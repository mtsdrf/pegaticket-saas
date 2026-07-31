<?php

namespace App\Http\Controllers\Product;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\SuggestedPriceRequest;
use App\Http\Requests\Product\ToggleAvailabilityRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Client\Client;
use App\Models\Product\Product;
use App\Services\APIResponse;
use App\Services\Product\ProductPricingService;
use App\Services\Product\ProductService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const EAGER_RELATIONS = ProductService::EAGER_RELATIONS;
    private const DETAIL_RELATIONS = ProductService::DETAIL_RELATIONS;

    private const PDF_FILTERS = [
        'q',
        'name',
        'product_type_uuid',
        'product_category_uuid',
        'product_type_name',
        'product_category_name',
        'price_min',
        'price_max',
        'is_available',
    ];

    public function __construct(
        private ProductService $service,
        private ProductPricingService $pricingService
    ) {
    }

    public function index(ListProductRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'barcode',
            'product_type_uuid',
            'product_category_uuid',
            'product_type_name',
            'product_category_name',
            'price_min',
            'price_max',
            'is_available',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            ProductResource::collection($list),
            __('messages.product.list'),
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

    public function show(Product $product)
    {
        $product = $this->service->find($product);
        $product->load(self::DETAIL_RELATIONS);
        $product->loadSum('stockBalances', 'quantity_on_hand');

        return APIResponse::success(
            new ProductResource($product),
            __('messages.product.show')
        );
    }

    public function store(StoreProductRequest $request)
    {
        $dto = CreateProductDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        $product = $this->service->create($dto);
        $product->load(self::DETAIL_RELATIONS);
        $product->loadSum('stockBalances', 'quantity_on_hand');

        return APIResponse::success(
            new ProductResource($product),
            __('messages.product.created'),
            201
        );
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $dto = UpdateProductDTO::fromArray($request->validated());

        $product = $this->service->update($product, $dto);
        $product->load(self::DETAIL_RELATIONS);
        $product->loadSum('stockBalances', 'quantity_on_hand');

        return APIResponse::success(
            new ProductResource($product),
            __('messages.product.updated')
        );
    }

    /**
     * Ação administrativa rápida no PWA (roadmap A4, item 16) — bloquear/
     * desbloquear produto sem o payload inteiro de update(). Body opcional
     * `is_available`; sem ele, inverte o valor atual.
     */
    public function toggleAvailability(ToggleAvailabilityRequest $request, Product $product)
    {
        $isAvailable = $request->has('is_available')
            ? filter_var($request->input('is_available'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $product = $this->service->toggleAvailability($product, $isAvailable);
        $product->load(self::DETAIL_RELATIONS);
        $product->loadSum('stockBalances', 'quantity_on_hand');

        return APIResponse::success(
            new ProductResource($product),
            $product->is_available
                ? __('messages.product.availability_enabled')
                : __('messages.product.availability_disabled')
        );
    }

    public function destroy(Product $product)
    {
        $this->service->delete($product);

        return APIResponse::success(
            null,
            __('messages.product.deleted'),
            204
        );
    }

    /**
     * Preço sugerido pra pré-preencher o form de pedido antes da
     * confirmação — mesma resolução usada em OrderService::create() quando
     * o item não traz unit_price manual (ProductPricingService::resolvePrice).
     * client_uuid é opcional: sem ele, equivale a resolvePrice($product, null).
     */
    public function suggestedPrice(SuggestedPriceRequest $request, Product $product)
    {
        $product = $this->service->find($product);

        $client = null;

        if ($request->filled('client_uuid')) {
            $client = Client::where('uuid', $request->query('client_uuid'))
                ->where('tenant_id', app('tenant_id'))
                ->whereNull('deleted_at')
                ->first();
        }

        $price = $this->pricingService->resolvePrice($product, $client);

        return APIResponse::success(
            ['price' => number_format($price, 2, '.', '')],
            __('messages.product.suggested_price')
        );
    }

    /**
     * Catálogo completo em PDF "para o cliente" — mesmos filtros de index(),
     * sem paginação (ProductService::forPdf). Imagem embutida em base64 pela
     * própria view (products.pdf), isRemoteEnabled continua false.
     */
    public function pdf(Request $request)
    {
        $products = $this->service->forPdf(
            app('tenant_id'),
            $request->only(self::PDF_FILTERS)
        );

        $pdf = Pdf::loadView('products.pdf', [
            'products' => $products,
            'tenantName' => tenant()?->name,
            'generatedAt' => now(),
        ]);

        return response()->streamDownload(
            fn() => print($pdf->output()),
            'catalogo-produtos-' . now()->format('Ymd_His') . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
