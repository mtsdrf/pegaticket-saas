<?php

namespace App\Services\Product;

use App\Models\Client\Client;
use App\Models\Product\Product;
use App\Models\Product\ProductCategoryPrice;

/**
 * Camada de resolução de preço compartilhada entre OrderService::create()
 * (quando o item não traz unit_price manual) e o endpoint
 * GET /products/{product}/suggested-price. Nunca decide sozinha sobre o
 * override manual por item — quem chama é responsável por só consultar
 * este serviço quando não houver unit_price explícito no request.
 */
class ProductPricingService
{
    /**
     * Sem cliente, ou cliente sem categoria com override cadastrado para
     * este produto, cai no Product.price puro. Cliente com mais de uma
     * categoria com override aplicável usa o MENOR preço entre elas
     * (decisão confirmada com o usuário — o cliente sempre recebe o
     * melhor desconto pro qual se qualifica).
     */
    public function resolvePrice(Product $product, ?Client $client): float
    {
        return $this->resolveCategoryPrice($product, $client) ?? (float) $product->price;
    }

    /**
     * Preço de categoria aplicável ao cliente, ou `null` quando NÃO há
     * override de categoria (sem cliente, cliente sem categoria, ou
     * categoria(s) do cliente sem override pra este produto). Diferente de
     * resolvePrice(), NÃO cai no Product.price nesse caso — devolve null
     * pra quem chama decidir o próximo degrau (ex.: preço de atacado no
     * checkout da loja, que só se aplica a cliente SEM preço de categoria).
     */
    public function resolveCategoryPrice(Product $product, ?Client $client): ?float
    {
        if (!$client) {
            return null;
        }

        $categoryIds = $client->categories()->pluck('client_categories.id');

        if ($categoryIds->isEmpty()) {
            return null;
        }

        $lowestOverride = ProductCategoryPrice::where('product_id', $product->id)
            ->where('tenant_id', $product->tenant_id)
            ->whereIn('client_category_id', $categoryIds)
            ->min('price');

        return $lowestOverride !== null ? (float) $lowestOverride : null;
    }
}
