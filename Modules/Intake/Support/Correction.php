<?php

namespace Modules\Intake\Support;

final class Correction
{
    public function __construct(
        public readonly string $command,   // kategori|total|vendor|tanggal|nomor|ok|tolak
        public readonly ?string $value,    // normalised raw value (null for ok/tolak)
        public readonly ?string $rowRef,   // last 4 hex of row_id when given as #xxxx
    ) {}
}
