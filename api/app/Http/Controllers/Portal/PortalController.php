<?php

namespace App\Http\Controllers\Portal;

use App\DTOs\Sale\RequestSaleCancellationDTO;
use App\DTOs\Ticket\TransferTicketDTO;
use App\Exceptions\InvalidSaleStateException;
use App\Exceptions\InvalidTicketStateException;
use App\Exceptions\Payment\PaymentOperationInProgressException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Exceptions\SaleAlreadyRatedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RateSaleRequest;
use App\Http\Requests\Portal\RequestSaleCancellationRequest;
use App\Http\Requests\Portal\TransferTicketRequest;
use App\Http\Requests\Sale\SalePaymentChargeRequest;
use App\Http\Requests\Sale\SalePaymentInstallmentOptionsRequest;
use App\Http\Resources\Portal\PortalMeResource;
use App\Http\Resources\Portal\PortalSaleResource;
use App\Http\Resources\Portal\PortalTicketResource;
use App\Http\Resources\Sale\SalePaymentResource;
use App\Services\APIResponse;
use App\Services\Portal\PortalCustomerService;
use App\Services\Portal\PortalSaleCancellationService;
use App\Services\Sale\SalePaymentService;
use App\Services\Storefront\SaleRatingService;
use App\Services\Ticket\TicketService;

class PortalController extends Controller
{
    public function __construct(
        private PortalCustomerService $service,
        private SaleRatingService $ratingService,
        private PortalSaleCancellationService $cancellationService,
        private SalePaymentService $paymentService,
        private TicketService $ticketService,
    ) {}

    public function sales()
    {
        $sales = $this->service->listOrders(portal_customer());

        return APIResponse::success(
            PortalSaleResource::collection($sales),
            __('messages.portal.sales_shown')
        );
    }

    public function me()
    {
        $customer = $this->service->me(portal_customer());

        return APIResponse::success(
            new PortalMeResource($customer),
            __('messages.portal.me_shown')
        );
    }

    /**
     * "Meus ingressos" da compra — endpoint próprio (não embutido em
     * `sales`/`saleItems`) porque o shape é diferente (QR/status de
     * check-in por ingresso, não item de venda). Posse verificada por
     * findOwnedOrder(), mesma checagem de reorder/avaliação/cancelamento.
     */
    public function saleTickets(string $uuid)
    {
        $sale = $this->service->findOwnedOrder(portal_customer()->id, $uuid);
        $sale->load(['items.tickets.ticketType.event', 'items.tickets.ticketType.session', 'items.tickets.seat']);

        $tickets = $sale->items->flatMap(fn ($item) => $item->tickets);

        return APIResponse::success(
            PortalTicketResource::collection($tickets),
            __('messages.portal.tickets_shown')
        );
    }

    /**
     * "Titularidade e transferência" (roadmap Fase 4) — posse verificada
     * via findOwnedTicket() (navega Ticket -> SaleItem -> Sale), mesmo
     * padrão de findOwnedOrder() usado no resto do Portal. tenant_id
     * vinculado manualmente (rota do portal não passa pelo middleware
     * `tenant`), mesma necessidade de paymentCharge().
     */
    public function transferTicket(TransferTicketRequest $request, string $uuid)
    {
        $ticket = $this->service->findOwnedTicket(portal_customer()->id, $uuid);

        app()->instance('tenant_id', $ticket->tenant_id);

        $dto = TransferTicketDTO::fromArray($request->validated());

        try {
            $ticket = $this->ticketService->transfer($ticket, $dto, portal_customer()->uuid);
        } catch (InvalidTicketStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_TICKET_STATE');
        }

        return APIResponse::success(
            new PortalTicketResource($ticket),
            __('messages.ticket.transferred')
        );
    }

    public function saleItems(string $uuid)
    {
        $items = $this->service->getSaleItemsForReorder(portal_customer(), $uuid);

        return APIResponse::success(
            ['items' => $items],
            __('messages.portal.sale_items_shown')
        );
    }

    public function rate(RateSaleRequest $request, string $uuid)
    {
        $data = $request->validated();

        try {
            $rating = $this->ratingService->rate(
                portal_customer()->id,
                $uuid,
                (int) $data['rating'],
                $data['comment'] ?? null
            );
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (SaleAlreadyRatedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'ORDER_ALREADY_RATED');
        }

        return APIResponse::success(
            ['rating' => $rating->rating, 'comment' => $rating->comment],
            __('messages.storefront.order_rated'),
            201
        );
    }

    /**
     * Cobrança Pix da PRÓPRIA venda (Fase B, item 1 — checkout Pix na loja
     * pública). Reaproveita `SalePaymentService::createPixChargeForOrder`
     * (mesmo caminho do endpoint de staff), mas com posse verificada via
     * `findOwnedOrder` (mesma checagem de "minha compra" do
     * reorder/avaliação/cancelamento) em vez de
     * `perm:sales,update` — o cliente final não tem Group/permissão, só
     * pode agir sobre a própria venda. `tenant_id` é vinculado manualmente
     * (`app()->instance`) porque a rota do portal não passa pelo middleware
     * `tenant` (o cliente final não pertence a um único tenant) — mesmo
     * valor que `assertBelongsToCurrentTenant` do Service exige.
     */
    public function paymentCheckoutConfig(string $uuid)
    {
        $sale = $this->service->findOwnedOrder(portal_customer()->id, $uuid);

        app()->instance('tenant_id', $sale->tenant_id);

        try {
            $config = $this->paymentService->checkoutConfigForOrder($sale);
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            $config,
            __('messages.sale.payment_checkout_config_loaded')
        );
    }

    public function paymentCharge(SalePaymentChargeRequest $request, string $uuid)
    {
        $sale = $this->service->findOwnedOrder(portal_customer()->id, $uuid);

        app()->instance('tenant_id', $sale->tenant_id);

        try {
            $payment = $this->paymentService->createChargeForOrder($sale, $request->validated());
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new SalePaymentResource($payment),
            __('messages.sale.payment_charge_created'),
            201
        );
    }

    public function paymentInstallmentOptions(SalePaymentInstallmentOptionsRequest $request, string $uuid)
    {
        $sale = $this->service->findOwnedOrder(portal_customer()->id, $uuid);

        app()->instance('tenant_id', $sale->tenant_id);

        try {
            $options = $this->paymentService->installmentOptionsForOrder(
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

    public function requestCancellation(RequestSaleCancellationRequest $request, string $uuid)
    {
        $dto = RequestSaleCancellationDTO::fromArray($request->validated());

        try {
            $sale = $this->cancellationService->request(portal_customer(), $uuid, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new PortalSaleResource($sale),
            __('messages.portal.cancellation_requested')
        );
    }
}
