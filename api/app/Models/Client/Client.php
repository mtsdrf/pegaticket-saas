<?php

namespace App\Models\Client;

use App\Models\BaseModel;
use App\Models\Location\Endereco;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;

class Client extends BaseModel
{
    protected $table = 'clients';

    protected $fillable = [
        'tenant_id',
        'endereco_id',
        'dia_ideal_id',
        'periodo_ideal_id',
        'name',
        'last_name',
        // Cadastro fiscal do destinatário (roadmap Fiscal D0)
        'cpf_cnpj',
        'ie',
        'ie_indicator',
        'phone_primary',
        'phone_secondary',
        'notes',
        'is_trusted',
        'is_active',
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'endereco_id',
        'dia_ideal_id',
        'periodo_ideal_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function endereco()
    {
        return $this->belongsTo(Endereco::class);
    }

    public function diaIdeal()
    {
        return $this->belongsTo(DiaIdeal::class);
    }

    public function periodoIdeal()
    {
        return $this->belongsTo(PeriodoIdeal::class);
    }

    public function categories()
    {
        return $this->belongsToMany(ClientCategory::class, 'client_client_categories')
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->withPivot(['uuid', 'created_by', 'updated_by', 'deleted_by', 'deleted_at']);
    }

    /**
     * Sem escopo/filtro aqui de propósito (inverso simples de Order::client()) —
     * quem consome decide o filtro (ex: ReportService só carrega pedidos
     * ativos pagos+entregues via callback no with()).
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
