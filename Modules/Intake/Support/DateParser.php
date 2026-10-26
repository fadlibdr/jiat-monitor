<?php

namespace Modules\Intake\Support;

/** Accepts DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD and "21 Juni 2026". Anything ambiguous or invalid -> null. */
final class DateParser
{
    private const MONTHS = [
        'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'pebruari' => 2, 'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'november' => 11, 'nov' => 11, 'nopember' => 11,
        'desember' => 12, 'des' => 12,
    ];

    public static function parse(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $raw)));

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            return self::make((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $s, $m)) {
            return self::make((int) $m[3], (int) $m[2], (int) $m[1]); // Indonesian order: day first
        }
        if (preg_match('/^(\d{1,2}) ([a-z]+)\.? (\d{4})$/', $s, $m) && isset(self::MONTHS[$m[2]])) {
            return self::make((int) $m[3], self::MONTHS[$m[2]], (int) $m[1]);
        }

        return null; // incl. two-digit years: "21/06/26" is not guessed
    }

    private static function make(int $y, int $mo, int $d): ?string
    {
        if ($y < 2000 || $y > 2100 || ! checkdate($mo, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
}
