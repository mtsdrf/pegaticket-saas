<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Minishlink\WebPush\WebPush;

// Repository Interfaces
use App\Repositories\Contracts\{
    UserRepositoryInterface,
    GroupRepositoryInterface,
    FunctionalityRepositoryInterface,
    TenantRepositoryInterface,
    PlanRepositoryInterface,
    PlanFunctionalityRepositoryInterface,
    TenantRoleRepositoryInterface,
    TenantRolePermissionRepositoryInterface,
    TenantUserRepositoryInterface,
    TenantUserInviteRepositoryInterface,
    ClientCategoryRepositoryInterface,
    ClientRepositoryInterface,
    ProductCategoryRepositoryInterface,
    ProductTypeRepositoryInterface,
    ProductRepositoryInterface,
    ProductCategoryPriceRepositoryInterface,
    EstadoRepositoryInterface,
    CidadeRepositoryInterface,
    BairroRepositoryInterface,
    EnderecoRepositoryInterface,
    DiaIdealRepositoryInterface,
    PeriodoIdealRepositoryInterface,
    StockLocationRepositoryInterface,
    StockBalanceRepositoryInterface,
    StockMovementRepositoryInterface,
    OrderRepositoryInterface,
    AuditLogRepositoryInterface,
    FinalCustomerRepositoryInterface,
    FinalCustomerTenantLinkRepositoryInterface,
    TenantSettingsRepositoryInterface,
    StoreBusinessHourRepositoryInterface,
    StoreDeliveryFeeRepositoryInterface,
    CouponRepositoryInterface,
    ProductPromotionRepositoryInterface,
    LegalDocumentRepositoryInterface,
    PrivacyRequestRepositoryInterface,
    SubscriptionRepositoryInterface,
    PlanPriceRepositoryInterface,
    InvoiceRepositoryInterface,
    PaymentRepositoryInterface,
    CashSessionRepositoryInterface,
    StationRepositoryInterface,
    TableRepositoryInterface,
    ComandaRepositoryInterface,
    ReleaseNoteRepositoryInterface,
    CartEventRepositoryInterface,
    UserPinRepositoryInterface,
    SupportTicketRepositoryInterface,
    ReactivationRuleRepositoryInterface,
    TenantFeatureOverrideRepositoryInterface,
    IdempotencyRepositoryInterface,
    RefundRepositoryInterface,
};

// Repository Implementations
use App\Repositories\Eloquent\{
    UserRepository,
    GroupRepository,
    FunctionalityRepository,
    TenantRepository,
    PlanRepository,
    PlanFunctionalityRepository,
    TenantRoleRepository,
    TenantRolePermissionRepository,
    TenantUserRepository,
    TenantUserInviteRepository,
    ClientCategoryRepository,
    ClientRepository,
    ProductCategoryRepository,
    ProductTypeRepository,
    ProductRepository,
    ProductCategoryPriceRepository,
    EstadoRepository,
    CidadeRepository,
    BairroRepository,
    EnderecoRepository,
    DiaIdealRepository,
    PeriodoIdealRepository,
    StockLocationRepository,
    StockBalanceRepository,
    StockMovementRepository,
    OrderRepository,
    AuditLogRepository,
    FinalCustomerRepository,
    FinalCustomerTenantLinkRepository,
    TenantSettingsRepository,
    StoreBusinessHourRepository,
    StoreDeliveryFeeRepository,
    CouponRepository,
    ProductPromotionRepository,
    LegalDocumentRepository,
    PrivacyRequestRepository,
    SubscriptionRepository,
    PlanPriceRepository,
    InvoiceRepository,
    PaymentRepository,
    CashSessionRepository,
    StationRepository,
    TableRepository,
    ComandaRepository,
    ReleaseNoteRepository,
    CartEventRepository,
    UserPinRepository,
    SupportTicketRepository,
    ReactivationRuleRepository,
    TenantFeatureOverrideRepository,
    IdempotencyRepository,
    RefundRepository,
};

