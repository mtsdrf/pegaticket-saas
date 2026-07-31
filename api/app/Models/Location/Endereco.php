<?php

namespace App\Models\Location;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

class Endereco extends BaseModel
{
    protected $table = 'enderecos';

    protected $fillable = [
        'tenant_id',
        'estado_id',
        'cidade_id',
        'bairro_id',
        'logradouro',
        'numero',
        'complemento',
        'cep',
        'is_active',
        'lat',
        'lng',
        'geocode_status',
        'geocoded_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'geocoded_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'estado_id',
        'cidade_id',
        'bairro_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cidade()
    {
        return $this->belongsTo(Cidade::class);
    }

    public function bairro()
    {
        return $this->belongsTo(Bairro::class);
    }
}
