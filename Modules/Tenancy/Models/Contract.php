<?php

namespace Modules\Tenancy\Models;

use Modules\Core\Models\BaseModel;
use Modules\Tenancy\Concerns\BelongsToCompany;

class Contract extends BaseModel
{
    use BelongsToCompany;

    protected $table = 'tn_contracts';

    protected $casts = [
        'value_dpp' => 'decimal:2',
        'ppn_rate' => 'decimal:4',
        'value_total' => 'decimal:2',
        'signed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
