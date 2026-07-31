<?php

namespace App\Services\Storefront;

use App\Models\Product\Product;
use App\Models\Storefront\ProductFavorite;
use App\Services\Product\ProductService;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Favoritos de produto pelo cliente final (roadmap Delivery, Fase 4 —
 * retenção). Sem Repository dedicado — mesma decisão já usada para
 * CouponRedemption/FinalCustomerOtp: tabela auxiliar simples, sem
 * BaseModel/soft delete, Service acessa o Model direto.
 */
class ProductFavoriteService
{
    /**
     * Idempotente: favorito existente é removido (unfavorite), inexistente
     * é criado (favorite). 404 se o uuid não corresponder a um produto
     * ativo (Product usa soft delete via BaseModel).
     */
    public function toggle(int $finalCustomerId, string $productUuid): array
    {
        $product = Product::where('uuid', $productUuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $favorite = ProductFavorite::where('final_customer_id', $finalCustomerId)
            ->where('product_id', $product->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return ['favorited' => false];
        }

        // Dois toques concorrentes no coração podem ambos passar pelo
        // `first()` acima sem achar nada e tentar criar — a unique composta
        // (final_customer_id, product_id) protege o dado, mas sem esse
        // catch o segundo vira 500 cru em vez de responder favorited=true
        // normalmente (mesmo padrão de FinalCustomerTenantLink/Fase 1).
        try {
            ProductFavorite::create([
                'final_customer_id' => $finalCustomerId,
                'product_id' => $product->id,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }
        }

        return ['favorited' => true];
    }

    /**
     * Produtos favoritados do cliente, mais recentes primeiro.
     * `is_favorited` marcado como atributo dinâmico (true, trivialmente,
     * já que é a própria lista de favoritos) para StorefrontProductResource
     * reaproveitar o mesmo contrato do catálogo.
     */
    public function list(int $finalCustomerId, int $perPage = 15): LengthAwarePaginator
    {
        $page = Product::query()
            ->join('product_favorites', 'product_favorites.product_id', '=', 'products.id')
            ->where('product_favorites.final_customer_id', $finalCustomerId)
            ->whereNull('products.deleted_at')
            ->with(['productType.productCategory'])
            ->select(ProductService::listColumns())
            ->orderByDesc('product_favorites.created_at')
            ->paginate($perPage);

        $page->getCollection()->each(
            fn(Product $product) => $product->setAttribute('is_favorited', true)
        );

        return $page;
    }
}
