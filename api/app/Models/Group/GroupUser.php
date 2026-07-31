<?php

namespace App\Models\Group;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupUser extends Pivot
{
    use SoftDeletes, HasUuid;

    protected $table = 'group_user';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'group_id',
        'user_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = ['id', 'group_id', 'user_id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'];
}