<?php

namespace App\Models\Location;

use App\Models\BaseModel;

class Bairro extends BaseModel
{
    protected $table = 'bairros';

    protected $fillable = [
        'cidade_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'cidade_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function cidade()
    {
        return $this->belongsTo(Cidade::class);
    }
}
