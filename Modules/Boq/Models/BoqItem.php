<?php

namespace Modules\Boq\Models;

use Modules\Core\Models\BaseModel;
use Modules\Tenancy\Concerns\BelongsToCompany;

class BoqItem extends BaseModel
{
    use BelongsToCompany;

    protected $table = 'boq_items';

    public const CATEGORIES = ['material', 'upah', 'alat', 'subkon', 'lain'];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'weight_pct' => 'decimal:6',
        'is_leaf' => 'boolean',
    ];
}
