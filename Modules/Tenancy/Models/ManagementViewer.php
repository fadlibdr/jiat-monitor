<?php

namespace Modules\Tenancy\Models;

use Modules\Core\Models\BaseModel;

class ManagementViewer extends BaseModel
{
    public const ROLES = ['holding_viewer', 'direksi'];

    protected $table = 'tn_management_viewers';
}
