<?php

namespace App\Providers;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CartEventRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\EventCategoryRepositoryInterface;
// Repository Interfaces
use App\Repositories\Contracts\EventProductRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\EventSessionRepositoryInterface;
use App\Repositories\Contracts\FinalCustomerRepositoryInterface;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Repositories\Contracts\FunctionalityRepositoryInterface;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Repositories\Contracts\HelpRequestRepositoryInterface;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\LegalDocumentRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PlanFunctionalityRepositoryInterface;
use App\Repositories\Contracts\PlanPriceRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PrivacyRequestRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\ReleaseNoteRepositoryInterface;
use App\Repositories\Contracts\SaleRefundRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\SeatRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\TenantFeatureOverrideRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TenantRolePermissionRepositoryInterface;
use App\Repositories\Contracts\TenantRoleRepositoryInterface;
use App\Repositories\Contracts\TenantSettingsRepositoryInterface;
use App\Repositories\Contracts\TenantUserInviteRepositoryInterface;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Repositories\Contracts\TicketBatchRepositoryInterface;
use App\Repositories\Contracts\TicketCheckinRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\CartEventRepository;
use App\Repositories\Eloquent\CashSessionRepository;
use App\Repositories\Eloquent\CouponRepository;
use App\Repositories\Eloquent\EventCategoryRepository;
// Repository Implementations
use App\Repositories\Eloquent\EventProductRepository;
use App\Repositories\Eloquent\EventRepository;
use App\Repositories\Eloquent\EventSessionRepository;
use App\Repositories\Eloquent\FinalCustomerRepository;
use App\Repositories\Eloquent\FinalCustomerTenantLinkRepository;
use App\Repositories\Eloquent\FunctionalityRepository;
use App\Repositories\Eloquent\GroupRepository;
use App\Repositories\Eloquent\HelpRequestRepository;
use App\Repositories\Eloquent\IdempotencyRepository;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\LegalDocumentRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PlanFunctionalityRepository;
use App\Repositories\Eloquent\PlanPriceRepository;
use App\Repositories\Eloquent\PlanRepository;
use App\Repositories\Eloquent\PrivacyRequestRepository;
use App\Repositories\Eloquent\RefundRepository;
use App\Repositories\Eloquent\ReleaseNoteRepository;
use App\Repositories\Eloquent\SaleRefundRepository;
use App\Repositories\Eloquent\SaleRepository;
use App\Repositories\Eloquent\SeatRepository;
use App\Repositories\Eloquent\SubscriptionRepository;
use App\Repositories\Eloquent\TenantFeatureOverrideRepository;
use App\Repositories\Eloquent\TenantRepository;
use App\Repositories\Eloquent\TenantRolePermissionRepository;
use App\Repositories\Eloquent\TenantRoleRepository;
use App\Repositories\Eloquent\TenantSettingsRepository;
use App\Repositories\Eloquent\TenantUserInviteRepository;
use App\Repositories\Eloquent\TenantUserRepository;
use App\Repositories\Eloquent\TicketBatchRepository;
use App\Repositories\Eloquent\TicketCheckinRepository;
use App\Repositories\Eloquent\TicketRepository;
use App\Repositories\Eloquent\TicketTypeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VenueRepository;
use App\Services\Payment\ManualPaymentProvider;
use App\Services\Payment\MercadoPagoPaymentProvider;
use App\Services\Payment\PagBankPaymentProvider;
use App\Services\Sale\SalePaymentService;
use Illuminate\Cache\RateLimiting\Limit;
// Payment provider (cobrança de planos — roadmap 1B)
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Minishlink\WebPush\WebPush;

