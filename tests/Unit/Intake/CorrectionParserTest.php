<?php

namespace Tests\Unit\Intake;

use Modules\Intake\Support\Correction;
use Modules\Intake\Support\CorrectionParser as P;
use PHPUnit\Framework\TestCase;

class CorrectionParserTest extends TestCase
{
    public function test_all_commands_on_reply(): void
    {
        $this->assertSame(['kategori', 'upah'], $this->cv(P::parse('KATEGORI Upah', true)));
        $this->assertSame(['total', '2450000'], $this->cv(P::parse('total 2450000', true)));
        $this->assertSame(['total', '2.450.000,00'], $this->cv(P::parse('Total 2.450.000,00', true)));
        $this->assertSame(['vendor', 'PT Sumber Beton'], $this->cv(P::parse('VENDOR PT Sumber Beton', true)));
        $this->assertSame(['tanggal', '21/06/2026'], $this->cv(P::parse('tanggal 21/06/2026', true)));
        $this->assertSame(['nomor', 'INV-0621-A'], $this->cv(P::parse('NOMOR INV-0621-A', true)));
        $this->assertSame(['ok', null], $this->cv(P::parse('ok', true)));
        $this->assertSame(['tolak', null], $this->cv(P::parse('TOLAK', true)));
    }

    public function test_double_spaces_and_case(): void
    {
        $this->assertSame(['total', '100000'], $this->cv(P::parse('  TOTAL    100000  ', true)));
    }

    public function test_row_reference_without_reply(): void
    {
        $c = P::parse('total 2450000 #A1b2', false);
        $this->assertSame('a1b2', $c->rowRef);
        $this->assertSame('total', $c->command);
        $c = P::parse('OK #a1b2', false);
        $this->assertSame(['ok', 'a1b2'], [$c->command, $c->rowRef]);
        $c = P::parse('#a1b2 tolak', false);
        $this->assertSame('tolak', $c->command);
    }

    public function test_ignored_texts(): void
    {
        $this->assertNull(P::parse('total 2450000', false), 'bukan balasan dan tanpa #ref');
        $this->assertNull(P::parse('ok', false));
        $this->assertNull(P::parse('selamat pagi semua', true));
        $this->assertNull(P::parse('kategori pasir', true), 'kategori tak dikenal');
        $this->assertNull(P::parse('total abc', true));
        $this->assertNull(P::parse('', true));
        $this->assertNull(P::parse('ok saja', true));
    }

    private function cv(?Correction $c): ?array
    {
        return $c === null ? null : [$c->command, $c->value];
    }
}
