<?php

namespace Modules\Intake\Support;

use Modules\Boq\Models\BoqItem;

/**
 * Chat correction parser (LLD §7). A text is processed only if it replies to a bot summary
 * ($isReplyToBot) or carries a "#xxxx" row reference (last 4 hex of row_id) next to a known keyword.
 * Anything else returns null and the group stays quiet. Values are returned raw: MoneyParser /
 * DateParser / vendor matching are applied by the caller so the rules live in one place.
 */
final class CorrectionParser
{
    public static function parse(string $text, bool $isReplyToBot): ?Correction
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text));
        if ($t === '') {
            return null;
        }

        $ref = null;
        if (preg_match('/(?:^|\s)#([0-9a-f]{4})(?=\s|$)/i', $t, $m)) {
            $ref = strtolower($m[1]);
            $t = trim(preg_replace('/\s+/', ' ', preg_replace('/(?:^|\s)#[0-9a-f]{4}(?=\s|$)/i', ' ', $t, 1)));
        }
        if (! $isReplyToBot && $ref === null) {
            return null;
        }

        $cats = implode('|', BoqItem::CATEGORIES);
        $rules = [
            'kategori' => "/^kategori ({$cats})$/i",
            'total' => '/^total ([\d.,]+)$/i',
            'vendor' => '/^vendor (.+)$/i',
            'tanggal' => '/^tanggal (\S+)$/i',
            'nomor' => '/^nomor (\S+)$/i',
            'ok' => '/^ok$/i',
            'tolak' => '/^tolak$/i',
        ];
        foreach ($rules as $cmd => $re) {
            if (preg_match($re, $t, $m)) {
                $value = in_array($cmd, ['ok', 'tolak'], true) ? null : trim($m[1]);
                if ($cmd === 'kategori') {
                    $value = strtolower($value);
                }

                return new Correction($cmd, $value, $ref);
            }
        }

        return null;
    }
}
