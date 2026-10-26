<?php

namespace Modules\Intake\Support;

use DateTimeImmutable;

/**
 * Validation rules V1-V7 of LLD §6 on the two OCR stage outputs. Pure: V8/V9/V10 need the database
 * and live in the intake job (Hamming and the 90-day window helper are here for it).
 */
final class ReceiptValidator
{
    public function __construct(
        private readonly float $qualityMin = 0.45,
        private readonly float $totalConfidenceMin = 0.70,
    ) {}

    /**
     * @param  array{doc_type?:string,quality?:float|int|null}  $stage1
     * @param  array<string,mixed>  $stage2  fields as {value, confidence}; line_items is a plain list
     */
    public function validate(array $stage1, array $stage2, ?DateTimeImmutable $siteStart, DateTimeImmutable $today): ValidationResult
    {
        // V1
        $docType = $stage1['doc_type'] ?? 'bukan_dokumen';
        $quality = (float) ($stage1['quality'] ?? 0);
        if ($docType === 'bukan_dokumen' || $quality < $this->qualityMin) {
            return new ValidationResult('reject', '', 'tidak_terbaca', 'Foto kurang jelas atau bukan nota. Mohon kirim ulang foto yang lebih jelas.');
        }

        // V2
        $total = $this->val($stage2, 'total');
        if ($total === null || $this->conf($stage2, 'total') < $this->totalConfidenceMin) {
            return new ValidationResult('reject', '', 'tidak_terbaca', 'Total tidak terbaca. Mohon kirim ulang foto yang lebih jelas.');
        }

        $flags = [];
        $missing = [];

        // V3
        foreach (['invoice_date', 'vendor_name'] as $f) {
            if ($this->val($stage2, $f) === null) {
                $missing[] = $f;
            }
        }
        if ($missing) {
            $flags[] = 'field_kosong';
        }

        // V4
        $items = $stage2['line_items'] ?? [];
        $subtotal = $this->val($stage2, 'subtotal');
        if (is_array($items) && $items !== [] && $subtotal !== null) {
            $sum = 0.0;
            foreach ($items as $it) {
                $sum += (float) ($it['amount'] ?? 0);
            }
            if (abs($sum - (float) $subtotal) > max(1000.0, 0.005 * (float) $subtotal)) {
                $flags[] = 'arith_items';
            }
        }

        // V5
        $ppn = $this->val($stage2, 'ppn');
        if ($ppn !== null && $subtotal !== null && abs((float) $subtotal + (float) $ppn - (float) $total) > 1000) {
            $flags[] = 'arith_ppn';
        }

        // V6
        $npwp = $this->val($stage2, 'npwp');
        if ($npwp !== null) {
            $digits = preg_replace('/\D/', '', (string) $npwp);
            if (! in_array(strlen($digits), [15, 16], true)) {
                $npwp = null;
                $flags[] = 'npwp_format';
            } else {
                $npwp = $digits;
            }
        }

        // V7
        $date = $this->val($stage2, 'invoice_date');
        if ($date !== null) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
            $lower = $siteStart?->modify('-30 days');
            $upper = $today->modify('+1 day');
            if ($d === false || ($lower !== null && $d < $lower) || $d > $upper) {
                $flags[] = 'date_range';
            }
        }

        $needsCheck = array_intersect($flags, ['field_kosong', 'arith_items', 'arith_ppn', 'date_range']) !== [];

        return new ValidationResult('accept', $needsCheck ? 'perlu_cek' : 'perlu_konfirmasi', null, null, array_values(array_unique($flags)), $npwp, $missing);
    }

    private function val(array $s, string $k): mixed
    {
        $f = $s[$k] ?? null;

        return is_array($f) ? ($f['value'] ?? null) : null;
    }

    private function conf(array $s, string $k): float
    {
        $f = $s[$k] ?? null;

        return is_array($f) ? (float) ($f['confidence'] ?? 0) : 0.0;
    }
}
