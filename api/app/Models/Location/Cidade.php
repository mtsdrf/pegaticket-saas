<?php

namespace App\Models\Location;

use App\Models\BaseModel;

class Cidade extends BaseModel
{
    protected $table = 'cidades';

    protected $fillable = [
        'estado_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'estado_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function bairros()
    {
        return $this->hasMany(Bairro::class);
    }
}
