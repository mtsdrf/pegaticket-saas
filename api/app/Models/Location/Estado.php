<?php

namespace App\Models\Location;

use App\Models\BaseModel;

class Estado extends BaseModel
{
    protected $table = 'estados';

    protected $fillable = [
        'name',
        'uf',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function cidades()
    {
        return $this->hasMany(Cidade::class);
    }
}
