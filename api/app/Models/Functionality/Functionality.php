<?php

namespace App\Models\Functionality;

use App\Models\BaseModel;

class Functionality extends BaseModel
{
    protected $table = 'functionalities';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = ['id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'];
}