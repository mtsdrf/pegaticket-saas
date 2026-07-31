<?php

namespace App\Repositories\Contracts;

use App\Models\Storefront\CartEvent;

/**
 * Contrato dedicado (não estende BaseRepositoryInterface): `cart_events`
 * não tem soft delete nem busca por uuid pública — é um ledger de
 * telemetria, write-only pela API. Ver App\Models\Storefront\CartEvent.
 */
interface CartEventRepositoryInterface
{
    public function store(array $data): CartEvent;
}
