<?php

namespace App\Services\Ticket;

use App\DTOs\Ticket\CreateTicketResaleListingDTO;
use App\DTOs\Ticket\TransferTicketDTO;
use App\Events\Ticket\TicketResaleListingCancelled;
use App\Events\Ticket\TicketResaleListingCreated;
use App\Events\Ticket\TicketResaleListingSold;
use App\Exceptions\InvalidResaleListingStateException;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Finance\PlatformFinanceSettings;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketResaleListing;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Repositories\Contracts\TicketResaleListingRepositoryInterface;
use App\Services\Sale\SalePaymentService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Revenda oficial verificada (roadmap Fase 4). Fechamento da revenda NÃO
 * reimplementa transferência/rotação de QR — sempre delega pra
 * TicketService::transfer() (mesma trava/lock, mesmo evento
 * TicketTransferred, mesma auditoria já existente).
 *
 * Repasse ao vendedor (decisão de escopo, ver migration): o valor pago
 * pelo comprador é registrado como Payment `paid` de imediato (mesma
 * mecânica manual já usada em SalePaymentService::registerManualPayment,
 * sem PSP mediando — reaproveitada via registerManualPaymentForPayable(),
 * não duplicada) porque a interface de PSP real (PaymentProviderInterface)
 * é inteiramente tipada em Sale/Invoice; forçar uma revenda P2P por ela
 * exigiria decisão de produto não validada (ex.: split automático pro
 * FinalCustomer vendedor, algo que NENHUM rail financeiro deste projeto
 * faz hoje — LedgerEntry/Settlement são do tenant, não de pessoa física).
 * O valor líquido devido ao vendedor fica registrado em
 * `seller_payout_amount`/`seller_payout_status=pendente_liberacao` e só
 * conta como pago ao vendedor quando o STAFF do tenant libera manualmente
 * (releasePayout()) — nenhum repasse automático é feito.
 */
class TicketResaleService
{
    public const EAGER_RELATIONS = ['ticket.ticketType.event', 'ticket.ticketType.session', 'seller', 'buyer'];

    public function __construct(
        private TicketResaleListingRepositoryInterface $repository,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
        private TicketService $ticketService,
        private SalePaymentService $paymentService,
    ) {}

    /**
     * Ingressos do cliente final elegíveis pra anunciar: `ativo`, sem
     * anúncio `listado` ativo ainda.
     */
    public function eligibleTickets(int $finalCustomerId): Collection
    {
        return Ticket::query()
            ->with(['ticketType.event', 'ticketType.session', 'seat'])
            ->where('status', 'ativo')
            ->whereHas('saleItem.sale', fn ($q) => $q->where('final_customer_id', $finalCustomerId))
            ->whereDoesntHave('resaleListings', fn ($q) => $q->where('status', 'listado'))
            ->get();
    }

    public function myListings(int $finalCustomerId): Collection
    {
        return TicketResaleListing::query()
            ->with(self::EAGER_RELATIONS)
            ->where('seller_final_customer_id', $finalCustomerId)
            ->orderByDesc('id')
            ->get();
    }

    public function browseActive(int $tenantId, ?string $eventUuid, ?string $eventSessionUuid, int $perPage = 15): LengthAwarePaginator
    {
        $query = TicketResaleListing::query()
            ->with(self::EAGER_RELATIONS)
            ->where('ticket_resale_listings.tenant_id', $tenantId)
            ->where('ticket_resale_listings.status', 'listado');

        if (! empty($eventUuid)) {
            $query->whereHas('ticket.ticketType.event', fn ($q) => $q->where('uuid', $eventUuid));
        }

        if (! empty($eventSessionUuid)) {
            $query->whereHas('ticket.ticketType.session', fn ($q) => $q->where('uuid', $eventSessionUuid));
        }

        return $query->orderBy('asking_price')->paginate($perPage);
    }

