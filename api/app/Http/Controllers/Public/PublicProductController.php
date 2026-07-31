<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductResource;
use App\Services\APIResponse;
use App\Services\Product\ProductService;
use Illuminate\Http\Request;

/**
 * API pública (roadmap A6, item 20) — leitura de produtos autenticada por
 * API key. Mesmo raciocínio de PublicOrderController: reaproveita
 * ProductService/ProductResource, só troca a autenticação/prefixo de rota.
 */
class PublicProductController extends Controller
{
    public function __construct(private ProductService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'q',
            'name',
            'barcode',
            'product_type_uuid',
            'product_category_uuid',
            'is_available',
        ]);

        $list = $this->service->paginate(
            app('tenant_id'),
            $filters,
            (int) $request->query('per_page', 15),
            $request->query('sort_by'),
            $request->query('sort_dir', 'asc')
        );

        return APIResponse::success(
            ProductResource::collection($list),
            __('messages.public_api.products_list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }
}
