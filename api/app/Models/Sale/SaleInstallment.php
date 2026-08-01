<?php

namespace App\Models\Sale;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

/**
 * Diferente de SaleItem (que continua um objeto de valor sem CRUD
 * próprio, ver PHPDoc lá): SaleInstallment ganhou gestão manual
 * (App\Services\Sale\SaleInstallmentService + rotas
 * POST/PUT/DELETE /orders/{order}/installments) a partir de 2026-07-12,
 * pra suportar correção manual de parcelas (paridade com o legado, que
 * tinha CRUD livre de parcela — mas com validação de soma que o legado
 * não tinha, ver architecture-decisions.md). Continua sem Repository
 * próprio: mutação sempre passa por Sale (tenant/lock), não por uma
 * rota RESTful de primeiro nível.
 */
class SaleInstallment extends BaseModel
{
    protected $table = 'order_installments';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'installment_number',
        'amount',
        'due_date',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'order_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order()
    {
        return $this->belongsTo(Sale::class);
    }

}