    public function create(int $sellerFinalCustomerId, string $ticketUuid, CreateTicketResaleListingDTO $dto): TicketResaleListing
    {
        return DB::transaction(function () use ($sellerFinalCustomerId, $ticketUuid, $dto) {
            $ticket = Ticket::query()
                ->with('saleItem.sale')
                ->where('uuid', $ticketUuid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            $sale = $ticket?->saleItem?->sale;

            if (! $ticket || ! $sale || (int) $sale->final_customer_id !== $sellerFinalCustomerId) {
                abort(404);
            }

            if ($ticket->status !== 'ativo') {
                throw new InvalidResaleListingStateException(__('messages.ticket_resale.ticket_not_eligible'));
            }

            $hasActiveListing = TicketResaleListing::where('ticket_id', $ticket->id)
                ->where('status', 'listado')
                ->exists();

            if ($hasActiveListing) {
                throw new InvalidResaleListingStateException(__('messages.ticket_resale.already_listed'));
            }

            $originalPrice = (float) $ticket->saleItem->unit_price;

            if ($dto->askingPrice <= 0 || $dto->askingPrice > $originalPrice) {
                throw new InvalidResaleListingStateException(__('messages.ticket_resale.price_above_cap'));
            }

            $listing = $this->repository->create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'seller_final_customer_id' => $sellerFinalCustomerId,
                'original_unit_price' => $originalPrice,
                'asking_price' => $dto->askingPrice,
                'status' => 'listado',
                'seller_payout_status' => 'nao_aplicavel',
            ]);

            $seller = FinalCustomer::find($sellerFinalCustomerId);

            event(new TicketResaleListingCreated(
                listingUuid: $listing->uuid,
                ticketUuid: $ticket->uuid,
                askingPrice: $dto->askingPrice,
                sellerFinalCustomerUuid: $seller?->uuid,
            ));

            return $listing;
        });
    }

    public function cancel(int $sellerFinalCustomerId, string $listingUuid): TicketResaleListing
    {
        return DB::transaction(function () use ($sellerFinalCustomerId, $listingUuid) {
            $listing = TicketResaleListing::where('uuid', $listingUuid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $listing || (int) $listing->seller_final_customer_id !== $sellerFinalCustomerId) {
                abort(404);
            }

            if ($listing->status !== 'listado') {
                throw new InvalidResaleListingStateException(__('messages.ticket_resale.not_cancellable'));
            }

            $listing->status = 'cancelado';
            $listing->cancelled_at = now();
            $listing->save();

            $seller = FinalCustomer::find($sellerFinalCustomerId);

            event(new TicketResaleListingCancelled(
                listingUuid: $listing->uuid,
                ticketUuid: $listing->ticket->uuid,
                sellerFinalCustomerUuid: $seller?->uuid,
            ));

            return $listing;
        });
    }

    /**
     * Fecha a revenda: cobra o comprador (Payment manual imediato, ver
     * docblock da classe), transfere titularidade via
     * TicketService::transfer() (rotaciona code/qr_token) e credita o
     * repasse pendente do vendedor. `tenant_id` global precisa já estar
     * vinculado (app()->instance) pelo caller — mesma exigência de
     * PortalController::transferTicket() (rota do portal não passa pelo
     * middleware `tenant`).
     */
    public function purchase(int $buyerFinalCustomerId, string $listingUuid, ?string $attendeeDocument = null): TicketResaleListing
    {
        return DB::transaction(function () use ($buyerFinalCustomerId, $listingUuid, $attendeeDocument) {
            $listing = TicketResaleListing::query()
                ->with('ticket')
                ->where('uuid', $listingUuid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $listing) {
                abort(404);
            }

            if ($listing->status !== 'listado') {
                throw new InvalidResaleListingStateException(__('messages.ticket_resale.no_longer_available'));
            }

            if ((int) $listing->seller_final_customer_id === $buyerFinalCustomerId) {
                throw new InvalidResaleListingStateException(__('messages.ticket_resale.cannot_buy_own_listing'));
            }

            $link = $this->linkRepository->findConfirmedByTenantAndFinalCustomer((int) $listing->tenant_id, $buyerFinalCustomerId);

            if (! $link) {
                abort(404);
            }

            $buyer = FinalCustomer::findOrFail($buyerFinalCustomerId);
            $seller = FinalCustomer::find($listing->seller_final_customer_id);

            $payment = $this->paymentService->registerManualPaymentForPayable($listing, 'pix', (float) $listing->asking_price);

            $attendeeName = trim((string) ($buyer->name.' '.($buyer->last_name ?? '')));

            $transferDto = new TransferTicketDTO(
                attendeeName: $attendeeName !== '' ? $attendeeName : $buyer->email,
                attendeeDocument: $attendeeDocument,
            );

            $this->ticketService->transfer($listing->ticket, $transferDto, $buyer->uuid);

            $feeSettings = PlatformFinanceSettings::query()->whereNull('deleted_at')->first();
            $platformFee = (float) ($feeSettings->platform_fee_fixed_amount ?? 0);
            $payoutAmount = max(0, (float) $listing->asking_price - $platformFee);

            $listing->buyer_final_customer_id = $buyerFinalCustomerId;
            $listing->payment_id = $payment->id;
            $listing->status = 'vendido';
            $listing->sold_at = now();
            $listing->seller_payout_amount = $payoutAmount;
            $listing->seller_payout_status = 'pendente_liberacao';
            $listing->save();

            event(new TicketResaleListingSold(
                listingUuid: $listing->uuid,
                ticketUuid: $listing->ticket->uuid,
                askingPrice: (float) $listing->asking_price,
                sellerFinalCustomerUuid: $seller?->uuid,
                buyerFinalCustomerUuid: $buyer->uuid,
            ));

            return $listing;
        });
    }

    /**
     * Liberação manual do repasse pro vendedor pelo staff do tenant
     * (decisão de escopo — ver docblock da classe: nenhum repasse
     * automático pra FinalCustomer existe neste projeto).
     */
    public function releasePayout(TicketResaleListing $listing, int $staffUserId): TicketResaleListing
    {
        $this->assertBelongsToCurrentTenant($listing);

        if ($listing->seller_payout_status !== 'pendente_liberacao') {
            throw new InvalidResaleListingStateException(__('messages.ticket_resale.payout_not_releasable'));
        }

        $listing->seller_payout_status = 'liberado';
        $listing->seller_payout_released_at = now();
        $listing->seller_payout_released_by = $staffUserId;
        $listing->save();

        return $listing;
    }

    private function assertBelongsToCurrentTenant(TicketResaleListing $listing): void
    {
        if ((int) $listing->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
