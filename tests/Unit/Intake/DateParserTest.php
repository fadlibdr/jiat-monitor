<?php

namespace Tests\Unit\Intake;

use Modules\Intake\Support\DateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateParserTest extends TestCase
{
    public static function cases(): array
    {
        return [
            ['21/06/2026', '2026-06-21'], ['21-06-2026', '2026-06-21'], ['2026-06-21', '2026-06-21'],
            ['21 Juni 2026', '2026-06-21'], ['1 agu 2026', '2026-08-01'], ['5 Mei 2026', '2026-05-05'],
            ['  21   Juni   2026 ', '2026-06-21'],
            'dua digit tahun -> null' => ['21/06/26', null],
            'bulan 13 -> null' => ['01/13/2026', null],
            '31 Februari -> null' => ['31/02/2026', null],
            'bulan tak dikenal -> null' => ['21 Foo 2026', null],
            'teks -> null' => ['kemarin', null],
        ];
    }

    #[DataProvider('cases')]
    public function test_parse(string $in, ?string $out): void
    {
        $this->assertSame($out, DateParser::parse($in));
    }
}
