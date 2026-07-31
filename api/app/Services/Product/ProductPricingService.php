<?php

namespace App\Services\Product;

use App\Models\Product\Product;

/**
 * Camada de resolução de preço compartilhada entre OrderService::create()
 * (quando o item não traz unit_price manual) e o endpoint
 * GET /products/{product}/suggested-price. Nunca decide sozinha sobre o
 * override manual por item — quem chama é responsável por só consultar
 * este serviço quando não houver unit_price explícito no request.
 */
class ProductPricingService
{
    public function resolvePrice(Product $product, ?object $client): float
    {
        return (float) $product->price;
    }
}
