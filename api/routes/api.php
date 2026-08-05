<?php

use App\Http\Controllers\Affiliate\AffiliateController;
use App\Http\Controllers\AuditLog\AuditLogController;
use App\Http\Controllers\Auth\AcceptTenantUserInviteController;
use App\Http\Controllers\Auth\AuthAccessController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AuthTenantController;
use App\Http\Controllers\Auth\ConfirmEmailController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\SelfSignupController;
use App\Http\Controllers\CashSession\CashSessionController;
use App\Http\Controllers\Event\EventCategoryController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Event\EventGateController;
use App\Http\Controllers\Event\EventImageController;
use App\Http\Controllers\Event\EventProductController;
use App\Http\Controllers\Event\EventSessionController;
use App\Http\Controllers\Event\TicketBatchController;
use App\Http\Controllers\Event\TicketTypeController;
use App\Http\Controllers\Event\TicketTypeImageController;
use App\Http\Controllers\Event\TicketTypeWaitlistController;
use App\Http\Controllers\FinalCustomer\FinalCustomerController;
use App\Http\Controllers\Finance\AdminFinanceOperationsController;
use App\Http\Controllers\Finance\EventFinancialCloseoutController;
use App\Http\Controllers\Finance\FinanceOperationsController;
use App\Http\Controllers\Finance\PlatformFinanceSettingsController;
use App\Http\Controllers\Finance\ReconciliationController;
use App\Http\Controllers\Finance\SettlementAdjustmentController;
use App\Http\Controllers\Functionality\FunctionalityController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\GuestList\GuestInviteController;
use App\Http\Controllers\GuestList\GuestListController;
use App\Http\Controllers\Health\HealthController;
use App\Http\Controllers\Legal\LegalDocumentController;
use App\Http\Controllers\Legal\ReleaseNoteController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Payment\PaymentIssueController;
use App\Http\Controllers\Plan\PlanController;
use App\Http\Controllers\Plan\PlanFunctionalityController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalCouponController;
use App\Http\Controllers\Portal\PortalFavoriteController;
use App\Http\Controllers\Portal\PortalLinkController;
use App\Http\Controllers\Portal\PushSubscriptionController;
use App\Http\Controllers\Privacy\PrivacyRequestController;
use App\Http\Controllers\Report\AnalyticsController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Sale\SaleController;
use App\Http\Controllers\Sale\SaleInstallmentController;
use App\Http\Controllers\Sale\SaleRefundController;
use App\Http\Controllers\Sale\SaleTrackingController;
use App\Http\Controllers\Storefront\CartEventController;
use App\Http\Controllers\Storefront\CouponController;
use App\Http\Controllers\Storefront\StorefrontCheckoutController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\StorefrontHoldController;
use App\Http\Controllers\Storefront\StorefrontManifestController;
use App\Http\Controllers\Storefront\StorefrontTicketWaitlistController;
use App\Http\Controllers\Subscription\PaymentWebhookController;
use App\Http\Controllers\Subscription\RefundController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\Support\HelpRequestController;
use App\Http\Controllers\Tenant\TenantController;
use App\Http\Controllers\Tenant\TenantDataExportController;
use App\Http\Controllers\Tenant\TenantFeatureOverrideController;
use App\Http\Controllers\Tenant\TenantLogoController;
use App\Http\Controllers\Tenant\TenantProfileController;
use App\Http\Controllers\Tenant\TenantRoleController;
use App\Http\Controllers\Tenant\TenantRolePermissionController;
use App\Http\Controllers\Tenant\TenantUserController;
use App\Http\Controllers\Tenant\TenantUserInviteController;
use App\Http\Controllers\TenantSettings\TenantSettingsController;
use App\Http\Controllers\Ticket\TicketController;
use App\Http\Controllers\User\UserAvatarController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Venue\SeatController;
use App\Http\Controllers\Venue\VenueController;
use App\Http\Controllers\Venue\VenueImageController;
use App\Http\Controllers\Workflow\WorkflowTransitionLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Monitoramento externo (roadmap A1.1) — 100% público, sem jwt/tenant/
    // perm, checa banco/storage/fila. Ver App\Services\Health\HealthCheckService.
    Route::get('/health', [HealthController::class, 'show'])
        ->middleware('throttle:60,1,health-check');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1,login');

    Route::post('/auth/refresh', [RefreshTokenController::class, 'refresh'])
        ->middleware('throttle:10,1,refresh');

    Route::get('/auth/signup/plans', [SelfSignupController::class, 'plans'])
        ->middleware('throttle:30,1,signup-plans');

    Route::post('/auth/signup', [SelfSignupController::class, 'store'])
        ->middleware('throttle:5,1,signup');

    Route::post('/auth/accept-invite', [AcceptTenantUserInviteController::class, 'store'])
        ->middleware('throttle:10,1,accept-invite');

    // "Meus dados" (auto-serviço) — confirmação de troca de e-mail. Rota
    // pública (fora de jwt) porque o usuário pode clicar o link de um
    // dispositivo sem sessão ativa, mesmo padrão de /auth/accept-invite.
    // Ver App\Http\Controllers\Auth\ConfirmEmailController.
    Route::post('/auth/confirm-email', [ConfirmEmailController::class, 'store'])
        ->middleware('throttle:10,1,confirm-email');

    // Esqueci a senha (staff User) — 2 rotas públicas, mesmo espírito de
    // /auth/confirm-email. forgotPassword nunca revela se o e-mail existe
    // (evita user enumeration); throttle baixo por ser rota pública
    // sensível a abuso. Ver App\Services\Auth\PasswordResetService.
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1,auth-forgot-password');

    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1,auth-reset-password');

    // Termos de Uso / Política de Privacidade (roadmap 1A) — 100% público
    // (o texto legal precisa ser lido antes do cadastro, sem sessão).
    // Retorna a versão ativa vigente do tipo. Ver LegalDocumentController.
    Route::get('/legal-documents/{type}', [LegalDocumentController::class, 'show'])
        ->where('type', 'terms|privacy')
        ->middleware('throttle:60,1,legal-documents-show');

    // Webhook de pagamento (roadmap 1B) — 100% público (fora de jwt/tenant/
    // perm): um PSP real chamaria aqui. Sem provedor real ainda, responde
    // sempre 501, mas já aplica idempotência por (provider, external_id).
    // Ver PaymentWebhookController.
    Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:120,1,payments-webhook');

    // Acompanhamento público da venda (roadmap 5.1) — 100% público, sem
    // jwt/tenant/perm, protegido só pelo uuid da venda ser imprevisível
    // (link enviado por WhatsApp na criação da venda). Ver
    // App\Http\Controllers\Sale\SaleTrackingController.
    Route::get('/rastreio/{sale:uuid}', [SaleTrackingController::class, 'show'])
        ->middleware('throttle:60,1,sale-tracking-public');

    // Autoatendimento de convite/cortesia (roadmap Fase 4) — 100% público,
    // protegido pelo token individual imprevisível (mesmo padrão de
    // rastreio público acima). Ver App\Services\GuestList\GuestListService.
    Route::get('/convites/{token}', [GuestInviteController::class, 'show'])
        ->middleware('throttle:60,1,guest-invite-show');

    Route::post('/convites/{token}/resgatar', [GuestInviteController::class, 'redeem'])
        ->middleware('throttle:20,1,guest-invite-redeem');

    // Imagens guardadas em BLOB no banco (avatar/produto/logo) — antes eram
    // arquivo estático em /storage/*, sem passar por middleware nenhum;
    // agora são lidas do banco e servidas por rota de API, 100% pública
    // (mesma exposição de sempre, só sem depender de disco/symlink na
    // hospedagem compartilhada). Efeito colateral bom: por estarem sob
    // /api/v1, passam pelo HandleCors normal do Laravel, diferente do
    // arquivo estático de antes. Ver architecture-decisions.md.
    Route::get('/users/{user}/avatar', [UserAvatarController::class, 'show'])
        ->middleware('throttle:100,1,users-avatar-show');

    Route::get('/events/{event}/image', [EventImageController::class, 'show'])
        ->middleware('throttle:100,1,events-image-show');

    Route::get('/ticket-types/{ticketType}/image', [TicketTypeImageController::class, 'show'])
        ->middleware('throttle:100,1,ticket-types-image-show');

    Route::get('/venues/{venue}/image', [VenueImageController::class, 'show'])
        ->middleware('throttle:100,1,venues-image-show');

    Route::get('/tenants/{tenant}/logo', [TenantLogoController::class, 'show'])
        ->middleware('throttle:100,1,tenants-logo-show');

    // Loja pública do tenant (Delivery Fase 1) — 100% público, sem
    // jwt/tenant/perm; StorefrontCatalogService resolve o tenant por slug
    // e checa a functionality 'storefront' do plano (404 se ausente, nunca
    // revela que o tenant existe sem o plano). Ver
    // App\Services\Storefront\StorefrontCatalogService.
    Route::get('/loja/{slug}', [StorefrontController::class, 'show'])
        ->middleware('throttle:100,1,storefront-show');

    // customer.jwt.optional (roadmap Delivery, Fase 4 — retenção): rota
    // continua 100% pública, mas quando o cliente final está autenticado o
    // catálogo já vem com is_favorited calculado, sem round-trip extra.
    // Nunca exige login (nunca 401), ver
    // App\Http\Middleware\OptionalCustomerJwtMiddleware.
    Route::get('/loja/{slug}/eventos', [StorefrontController::class, 'events'])
        ->middleware(['customer.jwt.optional', 'throttle:100,1,storefront-products']);

    // Detalhe público de um evento, com ticket_types/event_products
    // aninhados (NOVO — não existia equivalente no catálogo de comércio).
    Route::get('/loja/{slug}/eventos/{eventSlug}', [StorefrontController::class, 'event'])
        ->middleware('throttle:100,1,storefront-event-show');

    Route::get('/loja/{slug}/eventos/{eventSlug}/disponibilidade', [StorefrontHoldController::class, 'availability'])
        ->middleware('throttle:100,1,storefront-event-availability');

    // Fila virtual para alta demanda (roadmap Fase 7) — só relevante para
    // eventos com high_demand_mode=true (ver App\Services\Storefront\
    // VirtualQueueService); polling do frontend, mesmo espírito de
    // throttle generoso de disponibilidade.
    Route::get('/loja/{slug}/eventos/{eventSlug}/fila', [StorefrontHoldController::class, 'queueStatus'])
        ->middleware(['customer.jwt.optional', 'throttle:100,1,storefront-event-queue-status']);

    Route::post('/loja/{slug}/eventos/{eventSlug}/holds', [StorefrontHoldController::class, 'store'])
        ->middleware(['customer.jwt.optional', 'throttle:60,1,storefront-holds-create']);

    Route::get('/loja/{slug}/holds/{holdUuid}', [StorefrontHoldController::class, 'show'])
        ->middleware(['customer.jwt.optional', 'throttle:100,1,storefront-holds-show']);

    Route::post('/loja/{slug}/holds/{holdUuid}/renovar', [StorefrontHoldController::class, 'renew'])
        ->middleware(['customer.jwt.optional', 'throttle:60,1,storefront-holds-renew']);

    Route::delete('/loja/{slug}/holds/{holdUuid}', [StorefrontHoldController::class, 'destroy'])
        ->middleware(['customer.jwt.optional', 'throttle:60,1,storefront-holds-destroy']);

    // Categorias com evento disponível (vitrine) — mesmo espírito de
    // /loja/{slug}/eventos.
    Route::get('/loja/{slug}/categorias', [StorefrontController::class, 'categories'])
        ->middleware('throttle:100,1,storefront-categories');

    Route::get('/loja/{slug}/manifest.webmanifest', [StorefrontManifestController::class, 'show'])
        ->middleware('throttle:100,1,storefront-manifest');

    // Prévia pública de cupom — o frontend chama ao digitar o código no
    // checkout, antes do OTP/identificação do cliente final. Não consome
    // CouponRedemption (só StorefrontCheckoutService::checkout() consome,
    // no submit final). Ver
    // App\Http\Controllers\Storefront\StorefrontController::validateCoupon().
    Route::post('/loja/{slug}/cupons/validar', [StorefrontController::class, 'validateCoupon'])
        ->middleware('throttle:100,1,storefront-coupon-validate');

    // Telemetria de abandono de carrinho (roadmap A3.14) — 100% público,
    // mesmo espírito de /loja/{slug}/cupons/validar. Captura client-side no
    // checkout da loja; sem tela de leitura ainda, só o registro. Ver
    // App\Http\Controllers\Storefront\CartEventController.
    Route::post('/loja/{slug}/eventos-carrinho', [CartEventController::class, 'store'])
        ->middleware('throttle:60,1,storefront-cart-events');

    // Lista de espera de TicketType esgotado (roadmap inventário) — 100%
    // público, mesmo espírito de /loja/{slug}/eventos-carrinho. Anti-bot
    // via App\Services\Security\AntiBotGuardService (honeypot + tempo
    // mínimo). Ver App\Services\TicketTypeWaitlist\TicketTypeWaitlistService.
    Route::post('/loja/{slug}/lista-espera', [StorefrontTicketWaitlistController::class, 'store'])
        ->middleware('throttle:30,1,storefront-ticket-waitlist-create');
    // Portal do cliente final (roadmap 5.2) — login sem senha por OTP de
    // e-mail. Identidade própria (App\Models\FinalCustomer\FinalCustomer),
    // NÃO é App\Models\User\User (staff). Nunca revela se o e-mail já tinha
    // conta (resposta genérica sempre em request-otp).
    Route::post('/portal/auth/request-otp', [PortalAuthController::class, 'requestOtp'])
        ->middleware('throttle:5,1,portal-request-otp');

    Route::post('/portal/auth/verify-otp', [PortalAuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1,portal-verify-otp');

    // Autenticado como FinalCustomer via `customer.jwt` — nunca `jwt`/
    // `tenant`/`perm` (esse cliente não pertence a nenhum tenant, o
    // conceito de permissão não se aplica). Ver
    // App\Http\Middleware\CustomerJwtAccessMiddleware.
    Route::middleware(['customer.jwt'])->prefix('portal')->group(function () {

        Route::post('/links', [PortalLinkController::class, 'store'])
            ->middleware('throttle:30,1,portal-links-create');

        Route::get('/sales', [PortalController::class, 'sales'])
            ->middleware('throttle:60,1,portal-sales-list');

        Route::get('/me', [PortalController::class, 'me'])
            ->middleware('throttle:60,1,portal-me');

        // Favoritos de evento. Toggle idempotente: favorito existente
        // remove, inexistente cria.
        Route::post('/favorites/{event_uuid}/toggle', [PortalFavoriteController::class, 'toggle'])
            ->middleware('throttle:60,1,portal-favorites-toggle');

        Route::get('/favorites', [PortalFavoriteController::class, 'index'])
            ->middleware('throttle:60,1,portal-favorites-list');

        // "Comprar novamente" — reaproveita os itens de uma compra
        // anterior com preço/disponibilidade ATUAIS.
        Route::get('/sales/{uuid}/items', [PortalController::class, 'saleItems'])
            ->middleware('throttle:60,1,portal-sale-items');

        // "Meus ingressos" da compra (spec 5.15, área "Meus ingressos") —
        // endpoint próprio por compra em vez de listagem achatada global,
        // ver App\Http\Controllers\Portal\PortalController::saleTickets().
        Route::get('/sales/{uuid}/tickets', [PortalController::class, 'saleTickets'])
            ->middleware('throttle:60,1,portal-sale-tickets');

        // Titularidade e transferência (roadmap Fase 4) — troca o
        // participante do ingresso e rotaciona code/qr_token.
        Route::post('/tickets/{uuid}/transfer', [PortalController::class, 'transferTicket'])
            ->middleware('throttle:20,1,portal-tickets-transfer');

        // Avaliação de compra concluída — 1 avaliação por venda.
        Route::post('/sales/{uuid}/rating', [PortalController::class, 'rate'])
            ->middleware('throttle:30,1,portal-sales-rate');

        // Solicitação de cancelamento — só venda do canal público, apenas
        // enquanto ainda estiver em operação. Não executa efeitos
        // financeiros imediatamente, ver SaleService::requestCancellation().
        Route::post('/sales/{uuid}/request-cancellation', [PortalController::class, 'requestCancellation'])
            ->middleware('throttle:30,1,portal-sales-request-cancellation');

        // Cobrança Pix da própria venda (roadmap Fase B, item 1). Reaproveita
        // SalePaymentService (mesma regra
        // de negócio do endpoint de staff), posse verificada via
        // PortalCustomerService::findOwnedOrder(), ver PortalController.
        Route::get('/sales/{uuid}/payment-checkout-config', [PortalController::class, 'paymentCheckoutConfig'])
            ->middleware('throttle:20,1,portal-sales-payment-checkout-config');

        Route::post('/sales/{uuid}/payment-charge', [PortalController::class, 'paymentCharge'])
            ->middleware('throttle:20,1,portal-sales-payment-charge');

        // Web Push (VAPID) real (roadmap Delivery, Fase 4 — última fatia).
        // Ver App\Services\Storefront\PushSubscriptionService::subscribe().
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
            ->middleware('throttle:30,1,portal-push-subscriptions-create');

        // "Meus vouchers" = histórico de cupons já usados (read-only).
        Route::get('/coupon-redemptions', [PortalCouponController::class, 'index'])
            ->middleware('throttle:60,1,portal-coupon-redemptions-list');
    });

    // Checkout da loja (Delivery Fase 1) — autenticado como FinalCustomer
    // via customer.jwt, mas rota própria fora do prefixo /portal: é
    // escopada por slug de tenant (loja), não pela identidade global do
    // portal. Ver App\Services\Storefront\StorefrontCheckoutService.
    Route::post('/loja/{slug}/checkout', [StorefrontCheckoutController::class, 'store'])
        ->middleware(['customer.jwt', 'throttle:20,1,storefront-checkout']);
    Route::post('/bilheteria/{slug}/checkout', [StorefrontCheckoutController::class, 'store'])
        ->middleware(['customer.jwt', 'throttle:20,1,storefront-checkout']);

    Route::middleware(['jwt'])->group(function () {

        // Novidades da plataforma (roadmap A1.6) — qualquer usuário
        // autenticado, sem perm dedicada (conteúdo informativo, não CRUD).
        Route::get('/release-notes', [ReleaseNoteController::class, 'index'])
            ->middleware('throttle:60,1,release-notes-list');

        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->middleware('throttle:10,1,logout');

        Route::get('/auth/my-tenants', [AuthTenantController::class, 'myTenants'])
            ->middleware('throttle:50,1');

        Route::post('/auth/switch-tenant', [AuthTenantController::class, 'switchTenant'])
            ->middleware('throttle:20,1');

        Route::get('/auth/access-profile', [AuthAccessController::class, 'show'])
            ->middleware('throttle:60,1,auth-access-profile');

        // "Meus dados" (auto-serviço) — o usuário STAFF logado edita A SI
        // MESMO (nome/foto/senha/e-mail). Sem tenant/perm: mesmo espírito
        // de /auth/access-profile, só exige estar autenticado. Não
        // confundir com o CRUD admin /users (gerencia OUTROS usuários).
        Route::prefix('auth/profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])
                ->middleware('throttle:60,1,auth-profile-show');

            Route::put('/', [ProfileController::class, 'update'])
                ->middleware('throttle:30,1,auth-profile-update');

            Route::post('/password', [ProfileController::class, 'changePassword'])
                ->middleware('throttle:10,1,auth-profile-password');

            Route::post('/email', [ProfileController::class, 'requestEmailChange'])
                ->middleware('throttle:10,1,auth-profile-email');
        });

        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index'])
                ->middleware(['tenant', 'perm:users,read', 'throttle:100,1,users-list']);

            Route::post('/', [UserController::class, 'store'])
                ->middleware(['tenant', 'perm:users,create', 'throttle:30,1,users-create']);

            Route::get('/{user}', [UserController::class, 'show'])
                ->middleware(['tenant', 'perm:users,read', 'throttle:100,1,users-show']);

            Route::put('/{user}', [UserController::class, 'update'])
                ->middleware(['tenant', 'perm:users,update', 'throttle:30,1,users-update']);

            Route::delete('/{user}', [UserController::class, 'destroy'])
                ->middleware(['tenant', 'perm:users,delete', 'throttle:10,1,users-delete']);
        });

        Route::prefix('groups')->group(function () {
            Route::get('/', [GroupController::class, 'index'])
                ->middleware(['perm:groups,read', 'throttle:100,1,groups-list']);

            Route::post('/', [GroupController::class, 'store'])
                ->middleware(['perm:groups,create', 'throttle:30,1,groups-create']);

            Route::get('/{group}', [GroupController::class, 'show'])
                ->middleware(['perm:groups,read', 'throttle:100,1,groups-show']);

            Route::put('/{group}', [GroupController::class, 'update'])
                ->middleware(['perm:groups,update', 'throttle:30,1,groups-update']);

            Route::delete('/{group}', [GroupController::class, 'destroy'])
                ->middleware(['perm:groups,delete', 'throttle:10,1,groups-delete']);

            Route::post('/{group}/users/sync', [GroupController::class, 'syncUsers'])
                ->middleware(['perm:groups,update', 'throttle:20,1,groups-sync-users']);

            Route::post('/{group}/permissions/sync', [GroupController::class, 'syncPermissions'])
                ->middleware(['perm:groups,update', 'throttle:20,1,groups-sync-permissions']);
        });

        Route::prefix('functionalities')->group(function () {
            Route::get('/', [FunctionalityController::class, 'index'])
                ->middleware(['perm:functionalities,read', 'throttle:100,1,functionalities-list']);

            Route::post('/', [FunctionalityController::class, 'store'])
                ->middleware(['perm:functionalities,create', 'throttle:30,1,functionalities-create']);

            Route::get('/{functionality}', [FunctionalityController::class, 'show'])
                ->middleware(['perm:functionalities,read', 'throttle:100,1,functionalities-show']);

            Route::put('/{functionality}', [FunctionalityController::class, 'update'])
                ->middleware(['perm:functionalities,update', 'throttle:30,1,functionalities-update']);

            Route::delete('/{functionality}', [FunctionalityController::class, 'destroy'])
                ->middleware(['perm:functionalities,delete', 'throttle:10,1,functionalities-delete']);
        });

        // Auditoria (roadmap 2.7) — exclusiva de admin da plataforma, sem
        // middleware tenant: audit_logs é tabela global, cobre mutações
        // tenant-scoped e globais misturadas, sem tenant_id pra filtrar.
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index'])
                ->middleware(['perm:audit_logs,read', 'throttle:100,1,audit-logs-list']);
        });

        // Painel de pendências de pagamento/assinatura (roadmap 2026-07-24)
        // — visão cross-tenant EXCLUSIVA do staff interno da PegaTicket, mesmo
        // padrão de `tenants`/`plans`/`audit-logs`: sem middleware `tenant`
        // (perm resolvida só por `group_permissions`, nunca por
        // `tenant_role_permissions` — não há tenant resolvido aqui).
        // Functionality própria `payment_admin` (não reaproveita `finance`
        // de propósito: `finance` é tenant-facing e pode ser atribuída a um
        // tenant role, o que vazaria esta visão cross-tenant para um
        // tenant comum caso reaproveitada).
        Route::prefix('payments')->group(function () {
            Route::get('/issues', [PaymentIssueController::class, 'index'])
                ->middleware(['perm:payment_admin,read', 'throttle:60,1,payment-admin-issues-list']);

            Route::post('/issues/{reference}/reprocess', [PaymentIssueController::class, 'reprocess'])
                ->middleware(['perm:payment_admin,update', 'throttle:20,1,payment-admin-issues-reprocess']);
        });

        Route::prefix('finance/platform-settings')->group(function () {
            Route::get('/', [PlatformFinanceSettingsController::class, 'show'])
                ->middleware(['perm:payment_admin,read', 'throttle:60,1,finance-platform-settings-show']);

            Route::put('/', [PlatformFinanceSettingsController::class, 'update'])
                ->middleware(['perm:payment_admin,update', 'throttle:20,1,finance-platform-settings-update']);
        });

        Route::prefix('finance/admin')->group(function () {
            Route::get('/dashboard', [AdminFinanceOperationsController::class, 'dashboard'])
                ->middleware(['perm:payment_admin,read', 'throttle:60,1,finance-admin-dashboard']);

            Route::get('/receivables', [AdminFinanceOperationsController::class, 'receivables'])
                ->middleware(['perm:payment_admin,read', 'throttle:60,1,finance-admin-receivables']);

            Route::get('/settlements', [AdminFinanceOperationsController::class, 'settlements'])
                ->middleware(['perm:payment_admin,read', 'throttle:60,1,finance-admin-settlements']);

            Route::get('/adjustments', [AdminFinanceOperationsController::class, 'adjustments'])
                ->middleware(['perm:payment_admin,read', 'throttle:60,1,finance-admin-adjustments']);
        });

        Route::prefix('tenants')->group(function () {
            Route::get('/', [TenantController::class, 'index'])
                ->middleware(['perm:tenants,read', 'throttle:100,1,tenants-list']);

            Route::post('/', [TenantController::class, 'store'])
                ->middleware(['perm:tenants,create', 'throttle:30,1,tenants-create']);

            Route::put('/{tenant}', [TenantController::class, 'update'])
                ->middleware(['perm:tenants,update', 'throttle:30,1,tenants-update']);

            Route::delete('/{tenant}', [TenantController::class, 'destroy'])
                ->middleware(['perm:tenants,delete', 'throttle:10,1,tenants-delete']);

            // Feature flag por tenant individual (roadmap A5, item 19) —
            // exclusivo de admin da plataforma, reaproveita perm:tenants
            // (sem Functionality/Action nova). Sem tela dedicada ainda.
            Route::get('/{tenant}/feature-overrides', [TenantFeatureOverrideController::class, 'index'])
                ->middleware(['perm:tenants,read', 'throttle:100,1,tenant-feature-overrides-list']);

            Route::post('/{tenant}/feature-overrides/sync', [TenantFeatureOverrideController::class, 'sync'])
                ->middleware(['perm:tenants,update', 'throttle:20,1,tenant-feature-overrides-sync']);
        });

        Route::prefix('plans')->group(function () {
            Route::get('/', [PlanController::class, 'index'])
                ->middleware(['perm:plans,read', 'throttle:100,1,plans-list']);

            Route::post('/', [PlanController::class, 'store'])
                ->middleware(['perm:plans,create', 'throttle:30,1,plans-create']);

            Route::get('/{plan}', [PlanController::class, 'show'])
                ->middleware(['perm:plans,read', 'throttle:100,1,plans-show']);

            Route::put('/{plan}', [PlanController::class, 'update'])
                ->middleware(['perm:plans,update', 'throttle:30,1,plans-update']);

            Route::delete('/{plan}', [PlanController::class, 'destroy'])
                ->middleware(['perm:plans,delete', 'throttle:10,1,plans-delete']);

            Route::get('/{plan}/functionalities', [PlanFunctionalityController::class, 'index'])
                ->middleware(['perm:plans,read', 'throttle:100,1,plan-functionalities-list']);

            Route::post('/{plan}/functionalities/sync', [PlanFunctionalityController::class, 'sync'])
                ->middleware(['perm:plans,update', 'throttle:20,1,plan-functionalities-sync']);
        });

        // CRUD de novidades da plataforma (roadmap A1.6) — restrito a
        // administradores, mesmo padrão global de plans/functionalities.
        // Leitura (GET /release-notes) fica fora deste grupo, liberada pra
        // qualquer usuário autenticado (ver bloco jwt acima).
        Route::prefix('release-notes')->group(function () {
            Route::post('/', [ReleaseNoteController::class, 'store'])
                ->middleware(['perm:release_notes,create', 'throttle:30,1,release-notes-create']);

            Route::put('/{releaseNote}', [ReleaseNoteController::class, 'update'])
                ->middleware(['perm:release_notes,update', 'throttle:30,1,release-notes-update']);

            Route::delete('/{releaseNote}', [ReleaseNoteController::class, 'destroy'])
                ->middleware(['perm:release_notes,delete', 'throttle:10,1,release-notes-delete']);
        });

        Route::prefix('tenant-roles')->group(function () {
            Route::get('/', [TenantRoleController::class, 'index'])
                ->middleware(['tenant', 'perm:tenant_roles,read', 'throttle:100,1,tenant-roles-list']);

            Route::get('/functionalities', [TenantRoleController::class, 'availableFunctionalities'])
                ->middleware(['tenant', 'perm:tenant_roles,read', 'throttle:100,1,tenant-roles-functionalities']);

            Route::post('/', [TenantRoleController::class, 'store'])
                ->middleware(['tenant', 'perm:tenant_roles,create', 'throttle:30,1,tenant-roles-create']);

            Route::put('/{tenantRole}', [TenantRoleController::class, 'update'])
                ->middleware(['tenant', 'perm:tenant_roles,update', 'throttle:30,1,tenant-roles-update']);

            Route::delete('/{tenantRole}', [TenantRoleController::class, 'destroy'])
                ->middleware(['tenant', 'perm:tenant_roles,delete', 'throttle:10,1,tenant-roles-delete']);

            Route::get('/{tenantRole}/permissions', [TenantRolePermissionController::class, 'index'])
                ->middleware(['tenant', 'perm:tenant_roles,read', 'throttle:100,1,tenant-role-permissions-list']);

            Route::post('/{tenantRole}/permissions/sync', [TenantRolePermissionController::class, 'sync'])
                ->middleware(['tenant', 'perm:tenant_roles,update', 'throttle:20,1,tenant-role-permissions-sync']);
        });

        Route::prefix('tenant-users')->group(function () {
            Route::get('/', [TenantUserController::class, 'index'])
                ->middleware(['tenant', 'perm:tenant_users,read', 'throttle:100,1']);

            Route::post('/', [TenantUserController::class, 'store'])
                ->middleware(['tenant', 'perm:tenant_users,create', 'throttle:30,1']);

            Route::put('/{tenantUser}', [TenantUserController::class, 'update'])
                ->middleware(['tenant', 'perm:tenant_users,update', 'throttle:30,1']);

            Route::delete('/{tenantUser}', [TenantUserController::class, 'destroy'])
                ->middleware(['tenant', 'perm:tenant_users,delete', 'throttle:10,1']);

            // Convite de usuário novo (ainda sem conta) para a tenant ativa
            // por e-mail — distinto do store() acima, que só vincula um
            // User já existente. Mesma permissão (tenant_users,create).
            Route::post('/invite', [TenantUserInviteController::class, 'store'])
                ->middleware(['tenant', 'perm:tenant_users,create', 'throttle:10,1,tenant-users-invite']);
        });

        Route::prefix('event-categories')->group(function () {
            Route::get('/', [EventCategoryController::class, 'index'])
                ->middleware(['tenant', 'perm:event_categories,read', 'throttle:100,1,event-categories-list']);

            Route::post('/', [EventCategoryController::class, 'store'])
                ->middleware(['tenant', 'perm:event_categories,create', 'throttle:30,1,event-categories-create']);

            Route::put('/{eventCategory}', [EventCategoryController::class, 'update'])
                ->middleware(['tenant', 'perm:event_categories,update', 'throttle:30,1,event-categories-update']);

            Route::delete('/{eventCategory}', [EventCategoryController::class, 'destroy'])
                ->middleware(['tenant', 'perm:event_categories,delete', 'throttle:10,1,event-categories-delete']);
        });

        Route::prefix('tenant-settings')->group(function () {
            Route::get('/', [TenantSettingsController::class, 'show'])
                ->middleware(['tenant', 'perm:tenant_settings,read', 'throttle:100,1,tenant-settings-show']);

            Route::put('/', [TenantSettingsController::class, 'update'])
                ->middleware(['tenant', 'perm:tenant_settings,update', 'throttle:30,1,tenant-settings-update']);
        });

        // Perfil da própria empresa editável pelo dono (nome + logo apenas).
        // slug/plano/status ficam exclusivos do admin da plataforma (TenantController).
        // Checklist de implantação (roadmap A2, dores #4/#15) — qualquer
        // usuário autenticado do tenant pode ver o próprio progresso, sem
        // perm dedicada (informativo, não CRUD; mesmo espírito de
        // /release-notes). Ver App\Services\Onboarding\OnboardingService.
        Route::prefix('onboarding')->middleware('tenant')->group(function () {
            Route::get('/checklist', [OnboardingController::class, 'checklist'])
                ->middleware('throttle:60,1,onboarding-checklist');

            Route::post('/checklist/dismiss', [OnboardingController::class, 'dismiss'])
                ->middleware('throttle:20,1,onboarding-checklist-dismiss');

            Route::delete('/checklist/dismiss', [OnboardingController::class, 'restore'])
                ->middleware('throttle:20,1,onboarding-checklist-restore');
        });

        Route::prefix('tenant-profile')->middleware('tenant')->group(function () {
            Route::get('/', [TenantProfileController::class, 'show'])
                ->middleware(['perm:tenant-profile,read', 'throttle:60,1,tenant-profile-show']);

            Route::put('/', [TenantProfileController::class, 'update'])
                ->middleware(['perm:tenant-profile,update', 'throttle:20,1,tenant-profile-update']);

            Route::get('/privacy-requests', [PrivacyRequestController::class, 'index'])
                ->middleware(['perm:tenant-profile,read', 'throttle:60,1,privacy-requests-list']);

            Route::post('/privacy-requests', [PrivacyRequestController::class, 'store'])
                ->middleware(['perm:tenant-profile,update', 'throttle:20,1,privacy-requests-store']);

            Route::put('/privacy-requests/{uuid}', [PrivacyRequestController::class, 'update'])
                ->middleware(['perm:tenant-profile,update', 'throttle:20,1,privacy-requests-update']);
        });

        // Exportar meus dados (roadmap A1.2) — ZIP com 1 CSV por entidade
        // principal do tenant. Operação pesada (várias queries + zip em
        // memória) — throttle mais baixo que os outros de tenant-profile.
        Route::post('/tenant-data-export', [TenantDataExportController::class, 'store'])
            ->middleware(['tenant', 'perm:tenant-profile,export', 'throttle:3,60,tenant-data-export']);

        // Cupons de desconto sobre o carrinho todo — CRUD completo.
        // Ver App\Services\Storefront\CouponService.
        Route::prefix('coupons')->group(function () {
            Route::get('/', [CouponController::class, 'index'])
                ->middleware(['tenant', 'perm:storefront,read', 'throttle:100,1,coupons-list']);

            Route::post('/', [CouponController::class, 'store'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,coupons-create']);

            Route::put('/{uuid}', [CouponController::class, 'update'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,coupons-update']);

            Route::delete('/{uuid}', [CouponController::class, 'destroy'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:10,1,coupons-delete']);
        });

        Route::prefix('events')->group(function () {
            Route::get('/', [EventController::class, 'index'])
                ->middleware(['tenant', 'perm:events,read', 'throttle:100,1,events-list']);

            Route::post('/', [EventController::class, 'store'])
                ->middleware(['tenant', 'perm:events,create', 'throttle:30,1,events-create']);

            Route::get('/{event}', [EventController::class, 'show'])
                ->middleware(['tenant', 'perm:events,read', 'throttle:100,1,events-show']);

            Route::put('/{event}', [EventController::class, 'update'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-update']);

            Route::post('/{event}/publish', [EventController::class, 'publish'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-publish']);

            Route::post('/{event}/pause-sales', [EventController::class, 'pauseSales'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-pause-sales']);

            Route::post('/{event}/resume-sales', [EventController::class, 'resumeSales'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-resume-sales']);

            Route::post('/{event}/close-sales', [EventController::class, 'closeSales'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-close-sales']);

            Route::post('/{event}/cancel', [EventController::class, 'cancel'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-cancel']);

            Route::post('/{event}/archive', [EventController::class, 'archive'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,events-archive']);

            Route::delete('/{event}', [EventController::class, 'destroy'])
                ->middleware(['tenant', 'perm:events,delete', 'throttle:10,1,events-delete']);

            Route::get('/{event}/finance/closeout', [EventFinancialCloseoutController::class, 'show'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,event-finance-closeout']);

            Route::get('/{event}/finance/bordereau', [EventFinancialCloseoutController::class, 'bordereau'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:20,1,event-finance-bordereau']);
        });

        // "Cortesias estruturadas" (roadmap Fase 4) — reaproveita a
        // permissão de events (mesmo escopo de quem gerencia o evento),
        // sem criar Functionality própria. Ver
        // App\Services\GuestList\GuestListService.
        Route::prefix('guest-lists')->group(function () {
            Route::get('/', [GuestListController::class, 'index'])
                ->middleware(['tenant', 'perm:events,read', 'throttle:100,1,guest-lists-list']);

            Route::post('/', [GuestListController::class, 'store'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:30,1,guest-lists-create']);

            Route::get('/{uuid}', [GuestListController::class, 'show'])
                ->middleware(['tenant', 'perm:events,read', 'throttle:100,1,guest-lists-show']);

            Route::post('/{uuid}/entries', [GuestListController::class, 'addEntry'])
                ->middleware(['tenant', 'perm:events,update', 'throttle:60,1,guest-lists-add-entry']);
        });

        // Afiliados/promotores (roadmap Fase 6, fatia 1) — link rastreável,
        // atribuição de venda e comissão. Ver App\Services\Affiliate.
        Route::prefix('affiliates')->group(function () {
            Route::get('/', [AffiliateController::class, 'index'])
                ->middleware(['tenant', 'perm:affiliates,read', 'throttle:100,1,affiliates-list']);

            Route::post('/', [AffiliateController::class, 'store'])
                ->middleware(['tenant', 'perm:affiliates,create', 'throttle:30,1,affiliates-create']);

            Route::get('/{uuid}', [AffiliateController::class, 'show'])
                ->middleware(['tenant', 'perm:affiliates,read', 'throttle:100,1,affiliates-show']);

            Route::put('/{uuid}', [AffiliateController::class, 'update'])
                ->middleware(['tenant', 'perm:affiliates,update', 'throttle:30,1,affiliates-update']);

            Route::get('/{uuid}/commissions', [AffiliateController::class, 'commissions'])
                ->middleware(['tenant', 'perm:affiliates,read', 'throttle:100,1,affiliates-commissions']);
        });

        Route::prefix('events/{event}/sessions')->group(function () {
            Route::get('/', [EventSessionController::class, 'index'])
                ->middleware(['tenant', 'perm:event_sessions,read', 'throttle:100,1,event-sessions-list']);

            Route::post('/', [EventSessionController::class, 'store'])
                ->middleware(['tenant', 'perm:event_sessions,create', 'throttle:30,1,event-sessions-create']);

            Route::get('/{session}', [EventSessionController::class, 'show'])
                ->middleware(['tenant', 'perm:event_sessions,read', 'throttle:100,1,event-sessions-show']);

            Route::put('/{session}', [EventSessionController::class, 'update'])
                ->middleware(['tenant', 'perm:event_sessions,update', 'throttle:30,1,event-sessions-update']);

            Route::delete('/{session}', [EventSessionController::class, 'destroy'])
                ->middleware(['tenant', 'perm:event_sessions,delete', 'throttle:10,1,event-sessions-delete']);
        });

        // "Portarias" formais e opcionais de um evento — ver
        // App\Services\Event\EventGateService e
        // App\Services\Ticket\CheckinService (validação opt-in de
        // ticket_type por portaria no check-in).
        Route::prefix('events/{event}/gates')->group(function () {
            Route::get('/', [EventGateController::class, 'index'])
                ->middleware(['tenant', 'perm:event_gates,read', 'throttle:100,1,event-gates-list']);

            Route::post('/', [EventGateController::class, 'store'])
                ->middleware(['tenant', 'perm:event_gates,create', 'throttle:30,1,event-gates-create']);

            Route::get('/{gate}', [EventGateController::class, 'show'])
                ->middleware(['tenant', 'perm:event_gates,read', 'throttle:100,1,event-gates-show']);

            Route::put('/{gate}', [EventGateController::class, 'update'])
                ->middleware(['tenant', 'perm:event_gates,update', 'throttle:30,1,event-gates-update']);

            Route::delete('/{gate}', [EventGateController::class, 'destroy'])
                ->middleware(['tenant', 'perm:event_gates,delete', 'throttle:10,1,event-gates-delete']);
        });

        Route::prefix('ticket-types')->group(function () {
            Route::get('/', [TicketTypeController::class, 'index'])
                ->middleware(['tenant', 'perm:ticket_types,read', 'throttle:100,1,ticket-types-list']);

            Route::post('/', [TicketTypeController::class, 'store'])
                ->middleware(['tenant', 'perm:ticket_types,create', 'throttle:30,1,ticket-types-create']);

            Route::get('/{ticketType}', [TicketTypeController::class, 'show'])
                ->middleware(['tenant', 'perm:ticket_types,read', 'throttle:100,1,ticket-types-show']);

            Route::put('/{ticketType}', [TicketTypeController::class, 'update'])
                ->middleware(['tenant', 'perm:ticket_types,update', 'throttle:30,1,ticket-types-update']);

            Route::delete('/{ticketType}', [TicketTypeController::class, 'destroy'])
                ->middleware(['tenant', 'perm:ticket_types,delete', 'throttle:10,1,ticket-types-delete']);

            // Atalho pra alternar status ativo/pausado sem payload inteiro.
            Route::patch('/{ticketType}/toggle-status', [TicketTypeController::class, 'toggleStatus'])
                ->middleware(['tenant', 'perm:ticket_types,update', 'throttle:60,1,ticket-types-toggle-status']);

            // Lista de espera (roadmap inventário) — quem se cadastrou
            // enquanto o tipo de ingresso estava esgotado, útil pra medir
            // demanda represada. Ver
            // App\Services\TicketTypeWaitlist\TicketTypeWaitlistService.
            Route::get('/{ticketType}/lista-espera', [TicketTypeWaitlistController::class, 'index'])
                ->middleware(['tenant', 'perm:ticket_waitlist,read', 'throttle:100,1,ticket-types-waitlist-list']);
        });

        Route::prefix('ticket-types/{ticketType}/batches')->group(function () {
            Route::get('/', [TicketBatchController::class, 'index'])
                ->middleware(['tenant', 'perm:ticket_batches,read', 'throttle:100,1,ticket-batches-list']);

            Route::post('/', [TicketBatchController::class, 'store'])
                ->middleware(['tenant', 'perm:ticket_batches,create', 'throttle:30,1,ticket-batches-create']);

            Route::get('/{batch}', [TicketBatchController::class, 'show'])
                ->middleware(['tenant', 'perm:ticket_batches,read', 'throttle:100,1,ticket-batches-show']);

            Route::put('/{batch}', [TicketBatchController::class, 'update'])
                ->middleware(['tenant', 'perm:ticket_batches,update', 'throttle:30,1,ticket-batches-update']);

            Route::delete('/{batch}', [TicketBatchController::class, 'destroy'])
                ->middleware(['tenant', 'perm:ticket_batches,delete', 'throttle:10,1,ticket-batches-delete']);
        });

        Route::prefix('event-products')->group(function () {
            Route::get('/', [EventProductController::class, 'index'])
                ->middleware(['tenant', 'perm:event_products,read', 'throttle:100,1,event-products-list']);

            Route::post('/', [EventProductController::class, 'store'])
                ->middleware(['tenant', 'perm:event_products,create', 'throttle:30,1,event-products-create']);

            Route::get('/{eventProduct}', [EventProductController::class, 'show'])
                ->middleware(['tenant', 'perm:event_products,read', 'throttle:100,1,event-products-show']);

            Route::put('/{eventProduct}', [EventProductController::class, 'update'])
                ->middleware(['tenant', 'perm:event_products,update', 'throttle:30,1,event-products-update']);

            Route::delete('/{eventProduct}', [EventProductController::class, 'destroy'])
                ->middleware(['tenant', 'perm:event_products,delete', 'throttle:10,1,event-products-delete']);
        });

        Route::prefix('venues')->group(function () {
            Route::get('/', [VenueController::class, 'index'])
                ->middleware(['tenant', 'perm:venues,read', 'throttle:100,1,venues-list']);

            Route::post('/', [VenueController::class, 'store'])
                ->middleware(['tenant', 'perm:venues,create', 'throttle:30,1,venues-create']);

            Route::get('/{venue}', [VenueController::class, 'show'])
                ->middleware(['tenant', 'perm:venues,read', 'throttle:100,1,venues-show']);

            Route::put('/{venue}', [VenueController::class, 'update'])
                ->middleware(['tenant', 'perm:venues,update', 'throttle:30,1,venues-update']);

            Route::delete('/{venue}', [VenueController::class, 'destroy'])
                ->middleware(['tenant', 'perm:venues,delete', 'throttle:10,1,venues-delete']);

            Route::post('/{venue}/publish', [VenueController::class, 'publish'])
                ->middleware(['tenant', 'perm:venues,update', 'throttle:20,1,venues-publish']);

            Route::get('/{venue}/seats', [SeatController::class, 'index'])
                ->middleware(['tenant', 'perm:seats,read', 'throttle:100,1,seats-list']);

            Route::post('/{venue}/seats', [SeatController::class, 'store'])
                ->middleware(['tenant', 'perm:seats,create', 'throttle:30,1,seats-create']);

            Route::get('/{venue}/seats/{seat}', [SeatController::class, 'show'])
                ->middleware(['tenant', 'perm:seats,read', 'throttle:100,1,seats-show']);

            Route::put('/{venue}/seats/{seat}', [SeatController::class, 'update'])
                ->middleware(['tenant', 'perm:seats,update', 'throttle:30,1,seats-update']);

            Route::delete('/{venue}/seats/{seat}', [SeatController::class, 'destroy'])
                ->middleware(['tenant', 'perm:seats,delete', 'throttle:10,1,seats-delete']);
        });

        // Busca de comprador (FinalCustomerTenantLink) pro staff, usada na
        // venda manual — equivalente ao antigo GET /clients
        // do ClientController removido em favor de FinalCustomer.
        Route::get('/final-customers', [FinalCustomerController::class, 'index'])
            ->middleware(['tenant', 'perm:customers,read', 'throttle:100,1,final-customers-list']);

        // CRM básico do comprador (Fase 6) — agregação de total gasto/
        // compras/última compra + filtros de segmentação simples.
        Route::get('/final-customers/crm', [FinalCustomerController::class, 'crm'])
            ->middleware(['tenant', 'perm:customers,read', 'throttle:100,1,final-customers-crm']);

        Route::prefix('sales')->group(function () {
            Route::get('/', [SaleController::class, 'index'])
                ->middleware(['tenant', 'perm:sales,read', 'throttle:100,1,sales-list']);

            Route::post('/', [SaleController::class, 'store'])
                ->middleware(['tenant', 'perm:sales,create', 'throttle:30,1,sales-create']);

            Route::get('/{sale}', [SaleController::class, 'show'])
                ->middleware(['tenant', 'perm:sales,read', 'throttle:100,1,sales-show']);

            // Edição de itens/cabeçalho de venda já criada — escopo
            // limitado (não altera final_customer_uuid/is_installment), só
            // permitida enquanto a venda não está entregue/paga/cancelada.
            // Reaproveita perm:sales,update (mesma permissão já usada na
            // gestão manual de parcela).
            Route::put('/{sale}/items', [SaleController::class, 'updateItems'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:30,1,sales-update-items']);

            // Cobrança Pix de pagamento da venda (roadmap 2A — recebimento
            // do tenant). Reaproveita perm:sales,update (mesma permissão já
            // usada na gestão manual de parcela/itens), sem nova
            // Functionality.
            Route::get('/{sale}/payment-checkout-config', [SaleController::class, 'paymentCheckoutConfig'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:30,1,sales-payment-checkout-config']);

            Route::post('/{sale}/payment-charge', [SaleController::class, 'paymentCharge'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:30,1,sales-payment-charge']);

            Route::patch('/{sale}/installments/{installment}/pay', [SaleController::class, 'payInstallment'])
                ->middleware(['tenant', 'perm:sales,pay', 'throttle:30,1,sales-installments-pay']);

            Route::patch('/{sale}/installments/{installment}/unpay', [SaleController::class, 'unpayInstallment'])
                ->middleware(['tenant', 'perm:sales,pay', 'throttle:30,1,sales-installments-unpay']);

            // Gestão manual de parcela (correção/paridade com o legado) —
            // controller/service próprios (SaleInstallmentController/
            // SaleInstallmentService), ver architecture-decisions.md.
            Route::post('/{sale}/installments', [SaleInstallmentController::class, 'store'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:30,1,sales-installments-create']);

            Route::put('/{sale}/installments/{installment}', [SaleInstallmentController::class, 'update'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:30,1,sales-installments-update']);

            Route::delete('/{sale}/installments/{installment}', [SaleInstallmentController::class, 'destroy'])
                ->middleware(['tenant', 'perm:sales,delete', 'throttle:10,1,sales-installments-delete']);

            // Substituição em lote das parcelas não pagas — resolve a
            // limitação matemática dos 3 endpoints individuais acima
            // (soma validada a cada chamada isolada torna redistribuição
            // entre parcelas impossível sem 422 intermediário). Caminho
            // recomendado pro frontend pra qualquer edição de valor.
            Route::put('/{sale}/installments', [SaleInstallmentController::class, 'reallocate'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:30,1,sales-installments-reallocate']);

            Route::patch('/{sale}/cancel', [SaleController::class, 'cancel'])
                ->middleware(['tenant', 'perm:sales,cancel', 'throttle:30,1,sales-cancel']);

            // Estorno externo (spec 5.14/11.3): o clube já estornou no
            // PagBank fora do sistema, aqui só se REGISTRA o estorno e os
            // efeitos internos (tickets invalidados, lugar liberado se
            // escolhido). Functionality própria (sale_refunds), ações
            // 'create'/'read' já existentes. Ver SaleRefundController.
            Route::get('/{sale}/refunds', [SaleRefundController::class, 'index'])
                ->middleware(['tenant', 'perm:sale_refunds,read', 'throttle:60,1,sales-refunds-list']);

            Route::post('/{sale}/refunds', [SaleRefundController::class, 'store'])
                ->middleware(['tenant', 'perm:sale_refunds,create', 'throttle:30,1,sales-refunds-create']);

            Route::get('/{sale}/refunds/{refund}/receipt', [SaleRefundController::class, 'receipt'])
                ->middleware(['tenant', 'perm:sale_refunds,read', 'throttle:60,1,sales-refunds-receipt']);

            // Fila de aprovação do staff — toda venda do canal público
            // (origin=storefront) nasce pending_approval e precisa
            // passar por aqui. Reaproveita perm:sales,update (mesma
            // permissão já usada na gestão manual de parcela/itens).
            Route::post('/{sale}/approve', [SaleController::class, 'approve'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:60,1,sales-approve']);

            Route::post('/{sale}/reject', [SaleController::class, 'reject'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:60,1,sales-reject']);

            // Aprovação/rejeição da solicitação de cancelamento feita pelo
            // cliente final via Portal (roadmap A4) — reaproveita
            // perm:sales,update (mesma permissão já usada em approve/reject
            // acima). approve executa cancel() de fato (estoque/estorno);
            // reject só reverte o status, nada foi executado ainda.
            Route::post('/{sale}/approve-cancellation', [SaleController::class, 'approveCancellation'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:60,1,sales-approve-cancellation']);

            Route::post('/{sale}/reject-cancellation', [SaleController::class, 'rejectCancellation'])
                ->middleware(['tenant', 'perm:sales,update', 'throttle:60,1,sales-reject-cancellation']);

            Route::get('/{sale}/workflow-transitions', [WorkflowTransitionLogController::class, 'sale'])
                ->middleware(['tenant', 'perm:sales,read', 'throttle:120,1,sales-workflow-transitions']);
        });

        // Caixa (roadmap Fase 2 — bilheteria presencial). Functionality
        // própria (cash_sessions), ações 'read'/'open'/'close' (as duas
        // últimas reaproveitadas de ActionsSeeder — já existiam órfãs desde
        // o domínio anterior, "Abrir/Fechar operação"). Ver
        // CashSessionController.
        Route::prefix('cash-sessions')->group(function () {
            Route::get('/', [CashSessionController::class, 'index'])
                ->middleware(['tenant', 'perm:cash_sessions,read', 'throttle:60,1,cash-sessions-list']);

            Route::get('/current', [CashSessionController::class, 'current'])
                ->middleware(['tenant', 'perm:cash_sessions,read', 'throttle:100,1,cash-sessions-current']);

            Route::post('/open', [CashSessionController::class, 'open'])
                ->middleware(['tenant', 'perm:cash_sessions,open', 'throttle:20,1,cash-sessions-open']);

            Route::post('/close', [CashSessionController::class, 'close'])
                ->middleware(['tenant', 'perm:cash_sessions,close', 'throttle:20,1,cash-sessions-close']);
        });

        // Ticket = ingresso digital emitido (spec 5.15/5.16). Emissão é
        // automática (TicketIssuanceService, ouvindo SalePaid/SaleCancelled)
        // — sem rota de create/update manual aqui.
        Route::prefix('tickets')->group(function () {
            Route::get('/', [TicketController::class, 'index'])
                ->middleware(['tenant', 'perm:tickets,read', 'throttle:100,1,tickets-list']);

            Route::get('/checkin/summary', [TicketController::class, 'checkinSummary'])
                ->middleware(['tenant', 'perm:tickets,read', 'throttle:100,1,tickets-checkin-summary']);

            // Check-in de portaria (leitura de QR ou busca manual) — path
            // literal, sem conflito com GET /{ticket} (método diferente).
            Route::post('/checkin', [TicketController::class, 'checkin'])
                ->middleware(['tenant', 'perm:tickets,checkin', 'throttle:120,1,tickets-checkin']);

            Route::get('/{ticket}', [TicketController::class, 'show'])
                ->middleware(['tenant', 'perm:tickets,read', 'throttle:100,1,tickets-show']);

            Route::get('/{ticket}/checkins', [TicketController::class, 'checkinHistory'])
                ->middleware(['tenant', 'perm:tickets,read', 'throttle:100,1,tickets-checkin-history']);

            Route::post('/{ticket}/resend', [TicketController::class, 'resend'])
                ->middleware(['tenant', 'perm:tickets,resend', 'throttle:20,1,tickets-resend']);
        });

        // Tela dedicada de gestão de vendas online (origin=storefront) —
        // permissão própria (storefront-sales,*), independente de
        // perm:sales,*. Reaproveita os MESMOS métodos de SaleController
        // onde a regra de negócio já existe (approve/reject/cancel);
        // indexStorefront() é o único método novo. Ver
        // .claude/memory/architecture-decisions.md.
        Route::prefix('storefront-sales')->group(function () {
            Route::get('/', [SaleController::class, 'indexStorefront'])
                ->middleware(['tenant', 'perm:storefront-sales,read', 'throttle:100,1,storefront-sales-list']);

            Route::get('/{sale}', [SaleController::class, 'show'])
                ->middleware(['tenant', 'perm:storefront-sales,read', 'throttle:100,1,storefront-sales-show']);

            // Gera o link temporário de preparo (QR code) — mesma permissão
            // de leitura (só quem vê a venda gera o link).
            Route::post('/{sale}/prep-link', [SaleController::class, 'prepLink'])
                ->middleware(['tenant', 'perm:storefront-sales,read', 'throttle:20,1,storefront-sales-prep-link']);

            Route::post('/{sale}/approve', [SaleController::class, 'approve'])
                ->middleware(['tenant', 'perm:storefront-sales,approve', 'throttle:60,1,storefront-sales-approve']);

            Route::post('/{sale}/reject', [SaleController::class, 'reject'])
                ->middleware(['tenant', 'perm:storefront-sales,approve', 'throttle:60,1,storefront-sales-reject']);

            Route::patch('/{sale}/cancel', [SaleController::class, 'cancel'])
                ->middleware(['tenant', 'perm:storefront-sales,cancel', 'throttle:30,1,storefront-sales-cancel']);

            Route::get('/{sale}/workflow-transitions', [WorkflowTransitionLogController::class, 'sale'])
                ->middleware(['tenant', 'perm:storefront-sales,read', 'throttle:120,1,storefront-sales-workflow-transitions']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/indicators', [ReportController::class, 'indicators'])
                ->middleware(['tenant', 'perm:dashboard,read', 'throttle:60,1,reports-indicators']);

            // Dashboard operacional em tempo quase real (roadmap Fase 2) —
            // throttle mais generoso, pensado pra polling curto (ver
            // OperationSnapshotService).
            Route::get('/operation-snapshot', [ReportController::class, 'operationSnapshot'])
                ->middleware(['tenant', 'perm:dashboard,read', 'throttle:200,1,reports-operation-snapshot']);

            Route::get('/charts', [ReportController::class, 'charts'])
                ->middleware(['tenant', 'perm:dashboard,read', 'throttle:60,1,reports-charts']);

            Route::get('/sales', [ReportController::class, 'sales'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-sales']);

            Route::get('/sales/summary', [ReportController::class, 'salesSummary'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-sales-summary']);

            // Resultado por canal (roadmap A1.3) — drill-down até a venda
            // via GET /sales?origin=X&date_from=Y&date_to=Z (já existente).
            Route::get('/by-channel', [ReportController::class, 'byChannel'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-by-channel']);

            Route::post('/sales/pdf', [ReportController::class, 'salesPdf'])
                ->middleware(['tenant', 'perm:reports,export_pdf', 'throttle:10,1,reports-sales-pdf']);

            // Analytics (Fase 1 do roadmap) — Functionality própria
            // `analytics`, presente só nos planos professional/premium;
            // o gate PLAN_UPGRADE_REQUIRED é aplicado pelo CheckPermission.
            Route::prefix('analytics')->group(function () {
                Route::get('/sales-summary', [AnalyticsController::class, 'salesSummary'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-sales-summary']);

                Route::get('/top-products', [AnalyticsController::class, 'topProducts'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-top-products']);

                Route::get('/sales-by-location', [AnalyticsController::class, 'salesByLocation'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-sales-by-location']);

                Route::get('/sales-history', [AnalyticsController::class, 'salesHistory'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-sales-history']);

                Route::get('/top-clients', [AnalyticsController::class, 'topClients'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-top-clients']);

                Route::get('/payment-delays', [AnalyticsController::class, 'paymentDelays'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-payment-delays']);

                Route::get('/overdue-sales', [AnalyticsController::class, 'overdueOrders'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-overdue-sales']);

                Route::get('/abc-analysis', [AnalyticsController::class, 'abcAnalysis'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-abc-analysis']);

                Route::get('/margin-summary', [AnalyticsController::class, 'marginSummary'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-margin-summary']);

                Route::get('/coupon-roi', [AnalyticsController::class, 'couponRoi'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-coupon-roi']);

                Route::get('/revenue-concentration', [AnalyticsController::class, 'revenueConcentration'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-revenue-concentration']);

                Route::get('/churn-clients', [AnalyticsController::class, 'churnClients'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-churn-clients']);

                Route::get('/sales-by-hour', [AnalyticsController::class, 'salesByHour'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-sales-by-hour']);

                Route::get('/checkin-insights', [AnalyticsController::class, 'checkinInsights'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-checkin-insights']);
            });
        });

        // Apoio à conciliação financeira (roadmap A3.12) — leitura agregada
        // de payments/refunds/webhook_events, já existentes desde o roadmap
        // 2A/1B. Functionality própria `finance`, reaproveita a action
        // `read` já existente. Ver App\Services\Finance\ReconciliationService.
        Route::prefix('finance')->group(function () {
            Route::get('/dashboard', [FinanceOperationsController::class, 'dashboard'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-dashboard']);

            Route::get('/receivables', [FinanceOperationsController::class, 'receivables'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-receivables']);

            Route::get('/receivables/summary', [FinanceOperationsController::class, 'receivablesSummary'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-receivables-summary']);

            Route::get('/settlements', [FinanceOperationsController::class, 'settlements'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-settlements']);

            Route::get('/settlements/summary', [FinanceOperationsController::class, 'settlementsSummary'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-settlements-summary']);

            Route::get('/reconciliation', [ReconciliationController::class, 'index'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-reconciliation']);

            Route::get('/reconciliation/summary', [ReconciliationController::class, 'summary'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-reconciliation-summary']);

            Route::get('/adjustments', [SettlementAdjustmentController::class, 'index'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-adjustments']);

            Route::get('/adjustments/summary', [SettlementAdjustmentController::class, 'summary'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-adjustments-summary']);

            Route::post('/adjustments/manual', [SettlementAdjustmentController::class, 'storeManual'])
                ->middleware(['tenant', 'perm:finance,update', 'throttle:20,1,finance-adjustments-manual']);

            Route::post('/adjustments/{uuid}/resolve-recovery', [SettlementAdjustmentController::class, 'resolveRecovery'])
                ->middleware(['tenant', 'perm:finance,update', 'throttle:20,1,finance-adjustments-resolve-recovery']);

            Route::post('/adjustments/{uuid}/resolve-review', [SettlementAdjustmentController::class, 'resolveReview'])
                ->middleware(['tenant', 'perm:finance,update', 'throttle:20,1,finance-adjustments-resolve-review']);
        });

        // Assinatura da empresa (roadmap 1B — cobrança de planos).
        // tenant-scoped, functionality própria `subscription`. GET sempre
        // acessível (mesmo com assinatura suspensa — é a tela de
        // reativação); cancel/withdrawal exigem `subscription,update`.
        Route::prefix('subscription')->group(function () {
            Route::get('/', [SubscriptionController::class, 'show'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,read', 'throttle:60,1,subscription-show']);

            Route::post('/', [SubscriptionController::class, 'store'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:10,1,subscription-store']);

            Route::post('/cancel', [SubscriptionController::class, 'cancel'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:20,1,subscription-cancel']);

            Route::post('/withdrawal', [SubscriptionController::class, 'withdrawal'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:20,1,subscription-withdrawal']);

            Route::post('/renew', [SubscriptionController::class, 'renew'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:20,1,subscription-renew']);

            Route::put('/payment-method', [SubscriptionController::class, 'updatePaymentMethod'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:10,1,subscription-payment-method']);

            Route::get('/invoices', [SubscriptionController::class, 'invoices'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,read', 'throttle:60,1,subscription-invoices']);

            Route::post('/invoices/{invoice:uuid}/pix-charge', [SubscriptionController::class, 'invoicePixCharge'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:20,1,subscription-invoice-pix-charge']);

            Route::get('/plan-pricing', [SubscriptionController::class, 'planPricing'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,read', 'throttle:60,1,subscription-plan-pricing']);

            Route::get('/available-plans', [SubscriptionController::class, 'availablePlans'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,read', 'throttle:60,1,subscription-available-plans']);

            Route::post('/change-plan', [SubscriptionController::class, 'changePlan'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,update', 'throttle:10,1,subscription-change-plan']);

            Route::get('/history', [SubscriptionController::class, 'history'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,read', 'throttle:60,1,subscription-history']);
        });

        // Estornos do PRÓPRIO tenant (roadmap 2026-07-24) — venda pago
        // cancelado, arrependimento de assinatura e contestação, todos
        // reunidos numa única visão para o proprietário (hoje espalhados
        // entre /sales e /subscription sem lista dedicada).
        Route::prefix('subscription')->group(function () {
            Route::get('/refunds', [RefundController::class, 'index'])
                ->middleware(['tenant', 'tenant.owner', 'perm:subscription,read', 'throttle:60,1,subscription-refunds']);
        });

        // Central de chamados nativa (roadmap A4, item 17) — reaproveita o
        // padrão de dado do módulo do contador (anexo opcional, status
        // simples). Ver App\Services\Support\HelpRequestService. Entidade
        // renomeada de SupportTicket para HelpRequest (2026-07-31): "Ticket"
        // passou a ser reservado para o domínio central do produto (ingresso
        // de evento), sem ambiguidade com o módulo de suporte.
        Route::prefix('support')->group(function () {
            Route::get('/help-requests', [HelpRequestController::class, 'index'])
                ->middleware(['tenant', 'perm:support,read', 'throttle:60,1,support-help-requests-list']);

            Route::post('/help-requests', [HelpRequestController::class, 'store'])
                ->middleware(['tenant', 'perm:support,create', 'throttle:20,1,support-help-requests-create']);
        });

    });
});
