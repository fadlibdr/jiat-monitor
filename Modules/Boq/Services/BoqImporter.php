<?php

namespace Modules\Boq\Services;

use Illuminate\Support\Facades\DB;
use Modules\Boq\Exceptions\BoqImportException;
use Modules\Boq\Models\BoqItem;
use Modules\Tenancy\Models\Contract;
use Modules\Tenancy\Support\CompanyContext;

/**
 * Imports a RAB workbook into boq_items for one contract (LLD §2, §4 POST /boq/import).
 * Refuses (and writes nothing) unless SUM(leaf amounts) == contract.value_dpp exactly.
 */
class BoqImporter
{
    public function __construct(private readonly BoqWorkbookParser $parser = new BoqWorkbookParser) {}

    /**
     * @return array{items:int, leaves:int, total:string, contract_value_dpp:string, diff:string, anomalies:list<array<string,mixed>>, footers:list<array<string,mixed>>}
     */
    public function import(Contract $contract, string $path, string $sheet = 'RAB'): array
    {
        if (CompanyContext::id() !== (int) $contract->company_id) {
            throw new BoqImportException('Kontrak bukan milik perusahaan aktif.');
        }

        $parsed = $this->parser->parse($path, $sheet);
        $contractCents = (int) round(((float) $contract->getRawOriginal('value_dpp')) * 100);
        $diff = $parsed['total_cents'] - $contractCents;

        $summary = [
            'items' => count($parsed['items']),
            'leaves' => count(array_filter($parsed['items'], fn ($i) => $i['is_leaf'])),
            'total' => $this->fmt($parsed['total_cents']),
            'contract_value_dpp' => $this->fmt($contractCents),
            'diff' => $this->fmt($diff),
            'anomalies' => $parsed['anomalies'],
            'footers' => $parsed['footers'],
        ];

        if ($diff !== 0) {
            throw new BoqImportException(
                "Total BOQ {$summary['total']} berbeda dari nilai DPP kontrak {$summary['contract_value_dpp']} (selisih {$summary['diff']}). Tidak ada data ditulis."
            );
        }
        if ($contractCents <= 0) {
            throw new BoqImportException('Nilai DPP kontrak harus > 0.');
        }

        DB::transaction(function () use ($contract, $parsed, $contractCents) {
            BoqItem::query()->where('contract_id', $contract->id)->delete();

            $weights = $this->leafWeights($parsed['items'], $contractCents);
            // roll leaf weights up so every parent = sum of its children
            foreach ($parsed['items'] as $k => $it) {
                $parsed['items'][$k]['weight'] = $it['is_leaf'] ? $weights[$k] : 0;
            }
            foreach ($parsed['items'] as $k => $it) {
                if ($it['is_leaf']) {
                    for ($p = $it['parent_idx']; $p !== null; $p = $parsed['items'][$p]['parent_idx']) {
                        $parsed['items'][$p]['weight'] += $it['weight'];
                    }
                }
            }

            $ids = [];
            foreach ($parsed['items'] as $k => $it) {
                $row = BoqItem::query()->create([
                    'company_id' => $contract->company_id,
                    'contract_id' => $contract->id,
                    'parent_id' => $it['parent_idx'] === null ? null : $ids[$it['parent_idx']],
                    'code' => $it['code'],
                    'code_printed' => $it['code_printed'],
                    'description' => $it['description'],
                    'unit' => $it['unit'],
                    'qty' => $it['qty'],
                    'unit_price' => $it['unit_price_cents'] === null ? null : $it['unit_price_cents'] / 100,
                    'amount' => $it['amount_cents'] / 100,
                    'weight_pct' => $it['weight'] / 1_000_000,
                    'level' => $it['level'],
                    'is_leaf' => $it['is_leaf'],
                    'sort_order' => $k,
                ]);
                $ids[$k] = $row->id;
            }
        });

        return $summary;
    }

    /**
     * Leaf weights in millionths of a percent. Rounded per leaf; the rounding residual goes to
     * the largest leaf so the total is exactly 100.000000 (derived data, not a source number).
     *
     * @return array<int,int>
     */
    private function leafWeights(array $items, int $totalCents): array
    {
        $w = [];
        $sum = 0;
        $largest = null;
        foreach ($items as $k => $it) {
            if (! $it['is_leaf']) {
                continue;
            }
            $w[$k] = (int) round($it['amount_cents'] / $totalCents * 100_000_000); // pct x 1e6 (float ratio: cents x 1e8 would overflow int64)
            $sum += $w[$k];
            if ($largest === null || $it['amount_cents'] > $items[$largest]['amount_cents']) {
                $largest = $k;
            }
        }
        if ($largest !== null) {
            $w[$largest] += 100_000_000 - $sum;
        }

        return $w;
    }

    private function fmt(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $c = abs($cents);

        return $sign.intdiv($c, 100).'.'.str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }
}
