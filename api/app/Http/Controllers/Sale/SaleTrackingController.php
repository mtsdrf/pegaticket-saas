<?php

namespace App\Http\Controllers\Sale;

use App\Exceptions\InvalidSaleStateException;
use App\Exceptions\Payment\PaymentOperationInProgressException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SalePaymentChargeRequest;
use App\Http\Requests\Sale\SalePaymentInstallmentOptionsRequest;
use App\Http\Resources\Sale\SalePaymentResource;
use App\Http\Resources\Sale\SalePublicTrackingResource;
use App\Models\Sale\Sale;
use App\Services\APIResponse;
use App\Services\Sale\SalePaymentService;
use App\Services\Security\AntiBotGuardService;
use App\Services\Security\PaymentChargeAttemptLimiter;
use App\Services\Ticket\TicketPdfService;

/**
 * Endpoint público (Fase 5.1 do roadmap), sem jwt/tenant/perm — protegido
 * só pelo uuid da venda ser imprevisível (uuid v4, 122 bits), mesmo
 * padrão de qualquer link de rastreio de transportadora. Route-model-
 * binding (HasUuid::resolveRouteBinding) já resolve por uuid e já cai em
 * 404 automático pra uuid inexistente ou venda soft-deletada — nenhum
 * guard extra necessário aqui. Sem Service/Repository: leitura simples de
 * um Model já resolvido pelo binding, sem filtro/paginação.
 */
class SaleTrackingController extends Controller
{
    public function __construct(
        private TicketPdfService $ticketPdfService,
        private SalePaymentService $salePaymentService,
        private AntiBotGuardService $antiBotGuard,
        private PaymentChargeAttemptLimiter $paymentChargeAttemptLimiter,
    ) {}

    public function show(Sale $sale)
    {
        $sale->load([
            'finalCustomer',
            'tenant',
            'items.ticketType.event',
            'items.eventProduct.event',
            'items.seat',
            'installments',
            'coupon',
            'latestPayment',
        ]);

        return APIResponse::success(
            new SalePublicTrackingResource($sale),
            __('messages.sale.tracking_shown')
        );
    }

    public function downloadTicketsPdf(Sale $sale)
    {
        abort_unless((bool) $sale->is_paid, 404);

        $tickets = $this->ticketPdfService->issuedTicketsForSale($sale);
        abort_if($tickets->isEmpty(), 404);

        $pdf = $this->ticketPdfService->generateForSale($sale, $tickets);

        return response()->streamDownload(
            fn () => print ($pdf['content']),
            $pdf['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function paymentCheckoutConfig(Sale $sale)
    {
        app()->instance('tenant_id', $sale->tenant_id);

        try {
            $config = $this->salePaymentService->checkoutConfigForOrder($sale);
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            $config,
            __('messages.sale.payment_checkout_config_loaded')
        );
    }

    public function paymentInstallmentOptions(SalePaymentInstallmentOptionsRequest $request, Sale $sale)
    {
        app()->instance('tenant_id', $sale->tenant_id);

        try {
            $options = $this->salePaymentService->installmentOptionsForOrder(
                $sale,
                (string) $request->validated('credit_card_bin'),
                (int) ($request->validated('max_installments') ?? 12),
                (int) ($request->validated('max_installments_no_interest') ?? 1),
            );
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            $options,
            __('messages.sale.payment_checkout_config_loaded')
        );
    }

    public function paymentCharge(SalePaymentChargeRequest $request, Sale $sale)
    {
        $this->antiBotGuard->assertHuman(
            $request->validated('website'),
            $request->validated('form_rendered_at'),
            $request->validated('turnstile_token'),
            $request->ip()
        );

        $this->paymentChargeAttemptLimiter->assertNotExceeded($sale->uuid);

        app()->instance('tenant_id', $sale->tenant_id);

        try {
            $payment = $this->salePaymentService->createChargeForOrder($sale, $request->validated());
        } catch (InvalidSaleStateException $e) {
            $this->paymentChargeAttemptLimiter->recordFailedAttempt($sale->uuid);

            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (PaymentOperationInProgressException $e) {
            $this->paymentChargeAttemptLimiter->recordFailedAttempt($sale->uuid);

            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            $this->paymentChargeAttemptLimiter->recordFailedAttempt($sale->uuid);

            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new SalePaymentResource($payment),
            __('messages.sale.payment_charge_created'),
            201
        );
    }
}