/**
 * Class AppServiceProvider
 *
 * Service Provider principal da aplicação.
 * Registra bindings de Repositories e outras dependências.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRepositories();

        // Web Push (VAPID) — singleton para ser injetado em
        // PushNotificationService; teste substitui via app()->instance() /
        // $this->mock() para nunca bater no provedor de push real.
        // `@` na construção: Minishlink\WebPush\Utils::checkRequirement()
        // dispara trigger_error(E_USER_NOTICE) quando as extensões
        // opcionais bcmath/gmp não estão instaladas (recomendadas só por
        // performance, não obrigatórias) — o HandleExceptions do Laravel
        // converte QUALQUER notice em ErrorException real, o que faria a
        // simples resolução do container (ex.: dentro de um listener)
        // quebrar o fluxo principal do pedido só por causa de uma extensão
        // opcional ausente. Suprimir aqui é seguro: a lib já tem fallback
        // funcional (bcmath/gmp são só otimização de performance).
        //
        // try/catch abaixo cobre um caso que o `@` NÃO cobre: se
        // VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY/VAPID_SUBJECT estiverem vazios
        // (ex.: ambiente novo cujo .env ainda não recebeu as chaves reais),
        // Minishlink\WebPush\VAPID::validate() dá `throw new ErrorException`
        // explícito — `@` só suprime notice/warning, nunca uma exception
        // lançada de propósito. Sem o catch, essa exception derruba a
        // resolução do container inteira (o listener de push é resolvido
        // de forma síncrona dentro da mesma transação de
        // deliver/approve/reject de pedido), causando rollback + 500 num
        // fluxo que não tem nada a ver com push notification. Falha real
        // 2026-07-17: "Entregar pedido" 500 em produção porque o .env novo
        // ainda não tinha as chaves VAPID preenchidas — mesmo bug atingia
        // aprovar/rejeitar pedido pelo mesmo motivo. Com o catch, o
        // WebPush é construído sem VAPID (envio de fato falha depois,
        // dentro do try/catch já existente em
        // PushNotificationService::sendToSubscription(), que é o lugar
        // certo para essa falha morrer).
        $this->app->singleton(WebPush::class, function () {
            try {
                return @new WebPush([
                    'VAPID' => [
                        'subject' => config('services.vapid.subject'),
                        'publicKey' => config('services.vapid.public_key'),
                        'privateKey' => config('services.vapid.private_key'),
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Web Push (VAPID) mal configurado — notificações push desativadas até corrigir o .env', [
                    'error' => $e->getMessage(),
                ]);

                // `@` aqui pelo mesmo motivo do try acima: sem VAPID
                // configurado, Utils::checkRequirement() ainda dispara o
                // mesmo notice de GMP/BCMath ausente — sem suprimir,
                // vira uma SEGUNDA ErrorException não capturada e o
                // fallback quebra do mesmo jeito que o caso original.
                return @new WebPush;
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nominatim (OpenStreetMap) exige no máximo 1 req/s — usado pelo
        // GeocodeEnderecoJob via middleware RateLimited('nominatim').
        RateLimiter::for('nominatim', fn () => Limit::perSecond(1));

        // Política de senha forte (criação/troca de senha) — só complexidade
        // local, sem checagem de vazamento (HIBP), decisão confirmada com o
        // usuário. LoginRequest não usa Password::defaults() (mantém
        // 'min:8'): validar formato de senha JÁ EXISTENTE com regra de
        // criação quebraria login de usuários com senha antiga mais fraca.
        Password::defaults(fn () => Password::min(12)->max(255)->mixedCase()->numbers()->symbols());
    }

    /**
     * Registrar bindings de Repositories
     *
     * Vincula interfaces aos seus repositórios concretos.
     * Permite Dependency Injection via interface.
     */
    protected function registerRepositories(): void
    {
        // User Repository
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        // Group Repository
        $this->app->bind(
            GroupRepositoryInterface::class,
            GroupRepository::class
        );

        // Functionality Repository
        $this->app->bind(
            FunctionalityRepositoryInterface::class,
            FunctionalityRepository::class
        );

        // Tenant Repository
        $this->app->bind(
            TenantRepositoryInterface::class,
            TenantRepository::class
        );

        $this->app->bind(
            PlanRepositoryInterface::class,
            PlanRepository::class
        );

        $this->app->bind(
            PlanFunctionalityRepositoryInterface::class,
            PlanFunctionalityRepository::class
        );

        // Tenant Role Repository
        $this->app->bind(
            TenantRoleRepositoryInterface::class,
            TenantRoleRepository::class
        );

        // Tenant Role Permission Repository
        $this->app->bind(
            TenantRolePermissionRepositoryInterface::class,
            TenantRolePermissionRepository::class
        );

        // Tenant User Repository
        $this->app->bind(
            TenantUserRepositoryInterface::class,
            TenantUserRepository::class
        );

        // Tenant User Invite Repository
        $this->app->bind(
            TenantUserInviteRepositoryInterface::class,
            TenantUserInviteRepository::class
        );

        // Event Category Repository
        $this->app->bind(
            EventCategoryRepositoryInterface::class,
            EventCategoryRepository::class
        );

        // Event Repository
        $this->app->bind(
            EventRepositoryInterface::class,
            EventRepository::class
        );

        // Ticket Type Repository
        $this->app->bind(
            TicketTypeRepositoryInterface::class,
            TicketTypeRepository::class
        );

        // Event Product Repository
        $this->app->bind(
            EventProductRepositoryInterface::class,
            EventProductRepository::class
        );

        // Event Session Repository
        $this->app->bind(
            EventSessionRepositoryInterface::class,
            EventSessionRepository::class
        );

        // Ticket Batch Repository
        $this->app->bind(
            TicketBatchRepositoryInterface::class,
            TicketBatchRepository::class
        );

        // Ticket Repository (ingresso digital emitido, spec 5.15)
        $this->app->bind(
            TicketRepositoryInterface::class,
            TicketRepository::class
        );

        // Ticket Checkin Repository (controle de acesso/portaria, spec 5.16)
        $this->app->bind(
            TicketCheckinRepositoryInterface::class,
            TicketCheckinRepository::class
        );

        // Sale Refund Repository (estorno externo, spec 5.14)
        $this->app->bind(
            SaleRefundRepositoryInterface::class,
            SaleRefundRepository::class
        );

        // Cash Session Repository (caixa, roadmap Fase 2)
        $this->app->bind(
            CashSessionRepositoryInterface::class,
            CashSessionRepository::class
        );

        // Venue Repository
        $this->app->bind(
            VenueRepositoryInterface::class,
            VenueRepository::class
        );

        // Seat Repository
        $this->app->bind(
            SeatRepositoryInterface::class,
            SeatRepository::class
        );

        // Sale Repository
        $this->app->bind(
            SaleRepositoryInterface::class,
            SaleRepository::class
        );

        // Audit Log Repository
        $this->app->bind(
            AuditLogRepositoryInterface::class,
            AuditLogRepository::class
        );

        // Final Customer Repository (Portal do cliente final)
        $this->app->bind(
            FinalCustomerRepositoryInterface::class,
            FinalCustomerRepository::class
        );

        // Final Customer Tenant Link Repository
        $this->app->bind(
            FinalCustomerTenantLinkRepositoryInterface::class,
            FinalCustomerTenantLinkRepository::class
        );

        // Tenant Settings Repository
        $this->app->bind(
            TenantSettingsRepositoryInterface::class,
            TenantSettingsRepository::class
        );

        // Coupon Repository (Delivery Fase 3)
        $this->app->bind(
            CouponRepositoryInterface::class,
            CouponRepository::class
        );

        // Legal Document Repository (roadmap 1A — ToS/Privacidade)
        $this->app->bind(
            LegalDocumentRepositoryInterface::class,
            LegalDocumentRepository::class
        );

        $this->app->bind(
            PrivacyRequestRepositoryInterface::class,
            PrivacyRequestRepository::class
        );

        // Release Notes (roadmap A1.6)
        $this->app->bind(
            ReleaseNoteRepositoryInterface::class,
            ReleaseNoteRepository::class
        );

        // Telemetria de carrinho da bilheteria online (roadmap A3.14)
        $this->app->bind(
            CartEventRepositoryInterface::class,
            CartEventRepository::class
        );

        // Cobrança de planos (roadmap 1B)
        $this->app->bind(
            SubscriptionRepositoryInterface::class,
            SubscriptionRepository::class
        );

        $this->app->bind(
            PlanPriceRepositoryInterface::class,
            PlanPriceRepository::class
        );

        $this->app->bind(
            InvoiceRepositoryInterface::class,
            InvoiceRepository::class
        );

        $this->app->bind(
            PaymentRepositoryInterface::class,
            PaymentRepository::class
        );

        // Provedor de pagamento (rail Clube->PegaTicket / assinatura) —
        // resolvido por config('services.payments.provider') (env
        // PAYMENT_PROVIDER, default 'manual'). 'manual' preserva o
        // comportamento no-op original (fatura fica pending até
        // conciliação manual). 'mercadopago' liga o PSP real (roadmap Fase
        // B, item 1) assim que as credenciais em .env forem preenchidas —
        // domínio (SubscriptionService/InvoicePaymentService) depende só da
        // interface, nunca do adapter concreto. Binding GLOBAL — NÃO tocar
        // aqui para o rail de venda de ingresso, ver bind contextual de
        // SalePaymentService logo abaixo.
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            return $this->resolvePaymentProvider($app, (string) config('services.payments.provider'));
        });

        // Provedor de pagamento (rail comprador->clube / venda de ingresso)
        // — SÓ para SalePaymentService, via config('services.payments.
        // sale_provider') (env SALE_PAYMENT_PROVIDER). Sem valor definido,
        // herda 'services.payments.provider' (comportamento de hoje, os
        // dois rails compartilhando o mesmo adapter). Definir
        // SALE_PAYMENT_PROVIDER=pagbank liga PagBankPaymentProvider (roadmap
        // seção 2.1) SEM afetar o binding global acima usado pela
        // assinatura.
        $this->app->when(SalePaymentService::class)
            ->needs(PaymentProviderInterface::class)
            ->give(function ($app) {
                $saleProvider = config('services.payments.sale_provider');
                $provider = $saleProvider !== null && $saleProvider !== ''
                    ? (string) $saleProvider
                    : (string) config('services.payments.provider');

                return $this->resolvePaymentProvider($app, $provider);
            });

        // Central de chamados (roadmap A4, item 17)
        $this->app->bind(
            HelpRequestRepositoryInterface::class,
            HelpRequestRepository::class
        );

        // Feature flag por tenant individual (roadmap A5, item 19)
        $this->app->bind(
            TenantFeatureOverrideRepositoryInterface::class,
            TenantFeatureOverrideRepository::class
        );

        $this->app->bind(
            IdempotencyRepositoryInterface::class,
            IdempotencyRepository::class
        );

        // Estornos (roadmap 2026-07-24, GET /subscription/refunds)
        $this->app->bind(
            RefundRepositoryInterface::class,
            RefundRepository::class
        );

    }

    /**
     * Resolve o adapter concreto de PaymentProviderInterface a partir do
     * nome de provider configurado — reaproveitado tanto pelo binding
     * global (assinatura) quanto pelo binding contextual de
     * SalePaymentService (venda de ingresso), para as duas listas de
     * providers ficarem sempre em sincronia num único lugar.
     */
    private function resolvePaymentProvider($app, string $provider): PaymentProviderInterface
    {
        return match ($provider) {
            'mercadopago' => $app->make(MercadoPagoPaymentProvider::class),
            'pagbank' => $app->make(PagBankPaymentProvider::class),
            default => $app->make(ManualPaymentProvider::class),
        };
    }
}
