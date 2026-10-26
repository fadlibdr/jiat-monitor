<?php

namespace Modules\Tenancy\Models;

use Modules\Core\Models\BaseModel;

/** The tenant itself: deliberately NOT company-scoped. */
class Company extends BaseModel
{
    protected $table = 'tn_companies';
}
