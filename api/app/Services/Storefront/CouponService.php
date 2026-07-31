<?php

namespace App\Services\Storefront;

use App\DTOs\Storefront\CreateCouponDTO;
use App\DTOs\Storefront\UpdateCouponDTO;
use App\Events\Storefront\CouponCreated;
use App\Events\Storefront\CouponDeleted;
use App\Events\Storefront\CouponUpdated;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\CouponUsageLimitReachedException;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\CouponRedemption;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cupom de desconto sobre o carrinho todo (roadmap Delivery, Fase 3) — CRUD
 * normal, diferente de StoreDeliveryFeeService (não é upsert 1-por-chave,
 * um tenant pode ter vários cupons ativos ao mesmo tempo, cada um com seu
 * próprio código). validateForCheckout() é usado só pra montar/validar o
 * cupom nesta fatia — a integração real no guard 4 do checkout (consumo de
 * CouponRedemption) é a PRÓXIMA fatia do roadmap.
 */
class CouponService
{
    public function __construct(
        private CouponRepositoryInterface $repository
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(int $tenantId, CreateCouponDTO $dto): Coupon
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $coupon = $this->repository->create([
                'tenant_id' => $tenantId,
                'code' => $dto->code,
                'type' => $dto->type,
                'value' => $dto->value,
                'minimum_order_value' => $dto->minimumOrderValue,
                'max_uses_total' => $dto->maxUsesTotal,
                'max_uses_per_customer' => $dto->maxUsesPerCustomer,
                'starts_at' => $dto->startsAt,
                'expires_at' => $dto->expiresAt,
                'is_active' => $dto->isActive,
                'allowed_payment_methods' => $dto->allowedPaymentMethods,
            ]);

            event(new CouponCreated(couponUuid: $coupon->uuid, actorId: Auth::id()));

            return $coupon;
        });
    }

    public function update(int $tenantId, string $uuid, UpdateCouponDTO $dto): Coupon
    {
        return DB::transaction(function () use ($tenantId, $uuid, $dto) {
            $coupon = Coupon::where('uuid', $uuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $coupon = $this->repository->update($coupon, [
                'code' => $dto->code,
                'type' => $dto->type,
                'value' => $dto->value,
                'minimum_order_value' => $dto->minimumOrderValue,
                'max_uses_total' => $dto->maxUsesTotal,
                'max_uses_per_customer' => $dto->maxUsesPerCustomer,
                'starts_at' => $dto->startsAt,
                'expires_at' => $dto->expiresAt,
                'is_active' => $dto->isActive,
                'allowed_payment_methods' => $dto->allowedPaymentMethods,
            ]);

            event(new CouponUpdated(couponUuid: $coupon->uuid, actorId: Auth::id()));

            return $coupon;
        });
    }

    public function delete(int $tenantId, string $uuid): void
    {
        DB::transaction(function () use ($tenantId, $uuid) {
            $coupon = Coupon::where('uuid', $uuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $this->repository->delete($coupon);

            event(new CouponDeleted(couponUuid: $uuid, actorId: Auth::id()));
        });
    }

    /**
     * Valida a elegibilidade de um cupom para o checkout, NESSA ORDEM (cada
     * falha lança a exceção certa, com a mensagem mais específica possível):
     * 1. Existe + is_active=true.
     * 2. Dentro da janela starts_at/expires_at (quando configurados).
     * 3. Meio de pagamento pretendido está na allowlist do cupom (quando
     *    configurada — null = sem restrição).
     * 4. subtotalCents >= minimum_order_value (quando configurado).
     * 5. Uso total (max_uses_total) ainda não esgotado.
     * 6. Uso por cliente (max_uses_per_customer) ainda não esgotado.
     * Retorna o Coupon se passar em tudo — quem chama decide o que fazer
     * com ele (calcular desconto, criar CouponRedemption etc.).
     */
    public function validateForCheckout(
        int $tenantId,
        string $code,
        int $finalCustomerId,
        int $subtotalCents,
        ?string $paymentMethod = null
    ): Coupon {
        $coupon = $this->validateEligibility($tenantId, $code, $subtotalCents, $paymentMethod);

        if ($coupon->max_uses_per_customer !== null) {
            $customerUses = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('final_customer_id', $finalCustomerId)
                ->count();

            if ($customerUses >= $coupon->max_uses_per_customer) {
                throw new CouponUsageLimitReachedException(__('messages.coupon.usage_limit_reached'));
            }
        }

        return $coupon;
    }

    /**
     * Mesma validação de validateForCheckout(), SEM o check 6 (limite por
     * cliente) — usada pela prévia pública de cupom (POST
     * /loja/{slug}/cupons/validar), que roda antes do OTP/identificação do
     * cliente final. Simplificação documentada no plano: o limite por
     * cliente só é garantido de fato no submit final do checkout.
     * $paymentMethod é opcional aqui — a prévia hoje não coleta o meio de
     * pagamento pretendido, então um cupom restrito por meio pode aparecer
     * como "válido" na prévia e ser rejeitado só no checkout final (guard
     * de verdade). Ver .claude/memory/architecture-decisions.md.
     */
    public function validatePreview(int $tenantId, string $code, int $subtotalCents, ?string $paymentMethod = null): Coupon
    {
        return $this->validateEligibility($tenantId, $code, $subtotalCents, $paymentMethod);
    }

    /**
     * Checks 1-5 (existe+ativo, janela de datas, meio de pagamento, mínimo,
     * uso total) — comuns a validateForCheckout()/validatePreview(). O
     * check 6 (uso por cliente) só existe em validateForCheckout(), pois
     * depende de uma identidade de cliente conhecida.
     */
    private function validateEligibility(int $tenantId, string $code, int $subtotalCents, ?string $paymentMethod = null): Coupon
    {
        $coupon = Coupon::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            throw new InvalidCouponException(__('messages.coupon.invalid'));
        }

        $now = now();

        if ($coupon->starts_at !== null && $now->lt($coupon->starts_at)) {
            throw new InvalidCouponException(__('messages.coupon.not_yet_available'));
        }

        if ($coupon->expires_at !== null && $now->gt($coupon->expires_at)) {
            throw new InvalidCouponException(__('messages.coupon.expired'));
        }

        if (
            $coupon->allowed_payment_methods !== null
            && ($paymentMethod === null || !in_array($paymentMethod, $coupon->allowed_payment_methods, true))
        ) {
            throw new InvalidCouponException(__('messages.coupon.payment_method_not_allowed'));
        }

        if ($coupon->minimum_order_value !== null) {
            $minimumCents = (int) round((float) $coupon->minimum_order_value * 100);

            if ($subtotalCents < $minimumCents) {
                $minimum = number_format((float) $coupon->minimum_order_value, 2, ',', '.');

                throw new InvalidCouponException(
                    __('messages.coupon.below_minimum_order', ['minimum' => $minimum])
                );
            }
        }

        if ($coupon->max_uses_total !== null) {
            $totalUses = CouponRedemption::where('coupon_id', $coupon->id)->count();

            if ($totalUses >= $coupon->max_uses_total) {
                throw new CouponUsageLimitReachedException(__('messages.coupon.usage_limit_reached'));
            }
        }

        return $coupon;
    }

    /**
     * Desconto em centavos para um Coupon já validado, dado o subtotal (com
     * promoção/desconto de categoria já aplicados) e a taxa de entrega em
     * centavos (só relevante para type=free_shipping; default 0 para a
     * prévia pública, que não conhece a taxa de entrega ainda — bairro só é
     * escolhido depois, no checkout final).
     */
    public function calculateDiscountCents(Coupon $coupon, int $subtotalCents, int $deliveryFeeCents = 0): int
    {
        return match ($coupon->type) {
            'percentage' => min($subtotalCents, (int) round($subtotalCents * ((float) $coupon->value) / 100)),
            'fixed' => min($subtotalCents, (int) round(((float) $coupon->value) * 100)),
            'free_shipping' => $deliveryFeeCents,
            default => 0,
        };
    }
}
