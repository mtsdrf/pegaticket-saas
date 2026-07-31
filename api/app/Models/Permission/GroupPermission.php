<?php

namespace App\Models\Permission;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupPermission extends Pivot
{
    use SoftDeletes, HasUuid;

    protected $table = 'group_permissions';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'group_id',
        'functionality_id',
        'action_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = ['id', 'group_id', 'functionality_id', 'action_id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'];
}