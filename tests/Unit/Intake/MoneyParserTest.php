<?php

namespace Tests\Unit\Intake;

use Modules\Intake\Support\MoneyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyParserTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'rupiah dengan titik ribuan dan koma desimal' => ['Rp 2.450.000,00', '2450000.00'],
            'koma ribuan gaya inggris' => ['2,450,000', '2450000.00'],
            'titik ribuan tanpa desimal' => ['2.450.000', '2450000.00'],
            'angka polos' => ['2450000', '2450000.00'],
            'Rp. dengan spasi' => ['Rp. 1.250.000', '1250000.00'],
            'desimal koma dua digit' => ['12,50', '12.50'],
            'ribuan satu titik' => ['2.450', '2450.00'],
            'nol di depan' => ['0007', '7.00'],
            'titik + 2 digit ambigu -> null' => ['245.50', null],
            'titik + 1 digit ambigu -> null' => ['12.5', null],
            'grup salah -> null' => ['1.234.56', null],
            'huruf -> null' => ['dua juta', null],
            'kosong -> null' => ['', null],
            'campur: titik ribuan, koma desimal' => ['1.234.567,89', '1234567.89'],
            'campur: koma ribuan, titik desimal' => ['1,234,567.89', '1234567.89'],
        ];
    }

    #[DataProvider('cases')]
    public function test_idr(string $in, ?string $out): void
    {
        $this->assertSame($out, MoneyParser::idr($in));
    }

    public function test_null_input(): void
    {
        $this->assertNull(MoneyParser::idr(null));
    }
}
