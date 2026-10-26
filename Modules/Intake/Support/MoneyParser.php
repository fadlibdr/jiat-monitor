<?php

namespace Modules\Intake\Support;

/**
 * Rupiah text -> numeric(18,2) string, or null when the text is ambiguous.
 * Deviation from LLD §6 (flagged): the LLD strips every dot as a thousands mark, which would turn
 * "245.50" into 24550. The system never guesses a number, so a dot followed by 1-2 digits is null.
 */
final class MoneyParser
{
    public static function idr(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = preg_replace('/(rp\.?|idr)/i', '', $raw);
        $s = preg_replace('/[\s\x{00A0}]+/u', '', $s);
        if ($s === '' || ! preg_match('/^\d[\d.,]*$/', $s)) {
            return null;
        }

        $dots = substr_count($s, '.');
        $commas = substr_count($s, ',');

        if ($dots > 0 && $commas > 0) {
            // both present: the LAST separator is the decimal mark only if it is followed by exactly 2 digits
            $last = max(strrpos($s, '.'), strrpos($s, ','));
            $sep = $s[$last];
            $tail = substr($s, $last + 1);
            $other = $sep === '.' ? ',' : '.';
            if (strlen($tail) === 2 && substr_count($s, $sep) === 1) {
                $int = str_replace($other, '', substr($s, 0, $last));
                if (! self::groupsOk(substr($s, 0, $last), $other)) {
                    return null;
                }

                return self::fmt($int, $tail);
            }

            return null;
        }

        if ($dots > 0 || $commas > 0) {
            $sep = $dots > 0 ? '.' : ',';
            $n = max($dots, $commas);
            $last = strrpos($s, $sep);
            $tail = substr($s, $last + 1);
            if ($n === 1 && strlen($tail) === 2) {
                // "12,50" is a decimal (LLD); "12.50" is ambiguous in Indonesian text -> null
                return $sep === ',' && ctype_digit(substr($s, 0, $last))
                    ? self::fmt(substr($s, 0, $last), $tail)
                    : null;
            }
            if (self::groupsOk($s, $sep)) {
                return self::fmt(str_replace($sep, '', $s), '00');
            }

            return null;
        }

        return self::fmt($s, '00');
    }

    /** "2.450.000": first group 1-3 digits, every other group exactly 3. */
    private static function groupsOk(string $s, string $sep): bool
    {
        $parts = explode($sep, $s);
        if (strlen($parts[0]) < 1 || strlen($parts[0]) > 3) {
            return false;
        }
        foreach (array_slice($parts, 1) as $p) {
            if (strlen($p) !== 3 || ! ctype_digit($p)) {
                return false;
            }
        }

        return true;
    }

    private static function fmt(string $int, string $dec): string
    {
        $int = ltrim($int, '0');

        return ($int === '' ? '0' : $int).'.'.$dec;
    }
}
