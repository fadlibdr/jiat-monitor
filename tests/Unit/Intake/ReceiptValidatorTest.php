<?php

namespace Tests\Unit\Intake;

use DateTimeImmutable as D;
use Modules\Intake\Support\ReceiptValidator;
use PHPUnit\Framework\TestCase;

class ReceiptValidatorTest extends TestCase
{
    private function ok(array $over = []): array
    {
        return array_replace([
            'invoice_date' => ['value' => '2026-06-21', 'confidence' => 0.94],
            'vendor_name' => ['value' => 'PT Sumber Beton', 'confidence' => 0.91],
            'line_items' => [['desc' => 'Semen', 'qty' => 40, 'unit' => 'sak', 'price' => 42000, 'amount' => 1680000],
                ['desc' => 'Pasir', 'qty' => 4, 'unit' => 'truk', 'price' => 192500, 'amount' => 770000]],
            'subtotal' => ['value' => 2450000, 'confidence' => 0.90],
            'ppn' => ['value' => null, 'confidence' => 0.0],
            'total' => ['value' => 2450000, 'confidence' => 0.95],
            'npwp' => ['value' => null, 'confidence' => 0.0],
        ], $over);
    }

    private function check(array $s1, array $s2, ?D $start = null)
    {
        return (new ReceiptValidator)->validate($s1, $s2, $start ?? new D('2026-06-01'), new D('2026-06-22'));
    }

    private array $good = ['doc_type' => 'nota', 'quality' => 0.9];

    public function test_clean_receipt_goes_to_perlu_konfirmasi(): void
    {
        $r = $this->check($this->good, $this->ok());
        $this->assertFalse($r->rejected());
        $this->assertSame('perlu_konfirmasi', $r->status);
        $this->assertSame([], $r->flags);
    }

    public function test_v1_bukan_dokumen_and_low_quality(): void
    {
        $this->assertTrue($this->check(['doc_type' => 'bukan_dokumen', 'quality' => 0.99], $this->ok())->rejected());
        $this->assertTrue($this->check(['doc_type' => 'nota', 'quality' => 0.44], $this->ok())->rejected());
        $this->assertFalse($this->check(['doc_type' => 'nota', 'quality' => 0.45], $this->ok())->rejected());
        $this->assertSame('tidak_terbaca', $this->check(['doc_type' => 'nota', 'quality' => 0.1], $this->ok())->outcome);
    }

    public function test_v2_total_missing_or_unsure(): void
    {
        $this->assertTrue($this->check($this->good, $this->ok(['total' => ['value' => null, 'confidence' => 0.9]]))->rejected());
        $this->assertTrue($this->check($this->good, $this->ok(['total' => ['value' => 100, 'confidence' => 0.69]]))->rejected());
        $this->assertFalse($this->check($this->good, $this->ok(['total' => ['value' => 2450000, 'confidence' => 0.70]]))->rejected());
    }

    public function test_v3_missing_fields_keep_row_as_perlu_cek(): void
    {
        $r = $this->check($this->good, $this->ok(['vendor_name' => ['value' => null, 'confidence' => 0.0]]));
        $this->assertFalse($r->rejected());
        $this->assertSame('perlu_cek', $r->status);
        $this->assertSame(['vendor_name'], $r->missing);
    }

    public function test_v4_items_arithmetic_tolerance(): void
    {
        $items = fn (int $a) => [['amount' => $a]];
        $sub = fn (int $v) => ['value' => $v, 'confidence' => 0.9];
        // tolerance = max(1000, 0.5% of 2.450.000 = 12.250) => 12.250
        $this->assertSame([], $this->check($this->good, $this->ok(['line_items' => $items(2450000 - 12250), 'subtotal' => $sub(2450000)]))->flags);
        $r = $this->check($this->good, $this->ok(['line_items' => $items(2450000 - 12251), 'subtotal' => $sub(2450000)]));
        $this->assertContains('arith_items', $r->flags);
        $this->assertSame('perlu_cek', $r->status);
    }

    public function test_v5_ppn_arithmetic(): void
    {
        $f = fn (int $ppn) => $this->ok(['ppn' => ['value' => $ppn, 'confidence' => 0.9], 'subtotal' => ['value' => 1000000, 'confidence' => 0.9],
            'total' => ['value' => 1110000, 'confidence' => 0.9], 'line_items' => []]);
        $this->assertNotContains('arith_ppn', $this->check($this->good, $f(110000))->flags);
        $this->assertNotContains('arith_ppn', $this->check($this->good, $f(111000))->flags);   // off by exactly 1000
        $this->assertContains('arith_ppn', $this->check($this->good, $f(111001))->flags);
    }

    public function test_v6_npwp(): void
    {
        $r = $this->check($this->good, $this->ok(['npwp' => ['value' => '12.345.678.9-012.345', 'confidence' => 0.9]]));
        $this->assertSame('123456789012345', $r->npwp);
        $this->assertNotContains('npwp_format', $r->flags);

        $r = $this->check($this->good, $this->ok(['npwp' => ['value' => '12345', 'confidence' => 0.9]]));
        $this->assertNull($r->npwp);
        $this->assertContains('npwp_format', $r->flags);
        $this->assertSame('perlu_konfirmasi', $r->status, 'npwp_format saja tidak menurunkan status');
    }

    public function test_v7_date_range(): void
    {
        $d = fn (string $v) => $this->ok(['invoice_date' => ['value' => $v, 'confidence' => 0.9]]);
        $this->assertNotContains('date_range', $this->check($this->good, $d('2026-05-02'))->flags); // site start 06-01 - 30d = 05-02
        $this->assertContains('date_range', $this->check($this->good, $d('2026-05-01'))->flags);
        $this->assertNotContains('date_range', $this->check($this->good, $d('2026-06-23'))->flags); // today + 1
        $this->assertContains('date_range', $this->check($this->good, $d('2026-06-24'))->flags);
    }
}
