<?php

namespace Modules\Tenancy\Models;

use Modules\Core\Models\BaseModel;
use Modules\Tenancy\Concerns\BelongsToCompany;

class Site extends BaseModel
{
    use BelongsToCompany;

    protected $table = 'tn_sites';

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
}