// Payment provider (cobrança de planos — roadmap 1B)
use App\Contracts\Payment\PaymentProviderInterface;
use App\Services\Payment\ManualPaymentProvider;
use App\Services\Payment\MercadoPagoPaymentProvider;

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
                return @new WebPush();
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
     * 
     * @return void
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

        // Client Category Repository
        $this->app->bind(
            ClientCategoryRepositoryInterface::class,
            ClientCategoryRepository::class
        );

        // Client Repository
        $this->app->bind(
            ClientRepositoryInterface::class,
            ClientRepository::class
        );

        // Product Category Repository
        $this->app->bind(
            ProductCategoryRepositoryInterface::class,
            ProductCategoryRepository::class
        );

        // Product Type Repository
        $this->app->bind(
            ProductTypeRepositoryInterface::class,
            ProductTypeRepository::class
        );

        // Product Repository
        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        // Product Category Price Repository
        $this->app->bind(
            ProductCategoryPriceRepositoryInterface::class,
            ProductCategoryPriceRepository::class
        );

        // Estado Repository
        $this->app->bind(
            EstadoRepositoryInterface::class,
            EstadoRepository::class
        );

        // Cidade Repository
        $this->app->bind(
            CidadeRepositoryInterface::class,
            CidadeRepository::class
        );

        // Bairro Repository
        $this->app->bind(
            BairroRepositoryInterface::class,
            BairroRepository::class
        );

        // Endereco Repository
        $this->app->bind(
            EnderecoRepositoryInterface::class,
            EnderecoRepository::class
        );

        // Dia Ideal Repository
        $this->app->bind(
            DiaIdealRepositoryInterface::class,
            DiaIdealRepository::class
        );

        // Periodo Ideal Repository
        $this->app->bind(
            PeriodoIdealRepositoryInterface::class,
            PeriodoIdealRepository::class
        );

        // Stock Location Repository
        $this->app->bind(
            StockLocationRepositoryInterface::class,
            StockLocationRepository::class
        );

        // Stock Balance Repository
        $this->app->bind(
            StockBalanceRepositoryInterface::class,
            StockBalanceRepository::class
        );

        // Stock Movement Repository
        $this->app->bind(
            StockMovementRepositoryInterface::class,
            StockMovementRepository::class
        );

        // Order Repository
        $this->app->bind(
            OrderRepositoryInterface::class,
            OrderRepository::class
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

        // Store Business Hour Repository (Delivery Fase 2)
        $this->app->bind(
            StoreBusinessHourRepositoryInterface::class,
            StoreBusinessHourRepository::class
        );

        // Store Delivery Fee Repository (Delivery Fase 2)
        $this->app->bind(
            StoreDeliveryFeeRepositoryInterface::class,
            StoreDeliveryFeeRepository::class
        );

        // Coupon Repository (Delivery Fase 3)
        $this->app->bind(
            CouponRepositoryInterface::class,
            CouponRepository::class
        );

        // Product Promotion Repository (Delivery Fase 3)
        $this->app->bind(
            ProductPromotionRepositoryInterface::class,
            ProductPromotionRepository::class
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

        // Telemetria de carrinho da loja online (roadmap A3.14)
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

        // Provedor de pagamento — resolvido por config('services.payments.provider')
        // (env PAYMENT_PROVIDER, default 'manual'). 'manual' preserva o
        // comportamento no-op original (fatura/pedido fica pending até
        // conciliação manual). 'mercadopago' liga o PSP real (roadmap Fase
        // B, item 1) assim que as credenciais em .env forem preenchidas —
        // domínio (SubscriptionService/InvoiceService/OrderPaymentService)
        // depende só da interface, nunca do adapter concreto.
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            return match (config('services.payments.provider')) {
                'mercadopago' => $app->make(MercadoPagoPaymentProvider::class),
                default => $app->make(ManualPaymentProvider::class),
            };
        });

        // Caixa/PDV (roadmap PDV, Fase PDV-1)
        $this->app->bind(
            CashSessionRepositoryInterface::class,
            CashSessionRepository::class
        );

        // Balcão (roadmap Balcão, Fases 1+2)
        $this->app->bind(
            StationRepositoryInterface::class,
            StationRepository::class
        );

        // PIN de operador (roadmap A4, item 15)
        $this->app->bind(
            UserPinRepositoryInterface::class,
            UserPinRepository::class
        );

        // Central de chamados (roadmap A4, item 17)
        $this->app->bind(
            SupportTicketRepositoryInterface::class,
            SupportTicketRepository::class
        );

        // Régua de reativação de cliente (roadmap A5, item 18)
        $this->app->bind(
            ReactivationRuleRepositoryInterface::class,
            ReactivationRuleRepository::class
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

        $this->app->bind(
            TableRepositoryInterface::class,
            TableRepository::class
        );

        $this->app->bind(
            ComandaRepositoryInterface::class,
            ComandaRepository::class
        );
    }
}
