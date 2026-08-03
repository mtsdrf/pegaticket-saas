<?php

namespace App\Repositories\Contracts;

use App\Models\Sale\Sale;
use Illuminate\Support\Collection;

interface SaleRefundRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Estornos já registrados para a venda (não excluídos), soma usada
     * para validar que um novo estorno não ultrapassa o valor pago.
     */
    public function sumAmountForOrder(Sale $order): float;

    /**
     * Lista os estornos de uma venda, mais recentes primeiro, com os
     * tickets afetados carregados.
     */
    public function listForOrder(Sale $order): Collection;
}
