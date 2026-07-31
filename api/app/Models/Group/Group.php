<?php

namespace App\Models\Group;

use App\Models\BaseModel;

class Group extends BaseModel
{
    protected $table = 'groups';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = ['id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'];

    public function users()
    {
        return $this->belongsToMany(\App\Models\User\User::class, 'group_user')
            ->using(\App\Models\Group\GroupUser::class)
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->withPivot(['uuid', 'created_by', 'updated_by', 'deleted_by', 'deleted_at']);
    }
}