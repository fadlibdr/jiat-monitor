<?php

namespace Modules\Intake\Support;

final class ValidationResult
{
    /**
     * @param  'reject'|'accept'  $decision
     * @param  list<string>  $flags
     */
    public function __construct(
        public readonly string $decision,
        public readonly string $status,        // perlu_konfirmasi | perlu_cek | (reject => '')
        public readonly ?string $outcome,      // tidak_terbaca on reject
        public readonly ?string $message,      // reply text on reject
        public readonly array $flags = [],
        public readonly ?string $npwp = null,  // sanitised: null if V6 failed
        public readonly array $missing = [],   // fields to mark in the reply (V3)
    ) {}

    public function rejected(): bool
    {
        return $this->decision === 'reject';
    }
}
