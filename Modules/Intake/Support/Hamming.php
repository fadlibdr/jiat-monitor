<?php

namespace Modules\Intake\Support;

/** Hamming distance between two equal-length hex strings (pHash, 16 hex = 64 bit). */
final class Hamming
{
    public static function distance(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b) || ! ctype_xdigit($a) || ! ctype_xdigit($b)) {
            throw new \InvalidArgumentException('pHash harus hex dengan panjang sama.');
        }
        $d = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $d += substr_count(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), '1');
        }

        return $d;
    }

    public static function isNearDuplicate(string $a, string $b, int $max = 6): bool
    {
        return self::distance($a, $b) <= $max;
    }
}
