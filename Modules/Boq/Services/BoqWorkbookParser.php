<?php

namespace Modules\Boq\Services;

use Modules\Boq\Exceptions\BoqImportException;
use Modules\Intake\Support\MoneyParser;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads the RAB sheet of an AHSP workbook (layout "RAPI") into a BOQ tree. Pure: no DB.
 *
 * Rule zero (Principal): printed numbers are never altered. A printed jumlah wins over
 * qty x harga; any disagreement is returned in `anomalies` with location and both values.
 * All money is held as integer cents internally to avoid float drift.
 *
 * Layout assumption (BELUM TERVERIFIKASI against the real Keerom versi_2_RAPI file, which was
 * not available when this was written): a header row containing "Uraian" and "Jumlah" inside
 * the first 40 rows; columns are found by header text, not by fixed letter.
 */
class BoqWorkbookParser
{
    private const FOOTER = '/^\s*(jumlah|total|sub\s*total|ppn|dibulatkan|terbilang|harga\s+total)\b/i';

    /**
     * @return array{items: list<array<string,mixed>>, footers: list<array<string,mixed>>, anomalies: list<array<string,mixed>>, total_cents: int}
     */
    public function parse(string $path, string $sheetName = 'RAB'): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName($sheetName) ?? throw new BoqImportException("Sheet \"{$sheetName}\" tidak ditemukan.");
        $rows = $sheet->toArray(null, true, true, false); // calculated values, 0-based

        [$hdr, $cols] = $this->findHeader($rows);

        $items = [];
        $footers = [];
        $anomalies = [];
        $stack = []; // list of ['idx'=>int,'kind'=>string,'code'=>string]

        for ($i = $hdr + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $excelRow = $i + 1;
            $desc = trim((string) ($r[$cols['desc']] ?? ''));
            $code = trim((string) ($cols['code'] !== null ? ($r[$cols['code']] ?? '') : ''));
            $qty = $this->num($r[$cols['qty']] ?? null);
            $price = $this->cents($r[$cols['price']] ?? null);
            $amount = $this->cents($r[$cols['amount']] ?? null);

            if ($desc === '' && $code === '' && $qty === null && $price === null && $amount === null) {
                continue;
            }

            if ($code === '' && preg_match(self::FOOTER, $desc)) {
                $footers[] = ['row' => $excelRow, 'label' => $desc, 'amount_cents' => $amount];

                continue;
            }

            $isLeaf = ($qty !== null && $price !== null) || ($code !== '' && $amount !== null && $qty === null && $price === null && $this->looksLeaf($code, $rows, $i, $cols));
            if ($code === '') {
                $code = 'R'.$excelRow;
                $anomalies[] = ['row' => $excelRow, 'type' => 'row_without_code', 'detail' => "kode dibuat otomatis {$code}"];
            }

            $kind = $this->kind($code);
            while ($stack && ! $this->canParent(end($stack), $kind, $code)) {
                array_pop($stack);
            }
            $parent = $stack ? end($stack)['idx'] : null;
            $level = count($stack);
            // RABs reuse local numbering ("1", "2") under every parent, so the stored code is the full
            // path (II.A.1); the printed code is kept in code_printed. Dotted codes already carrying
            // their parent's prefix ("1.1" under "1") are left as printed.
            $printedCode = $code;
            if ($stack) {
                $top = end($stack);
                $isChildOfPrefix = $kind === 'number' && str_starts_with(rtrim($code, '.'), rtrim($top['code'], '.').'.');
                if (! $isChildOfPrefix) {
                    $code = $top['full'].'.'.rtrim($code, '.');
                }
            }

            $item = [
                'printed_amount_cents' => $isLeaf ? null : $amount,
                'row' => $excelRow, 'code' => $code, 'code_printed' => $printedCode, 'description' => $desc,
                'unit' => $this->str($r[$cols['unit']] ?? null),
                'qty' => $qty, 'unit_price_cents' => $price, 'amount_cents' => $isLeaf ? $amount : null,
                'parent_idx' => $parent, 'level' => $level, 'is_leaf' => $isLeaf,
            ];

            if ($isLeaf) {
                if ($amount === null && $qty !== null && $price !== null) {
                    $item['amount_cents'] = (int) round($qty * $price);
                    $anomalies[] = ['row' => $excelRow, 'type' => 'amount_computed', 'detail' => "jumlah kosong; qty x harga = {$item['amount_cents']} sen"];
                } elseif ($amount !== null && $qty !== null && $price !== null && abs($amount - round($qty * $price)) > 100) {
                    // keep the printed jumlah (rule zero); report both values
                    $anomalies[] = ['row' => $excelRow, 'type' => 'amount_mismatch',
                        'detail' => 'jumlah tercetak '.$amount.' sen != qty x harga '.round($qty * $price).' sen; jumlah tercetak dipakai'];
                }
                if ($item['amount_cents'] === null) {
                    throw new BoqImportException("Baris {$excelRow} ({$code}): item tanpa jumlah dan tanpa qty/harga.");
                }
            }

            $items[] = $item;
            $idx = count($items) - 1;
            if (! $isLeaf) {
                $stack[] = ['idx' => $idx, 'kind' => $kind, 'code' => $printedCode, 'full' => $code];
            }
        }

