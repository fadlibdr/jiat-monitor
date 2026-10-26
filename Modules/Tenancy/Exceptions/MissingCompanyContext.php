<?php

namespace Modules\Tenancy\Exceptions;

use RuntimeException;

class MissingCompanyContext extends RuntimeException
{
    public function __construct(string $message = 'Tidak ada perusahaan aktif: company_id tidak dapat ditentukan.')
    {
        parent::__construct($message);
    }
}
