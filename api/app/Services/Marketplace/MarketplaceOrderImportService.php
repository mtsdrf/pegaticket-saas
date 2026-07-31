<?php

namespace App\Services\Marketplace;

use App\DTOs\Order\CreateOrderDTO;
use App\Exceptions\Marketplace\MarketplaceIntegrationException;
use App\Models\Client\Client;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Marketplace\MarketplaceOrder;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Stock\StockLocation;
use App\Support\AuthContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceOrderImportService
{
    public function __construct(
        private \App\Services\Order\OrderService $orderService,
    ) {
    }

    public function import(MarketplaceOrder $marketplaceOrder): MarketplaceOrder
    {
        if ($marketplaceOrder->internal_order_id !== null) {
            return $marketplaceOrder->fresh(['internalOrder']);
        }

        return DB::transaction(function () use ($marketplaceOrder) {
            $marketplaceOrder = MarketplaceOrder::query()
                ->whereKey($marketplaceOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($marketplaceOrder->internal_order_id !== null) {
                return $marketplaceOrder->fresh(['internalOrder']);
            }

            $integration = $marketplaceOrder->integration()->firstOrFail();
            $payload = is_array($marketplaceOrder->payload) ? $marketplaceOrder->payload : [];
            $client = $this->resolveClient($marketplaceOrder->tenant_id, $payload);
            $stockLocation = $this->resolveDefaultStockLocation($marketplaceOrder->tenant_id);
            $items = $this->resolveItems($marketplaceOrder->tenant_id, $payload);

            if ($items === []) {
                throw new MarketplaceIntegrationException(__('messages.marketplace.order_has_no_supported_items'));
            }

            $externalState = (string) ($marketplaceOrder->status ?? '');

            $dto = new CreateOrderDTO(
                tenantId: $marketplaceOrder->tenant_id,
                clientUuid: $client->uuid,
                stockLocationUuid: $stockLocation->uuid,
                isInstallment: false,
                installmentsCount: null,
                notes: $this->buildNotes($integration->provider, $marketplaceOrder),
                expectedDeliveryDate: $this->resolveExpectedDeliveryDate($payload),
                markAsDelivered: false,
                markAsPaid: false,
                items: $items,
                origin: $integration->provider,
                status: $this->resolveInternalStatus($externalState),
                reserveStock: true,
                deliveryFee: $this->resolveDeliveryFee($payload),
                serviceFee: $this->resolveServiceFee($payload),
                couponId: null,
                discountAmount: $this->resolveDiscountAmount($payload),
                cashbackRedeemedAmount: 0.0,
                fulfillmentType: $this->resolveFulfillmentType($payload),
            );

            $order = $this->orderService->create($dto);

            $marketplaceOrder->forceFill([
                'internal_order_id' => $order->id,
                'imported_at' => now(),
                'import_error_message' => null,
            ])->save();

            return $marketplaceOrder->fresh(['internalOrder']);
        });
    }

    private function resolveClient(int $tenantId, array $payload): Client
    {
        $customer = $this->extractCustomer($payload);
        $name = trim((string) ($customer['name'] ?? $payload['customer_name'] ?? 'Cliente marketplace'));
        $phone = $this->normalizePhone((string) ($customer['phone'] ?? $customer['phoneNumber'] ?? $customer['mobilePhone'] ?? ''));
        $document = preg_replace('/\D+/', '', (string) ($customer['documentNumber'] ?? $customer['document'] ?? ''));
        $address = $this->resolveAddress($tenantId, $payload);

        $query = Client::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($phone !== '') {
            $existing = $query->where('phone_primary', $phone)->first();
            if ($existing) {
                $existing->forceFill([
                    'name' => $name !== '' ? $name : $existing->name,
                    'endereco_id' => $address->id,
                    'cpf_cnpj' => $document !== '' ? $document : $existing->cpf_cnpj,
                ])->save();

                return $existing->fresh();
            }
        }

        return Client::create([
            'tenant_id' => $tenantId,
            'endereco_id' => $address->id,
            'name' => $name !== '' ? $name : 'Cliente marketplace',
            'cpf_cnpj' => $document !== '' ? $document : null,
            'phone_primary' => $phone !== '' ? $phone : null,
            'notes' => 'Cliente criado automaticamente por importação de marketplace.',
            'is_trusted' => true,
            'is_active' => true,
            'created_by' => AuthContext::safeUserId(),
            'updated_by' => AuthContext::safeUserId(),
        ]);
    }

    /**
     * @return array<int, array{product_uuid: string, quantity: float, unit_price?: float}>
     */
    private function resolveItems(int $tenantId, array $payload): array
    {
        $rawItems = $this->extractItems($payload);
        $items = [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $product = $this->findProductForMarketplaceItem($tenantId, $rawItem);
            if (!$product) {
                $identifier = (string) ($rawItem['externalCode'] ?? $rawItem['sku'] ?? $rawItem['barcode'] ?? $rawItem['name'] ?? __('messages.marketplace.unknown_item'));
                throw new MarketplaceIntegrationException(__('messages.marketplace.product_mapping_not_found', ['item' => $identifier]));
            }

            $quantity = max((float) ($rawItem['quantity'] ?? Arr::get($rawItem, 'quantity.value') ?? 1), 0.001);
            $unitPrice = $this->resolveItemUnitPrice($rawItem, $product, $quantity);

            $items[] = [
                'product_uuid' => $product->uuid,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return $items;
    }

    private function findProductForMarketplaceItem(int $tenantId, array $rawItem): ?Product
    {
        $sku = trim((string) ($rawItem['externalCode'] ?? $rawItem['sku'] ?? Arr::get($rawItem, 'product.sku') ?? ''));
        if ($sku !== '') {
            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('sku', $sku)
                ->first();
            if ($product) {
                return $product;
            }
        }

        $barcode = trim((string) ($rawItem['barcode'] ?? Arr::get($rawItem, 'product.barcode') ?? ''));
        if ($barcode !== '') {
            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('barcode', $barcode)
                ->first();
            if ($product) {
                return $product;
            }
        }

        $name = trim((string) ($rawItem['name'] ?? Arr::get($rawItem, 'product.name') ?? ''));
        if ($name !== '') {
            return Product::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
        }

        return null;
    }

    private function resolveAddress(int $tenantId, array $payload): Endereco
    {
        $address = $this->extractAddress($payload);

        $stateName = trim((string) ($address['state'] ?? $address['stateName'] ?? ''));
        $stateUf = strtoupper(trim((string) ($address['stateCode'] ?? $address['uf'] ?? '')));
        if ($stateName === '' && $stateUf === '') {
            $stateName = 'Nao informado';
            $stateUf = 'NI';
        }

        $estado = Estado::query()
            ->whereNull('deleted_at')
            ->when($stateUf !== '', fn ($query) => $query->where('uf', $stateUf))
            ->first();

        if (!$estado) {
            $estado = Estado::create([
                'name' => $stateName !== '' ? $stateName : 'Nao informado',
                'uf' => $stateUf !== '' ? $stateUf : 'NI',
                'is_active' => true,
            ]);
        }

        $cityName = trim((string) ($address['city'] ?? $address['cityName'] ?? 'Nao informada'));
        $cidade = Cidade::query()
            ->where('estado_id', $estado->id)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityName)])
            ->first();

        if (!$cidade) {
            $cidade = Cidade::create([
                'estado_id' => $estado->id,
                'name' => $cityName,
                'is_active' => true,
            ]);
        }

        $districtName = trim((string) ($address['district'] ?? $address['neighborhood'] ?? $address['districtName'] ?? 'Nao informado'));
        $bairro = Bairro::query()
            ->where('cidade_id', $cidade->id)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($districtName)])
            ->first();

        if (!$bairro) {
            $bairro = Bairro::create([
                'cidade_id' => $cidade->id,
                'name' => $districtName,
                'is_active' => true,
            ]);
        }

        $logradouro = trim((string) ($address['streetName'] ?? $address['street'] ?? $address['formattedAddress'] ?? 'Endereco marketplace'));
        $numero = trim((string) ($address['streetNumber'] ?? $address['number'] ?? 'S/N'));
        $complemento = trim((string) ($address['complement'] ?? ''));
        $cep = preg_replace('/\D+/', '', (string) ($address['postalCode'] ?? $address['zipCode'] ?? $address['cep'] ?? ''));

        return Endereco::create([
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => $logradouro,
            'numero' => $numero !== '' ? $numero : null,
            'complemento' => $complemento !== '' ? $complemento : null,
            'cep' => $cep !== '' ? $cep : null,
            'is_active' => true,
            'geocode_status' => 'pending',
        ]);
    }

    private function resolveDefaultStockLocation(int $tenantId): StockLocation
    {
        return StockLocation::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('is_default', true)
            ->firstOrFail();
    }

    private function resolveExpectedDeliveryDate(array $payload): ?string
    {
        return Arr::get($payload, 'schedule.deliveryDateTime')
            ?? Arr::get($payload, 'delivery.deliveryDateTime')
            ?? null;
    }

    private function resolveInternalStatus(string $externalState): string
    {
        $normalized = mb_strtolower(trim($externalState));

        return match ($normalized) {
            'placed', 'plc', 'pending_confirmation', 'pending_approval' => 'pending_approval',
            'cancelled', 'canceled', 'rejected' => 'rejected',
            default => 'confirmed',
        };
    }

    private function resolveFulfillmentType(array $payload): string
    {
        $type = mb_strtolower((string) (
            Arr::get($payload, 'orderType')
            ?? Arr::get($payload, 'delivery.mode')
            ?? Arr::get($payload, 'takeout.mode')
            ?? 'delivery'
        ));

        return in_array($type, ['takeout', 'pickup', 'retirada'], true) ? 'pickup' : 'delivery';
    }

    private function resolveDeliveryFee(array $payload): float
    {
        return $this->resolveMoneyValue(
            Arr::get($payload, 'total.deliveryFee')
            ?? Arr::get($payload, 'totals.deliveryFee')
            ?? Arr::get($payload, 'delivery.deliveryFee')
        );
    }

    private function resolveServiceFee(array $payload): float
    {
        return $this->resolveMoneyValue(
            Arr::get($payload, 'total.serviceFee')
            ?? Arr::get($payload, 'totals.serviceFee')
            ?? 0
        );
    }

    private function resolveDiscountAmount(array $payload): float
    {
        return $this->resolveMoneyValue(
            Arr::get($payload, 'total.discount')
            ?? Arr::get($payload, 'totals.discount')
            ?? Arr::get($payload, 'benefits.total')
            ?? 0
        );
    }

    private function resolveItemUnitPrice(array $rawItem, Product $product, float $quantity): float
    {
        $resolved = Arr::get($rawItem, 'unitPrice')
            ?? Arr::get($rawItem, 'price')
            ?? Arr::get($rawItem, 'totalPrice');

        if (is_numeric($resolved)) {
            $value = (float) $resolved;

            if (Arr::has($rawItem, 'totalPrice') && $quantity > 0) {
                $value = $value / $quantity;
            }

            return round($value, 2);
        }

        return (float) $product->price;
    }

    private function resolveMoneyValue(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function buildNotes(string $provider, MarketplaceOrder $marketplaceOrder): string
    {
        return sprintf(
            'Importado automaticamente do %s. Pedido externo %s.',
            strtoupper($provider),
            $marketplaceOrder->display_id ?: $marketplaceOrder->external_id
        );
    }

    private function extractCustomer(array $payload): array
    {
        $customer = Arr::get($payload, 'customer');

        return is_array($customer) ? $customer : [];
    }

    private function extractAddress(array $payload): array
    {
        $address = Arr::get($payload, 'delivery.deliveryAddress')
            ?? Arr::get($payload, 'customer.address')
            ?? Arr::get($payload, 'deliveryAddress')
            ?? [];

        return is_array($address) ? $address : [];
    }

    /**
     * @return array<int, mixed>
     */
    private function extractItems(array $payload): array
    {
        $items = Arr::get($payload, 'items')
            ?? Arr::get($payload, 'orderItems')
            ?? [];

        return is_array($items) ? array_values($items) : [];
    }
}