        // duplicate codes would break unique(contract_id, code)
        $seen = [];
        foreach ($items as $it) {
            if (isset($seen[$it['code']])) {
                throw new BoqImportException("Kode ganda \"{$it['code']}\" pada baris {$seen[$it['code']]} dan {$it['row']}.");
            }
            $seen[$it['code']] = $it['row'];
        }

        // roll leaf amounts up into parents (computed, never printed)
        foreach ($items as $k => $it) {
            if ($it['is_leaf']) {
                for ($p = $it['parent_idx']; $p !== null; $p = $items[$p]['parent_idx']) {
                    $items[$p]['amount_cents'] = ($items[$p]['amount_cents'] ?? 0) + $it['amount_cents'];
                }
            }
        }
        // a parent's printed subtotal, when present, must agree with the roll-up; the roll-up is stored
        foreach ($items as $k => $it) {
            if ($it['is_leaf']) {
                continue;
            }
            $items[$k]['amount_cents'] ??= 0;
            if ($it['printed_amount_cents'] !== null && $it['printed_amount_cents'] !== $items[$k]['amount_cents']) {
                $anomalies[] = ['row' => $it['row'], 'type' => 'subtotal_mismatch',
                    'detail' => "subtotal tercetak {$it['printed_amount_cents']} sen != jumlah anak {$items[$k]['amount_cents']} sen; jumlah anak dipakai"];
            }
        }

        $total = 0;
        foreach ($items as $it) {
            if ($it['is_leaf']) {
                $total += $it['amount_cents'];
            }
        }

        return ['items' => $items, 'footers' => $footers, 'anomalies' => $anomalies, 'total_cents' => $total];
    }

    /** @return array{0:int,1:array<string,int|null>} */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 40, true) as $i => $r) {
            $cells = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $r);
            $find = function (string $re) use ($cells) {
                foreach ($cells as $c => $v) {
                    if ($v !== '' && preg_match($re, $v)) {
                        return $c;
                    }
                }

                return null;
            };
            $desc = $find('/^uraian/');
            $amount = $find('/^jumlah/');
            if ($desc !== null && $amount !== null) {
                $cols = [
                    'code' => $find('/^(no\b|no\.|kode)/'),
                    'desc' => $desc,
                    'unit' => $find('/^satuan/'),
                    'qty' => $find('/^(volume|vol)/'),
                    'price' => $find('/^harga/'),
                    'amount' => $amount,
                ];
                foreach (['qty', 'price'] as $must) {
                    if ($cols[$must] === null) {
                        throw new BoqImportException("Kolom \"{$must}\" tidak ditemukan pada baris header ".($i + 1).'.');
                    }
                }

                return [$i, $cols];
            }
        }
        throw new BoqImportException('Baris header (Uraian ... Jumlah) tidak ditemukan pada 40 baris pertama.');
    }

    private function kind(string $code): string
    {
        $c = rtrim($code, '.');
        if (preg_match('/^[IVXLC]+$/', $c)) {
            return 'roman';
        }
        if (preg_match('/^[A-Z]$/', $c)) {
            return 'letter';
        }

        return 'number';
    }

    private function canParent(array $top, string $kind, string $code): bool
    {
        return match ($kind) {
            'roman' => false,
            'letter' => $top['kind'] === 'roman',
            default => in_array($top['kind'], ['roman', 'letter'], true)
                || ($top['kind'] === 'number' && str_starts_with(rtrim($code, '.'), rtrim($top['code'], '.').'.')),
        };
    }

    /** A row with an amount but no qty/price is a leaf only if the next coded row is not its child. */
    private function looksLeaf(string $code, array $rows, int $i, array $cols): bool
    {
        for ($j = $i + 1; $j < count($rows); $j++) {
            $next = trim((string) ($cols['code'] !== null ? ($rows[$j][$cols['code']] ?? '') : ''));
            if ($next === '') {
                continue;
            }

            return ! str_starts_with(rtrim($next, '.'), rtrim($code, '.').'.')
                && ! ($this->kind($code) !== 'number' && $this->kind($next) === 'number');
        }

        return true;
    }

    private function str(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $m = MoneyParser::idr((string) $v);

        return $m === null ? null : (float) $m;
    }

    private function cents(mixed $v): ?int
    {
        $n = $this->num($v);

        return $n === null ? null : (int) round($n * 100);
    }
}
