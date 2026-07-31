<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Health\HealthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AuthAccessController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\SelfSignupController;
use App\Http\Controllers\Plan\PlanController;
use App\Http\Controllers\Plan\PlanFunctionalityController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\Functionality\FunctionalityController;
use App\Http\Controllers\AuditLog\AuditLogController;
use App\Http\Controllers\Tenant\TenantController;
use App\Http\Controllers\Tenant\TenantFeatureOverrideController;
use App\Http\Controllers\Tenant\TenantProfileController;
use App\Http\Controllers\Tenant\TenantDataExportController;
use App\Http\Controllers\Tenant\TenantRoleController;
use App\Http\Controllers\Tenant\TenantRolePermissionController;
use App\Http\Controllers\Tenant\TenantUserController;
use App\Http\Controllers\Tenant\TenantUserInviteController;
use App\Http\Controllers\Auth\AcceptTenantUserInviteController;
use App\Http\Controllers\Auth\AuthTenantController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\ConfirmEmailController;
use App\Http\Controllers\Legal\LegalDocumentController;
use App\Http\Controllers\Legal\ReleaseNoteController;
use App\Http\Controllers\Privacy\PrivacyRequestController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\Subscription\PaymentWebhookController;
use App\Http\Controllers\Subscription\RefundController;
use App\Http\Controllers\Payment\PaymentIssueController;
use App\Http\Controllers\Event\EventCategoryController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Event\EventImageController;
use App\Http\Controllers\Event\TicketTypeController;
use App\Http\Controllers\Event\TicketTypeImageController;
use App\Http\Controllers\Event\EventProductController;
use App\Http\Controllers\FinalCustomer\FinalCustomerController;
use App\Http\Controllers\Location\EstadoController;
use App\Http\Controllers\Location\CidadeController;
use App\Http\Controllers\Location\BairroController;
use App\Http\Controllers\Location\EnderecoController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\TenantSettings\TenantSettingsController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\OrderInstallmentController;
use App\Http\Controllers\Order\OrderTrackingController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\AnalyticsController;
use App\Http\Controllers\Finance\ReconciliationController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalLinkController;
use App\Http\Controllers\Portal\PortalCouponController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalFavoriteController;
use App\Http\Controllers\Portal\PushSubscriptionController;
use App\Http\Controllers\User\UserAvatarController;
use App\Http\Controllers\Tenant\TenantLogoController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\CartEventController;
use App\Http\Controllers\Storefront\StorefrontCheckoutController;
use App\Http\Controllers\Storefront\StorefrontManifestController;
use App\Http\Controllers\Storefront\StorefrontLocationController;
use App\Http\Controllers\Storefront\CouponController;
use App\Http\Controllers\Storefront\ProductPromotionController;
use App\Http\Controllers\Support\HelpRequestController;
use App\Http\Controllers\Workflow\WorkflowTransitionLogController;

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

    // Acompanhamento de pedido (roadmap 5.1) — 100% público, sem
    // jwt/tenant/perm, protegido só pelo uuid do pedido ser imprevisível
    // (link enviado por WhatsApp na criação do pedido). Ver
    // App\Http\Controllers\Order\OrderTrackingController.
    Route::get('/rastreio/{order:uuid}', [OrderTrackingController::class, 'show'])
        ->middleware('throttle:60,1,order-tracking-public');

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

    // Categorias com evento disponível (vitrine) — mesmo espírito de
    // /loja/{slug}/eventos.
    Route::get('/loja/{slug}/categorias', [StorefrontController::class, 'categories'])
        ->middleware('throttle:100,1,storefront-categories');

    Route::get('/loja/{slug}/manifest.webmanifest', [StorefrontManifestController::class, 'show'])
        ->middleware('throttle:100,1,storefront-manifest');

    // Consulta prévia de taxa de entrega (Delivery Fase 2) — o frontend
    // chama ao escolher o bairro no checkout, antes de confirmar o pedido.
    // 100% público, mesmo espírito de /loja/{slug}/produtos. Ver
    // App\Http\Controllers\Storefront\StorefrontController::deliveryFee().
    Route::get('/loja/{slug}/taxa-entrega/{bairro_uuid}', [StorefrontController::class, 'deliveryFee'])
        ->middleware('throttle:100,1,storefront-delivery-fee');

    // Prévia pública de cupom (Delivery Fase 3) — o frontend chama ao
    // digitar o código no checkout, antes do OTP/identificação do cliente
    // final. Não consome CouponRedemption (só StorefrontCheckoutService::
    // checkout() consome, no submit final). Mesmo espírito 100% público de
    // /loja/{slug}/taxa-entrega/{bairro_uuid}. Ver
    // App\Http\Controllers\Storefront\StorefrontController::validateCoupon().
    Route::post('/loja/{slug}/cupons/validar', [StorefrontController::class, 'validateCoupon'])
        ->middleware('throttle:100,1,storefront-coupon-validate');

    // Telemetria de abandono de carrinho (roadmap A3.14) — 100% público,
    // mesmo espírito de /loja/{slug}/cupons/validar. Captura client-side no
    // checkout da loja; sem tela de leitura ainda, só o registro. Ver
    // App\Http\Controllers\Storefront\CartEventController.
    Route::post('/loja/{slug}/eventos-carrinho', [CartEventController::class, 'store'])
        ->middleware('throttle:60,1,storefront-cart-events');


    // Estado/Cidade/Bairro para a cascata de endereço do checkout da loja —
    // descoberto durante o frontend (2026-07-16) que EstadoController/
    // CidadeController/BairroController exigem o guard de staff (`jwt`),
    // que rejeita explicitamente um token `customer.jwt` (FinalCustomer).
    // Path fixo (não `/loja/{slug}/...`) porque os dados são globais, sem
    // tenant_id — sem conflito de rota com `/loja/{slug}` (segmentos
    // diferentes). Ver App\Http\Controllers\Storefront\StorefrontLocationController.
    Route::get('/loja/localizacoes/estados', [StorefrontLocationController::class, 'estados'])
        ->middleware('throttle:100,1,storefront-estados');

    Route::get('/loja/localizacoes/cidades', [StorefrontLocationController::class, 'cidades'])
        ->middleware('throttle:100,1,storefront-cidades');

    Route::get('/loja/localizacoes/bairros', [StorefrontLocationController::class, 'bairros'])
        ->middleware('throttle:100,1,storefront-bairros');

    // CEP (ViaCEP) + reverse-geocode públicos pro endereço do checkout —
    // mesmo espírito das rotas acima. Throttle mais baixo (chamam API
    // externa de terceiro).
    Route::get('/loja/localizacoes/cep/{cep}', [StorefrontLocationController::class, 'cep'])
        ->middleware('throttle:60,1,storefront-cep-lookup');

    Route::get('/loja/localizacoes/reverse-geocode', [StorefrontLocationController::class, 'reverseGeocode'])
        ->middleware('throttle:30,1,storefront-reverse-geocode');

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

        Route::get('/orders', [PortalController::class, 'orders'])
            ->middleware('throttle:60,1,portal-orders-list');

        Route::get('/me', [PortalController::class, 'me'])
            ->middleware('throttle:60,1,portal-me');

        // Favoritos de evento. Toggle idempotente: favorito existente
        // remove, inexistente cria.
        Route::post('/favorites/{event_uuid}/toggle', [PortalFavoriteController::class, 'toggle'])
            ->middleware('throttle:60,1,portal-favorites-toggle');

        Route::get('/favorites', [PortalFavoriteController::class, 'index'])
            ->middleware('throttle:60,1,portal-favorites-list');

        // "Pedir de novo" (roadmap Delivery, Fase 4) — itens do pedido
        // antigo com preço/disponibilidade ATUAIS do produto.
        Route::get('/orders/{uuid}/items', [PortalController::class, 'orderItems'])
            ->middleware('throttle:60,1,portal-order-items');

        // Avaliação de pedido entregue (roadmap Delivery, Fase 4) — 1
        // avaliação por pedido, ver OrderRatingService::rate().
        Route::post('/orders/{uuid}/rating', [PortalController::class, 'rate'])
            ->middleware('throttle:30,1,portal-orders-rate');

        // Solicitação de cancelamento (roadmap A4) — só pedido
        // origin=storefront, só enquanto não saiu para entrega/entregue.
        // Não muda estoque/pagamento ainda, ver OrderService::requestCancellation().
        Route::post('/orders/{uuid}/request-cancellation', [PortalController::class, 'requestCancellation'])
            ->middleware('throttle:30,1,portal-orders-request-cancellation');

        // Cobrança Pix do próprio pedido (roadmap Fase B, item 1 — checkout
        // Pix na loja pública). Reaproveita OrderPaymentService (mesma regra
        // de negócio do endpoint de staff), posse verificada via
        // PortalCustomerService::findOwnedOrder(), ver PortalController.
        Route::post('/orders/{uuid}/payment-charge', [PortalController::class, 'paymentCharge'])
            ->middleware('throttle:20,1,portal-orders-payment-charge');

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

        // Reverse geocoding (lat/lng -> sugestão de estado/cidade/bairro)
        // pra pré-selecionar os combos em cascata no formulário de cliente
        // ("usar minha localização atual"). Sem tenant: estados/cidades/
        // bairros são tabelas globais de referência, sem tenant_id. Sem
        // perm: qualquer usuário autenticado pode usar, mesmo espírito de
        // /auth/access-profile. Ver App\Services\Location\ReverseGeocodeService.
        Route::get('/location/reverse-geocode', [LocationController::class, 'reverseGeocode'])
            ->middleware('throttle:20,1,location-reverse-geocode');

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

        // Global (sem middleware tenant): Estado/Cidade/Bairro são compartilhados entre tenants.
        Route::prefix('estados')->group(function () {
            Route::get('/', [EstadoController::class, 'index'])
                ->middleware(['tenant', 'perm:estados,read', 'throttle:100,1,estados-list']);

            Route::post('/', [EstadoController::class, 'store'])
                ->middleware(['tenant', 'perm:estados,create', 'throttle:30,1,estados-create']);

            Route::put('/{estado}', [EstadoController::class, 'update'])
                ->middleware(['tenant', 'perm:estados,update', 'throttle:30,1,estados-update']);

            Route::delete('/{estado}', [EstadoController::class, 'destroy'])
                ->middleware(['tenant', 'perm:estados,delete', 'throttle:10,1,estados-delete']);
        });

        Route::prefix('cidades')->group(function () {
            Route::get('/', [CidadeController::class, 'index'])
                ->middleware(['tenant', 'perm:cidades,read', 'throttle:100,1,cidades-list']);

            Route::post('/', [CidadeController::class, 'store'])
                ->middleware(['tenant', 'perm:cidades,create', 'throttle:30,1,cidades-create']);

            Route::put('/{cidade}', [CidadeController::class, 'update'])
                ->middleware(['tenant', 'perm:cidades,update', 'throttle:30,1,cidades-update']);

            Route::delete('/{cidade}', [CidadeController::class, 'destroy'])
                ->middleware(['tenant', 'perm:cidades,delete', 'throttle:10,1,cidades-delete']);
        });

        Route::prefix('bairros')->group(function () {
            Route::get('/', [BairroController::class, 'index'])
                ->middleware(['tenant', 'perm:bairros,read', 'throttle:100,1,bairros-list']);

            Route::post('/', [BairroController::class, 'store'])
                ->middleware(['tenant', 'perm:bairros,create', 'throttle:30,1,bairros-create']);

            Route::put('/{bairro}', [BairroController::class, 'update'])
                ->middleware(['tenant', 'perm:bairros,update', 'throttle:30,1,bairros-update']);

            Route::delete('/{bairro}', [BairroController::class, 'destroy'])
                ->middleware(['tenant', 'perm:bairros,delete', 'throttle:10,1,bairros-delete']);
        });

        // Tenant-scoped: cada tenant cadastra os logradouros do seu próprio cliente.
        Route::prefix('enderecos')->group(function () {
            Route::get('/', [EnderecoController::class, 'index'])
                ->middleware(['tenant', 'perm:enderecos,read', 'throttle:100,1,enderecos-list']);

            Route::post('/', [EnderecoController::class, 'store'])
                ->middleware(['tenant', 'perm:enderecos,create', 'throttle:30,1,enderecos-create']);

            Route::put('/{endereco}', [EnderecoController::class, 'update'])
                ->middleware(['tenant', 'perm:enderecos,update', 'throttle:30,1,enderecos-update']);

            Route::delete('/{endereco}', [EnderecoController::class, 'destroy'])
                ->middleware(['tenant', 'perm:enderecos,delete', 'throttle:10,1,enderecos-delete']);
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

        // Cupons de desconto sobre o carrinho todo (Delivery Fase 3) — CRUD
        // completo (create/update, diferente do upsert de taxa de entrega).
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

        // Preço promocional "de/por" por tipo de ingresso — upsert 1 por
        // ticket type, mesmo shape de store-delivery-fees. Ver
        // App\Services\Storefront\ProductPromotionService.
        Route::prefix('product-promotions')->group(function () {
            Route::get('/', [ProductPromotionController::class, 'index'])
                ->middleware(['tenant', 'perm:storefront,read', 'throttle:100,1,product-promotions-list']);

            Route::post('/', [ProductPromotionController::class, 'store'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,product-promotions-create']);

            Route::delete('/{uuid}', [ProductPromotionController::class, 'destroy'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:10,1,product-promotions-delete']);
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

            Route::delete('/{event}', [EventController::class, 'destroy'])
                ->middleware(['tenant', 'perm:events,delete', 'throttle:10,1,events-delete']);
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

        // Busca de comprador (FinalCustomerTenantLink) pro staff, usada no
        // pedido manual (OrderFormPage) — equivalente ao antigo GET /clients
        // do ClientController removido em favor de FinalCustomer.
        Route::get('/final-customers', [FinalCustomerController::class, 'index'])
            ->middleware(['tenant', 'perm:customers,read', 'throttle:100,1,final-customers-list']);

        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:100,1,orders-list']);

            Route::post('/', [OrderController::class, 'store'])
                ->middleware(['tenant', 'perm:orders,create', 'throttle:30,1,orders-create']);

            Route::get('/{order}', [OrderController::class, 'show'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:100,1,orders-show']);

            // Edição de itens/cabeçalho de pedido já criado — escopo
            // limitado (não altera client_uuid/is_installment), só
            // permitida enquanto o pedido não está entregue/pago/cancelado.
            // Reaproveita perm:orders,update (mesma permissão já usada na
            // gestão manual de parcela).
            Route::put('/{order}/items', [OrderController::class, 'updateItems'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:30,1,orders-update-items']);

            Route::patch('/{order}/deliver', [OrderController::class, 'deliver'])
                ->middleware(['tenant', 'perm:orders,deliver', 'throttle:30,1,orders-deliver']);

            Route::patch('/{order}/undeliver', [OrderController::class, 'undeliver'])
                ->middleware(['tenant', 'perm:orders,deliver', 'throttle:30,1,orders-undeliver']);

            Route::patch('/{order}/pay', [OrderController::class, 'pay'])
                ->middleware(['tenant', 'perm:orders,pay', 'throttle:30,1,orders-pay']);

            Route::patch('/{order}/unpay', [OrderController::class, 'unpay'])
                ->middleware(['tenant', 'perm:orders,pay', 'throttle:30,1,orders-unpay']);

            // Cobrança Pix de pagamento do pedido (roadmap 2A — recebimento
            // do tenant). Reaproveita perm:orders,update (mesma permissão já
            // usada na gestão manual de parcela/itens), sem nova
            // Functionality.
            Route::post('/{order}/payment-charge', [OrderController::class, 'paymentCharge'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:30,1,orders-payment-charge']);

            Route::patch('/{order}/installments/{installment}/pay', [OrderController::class, 'payInstallment'])
                ->middleware(['tenant', 'perm:orders,pay', 'throttle:30,1,orders-installments-pay']);

            Route::patch('/{order}/installments/{installment}/unpay', [OrderController::class, 'unpayInstallment'])
                ->middleware(['tenant', 'perm:orders,pay', 'throttle:30,1,orders-installments-unpay']);

            // Gestão manual de parcela (correção/paridade com o legado) —
            // controller/service próprios (OrderInstallmentController/
            // OrderInstallmentService), ver architecture-decisions.md.
            Route::post('/{order}/installments', [OrderInstallmentController::class, 'store'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:30,1,orders-installments-create']);

            Route::put('/{order}/installments/{installment}', [OrderInstallmentController::class, 'update'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:30,1,orders-installments-update']);

            Route::delete('/{order}/installments/{installment}', [OrderInstallmentController::class, 'destroy'])
                ->middleware(['tenant', 'perm:orders,delete', 'throttle:10,1,orders-installments-delete']);

            // Substituição em lote das parcelas não pagas — resolve a
            // limitação matemática dos 3 endpoints individuais acima
            // (soma validada a cada chamada isolada torna redistribuição
            // entre parcelas impossível sem 422 intermediário). Caminho
            // recomendado pro frontend pra qualquer edição de valor.
            Route::put('/{order}/installments', [OrderInstallmentController::class, 'reallocate'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:30,1,orders-installments-reallocate']);

            Route::patch('/{order}/cancel', [OrderController::class, 'cancel'])
                ->middleware(['tenant', 'perm:orders,cancel', 'throttle:30,1,orders-cancel']);

            // Fila de aprovação do staff (Delivery Fase 1) — todo pedido da
            // loja (origin=storefront) nasce pending_approval e precisa
            // passar por aqui. Reaproveita perm:orders,update (mesma
            // permissão já usada na gestão manual de parcela/itens).
            Route::post('/{order}/approve', [OrderController::class, 'approve'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:60,1,orders-approve']);

            Route::post('/{order}/reject', [OrderController::class, 'reject'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:60,1,orders-reject']);

            // Aprovação/rejeição da solicitação de cancelamento feita pelo
            // cliente final via Portal (roadmap A4) — reaproveita
            // perm:orders,update (mesma permissão já usada em approve/reject
            // acima). approve executa cancel() de fato (estoque/estorno);
            // reject só reverte o status, nada foi executado ainda.
            Route::post('/{order}/approve-cancellation', [OrderController::class, 'approveCancellation'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:60,1,orders-approve-cancellation']);

            Route::post('/{order}/reject-cancellation', [OrderController::class, 'rejectCancellation'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:60,1,orders-reject-cancellation']);

            Route::get('/{order}/workflow-transitions', [WorkflowTransitionLogController::class, 'order'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:120,1,orders-workflow-transitions']);
        });

        // Tela dedicada de gestão de pedidos da loja (origin=storefront) —
        // permissão própria (storefront-orders,*), independente de
        // perm:orders,*. Reaproveita os MESMOS métodos de OrderController
        // onde a regra de negócio já existe (approve/reject/cancel/deliver);
        // dispatch()/indexStorefront() são os únicos métodos novos. Ver
        // .claude/memory/architecture-decisions.md.
        Route::prefix('storefront-orders')->group(function () {
            Route::get('/', [OrderController::class, 'indexStorefront'])
                ->middleware(['tenant', 'perm:storefront-orders,read', 'throttle:100,1,storefront-orders-list']);

            Route::get('/{order}', [OrderController::class, 'show'])
                ->middleware(['tenant', 'perm:storefront-orders,read', 'throttle:100,1,storefront-orders-show']);

            // Gera o link temporário de preparo (QR code) — mesma permissão
            // de leitura (só quem vê o pedido gera o link).
            Route::post('/{order}/prep-link', [OrderController::class, 'prepLink'])
                ->middleware(['tenant', 'perm:storefront-orders,read', 'throttle:20,1,storefront-orders-prep-link']);

            Route::post('/{order}/approve', [OrderController::class, 'approve'])
                ->middleware(['tenant', 'perm:storefront-orders,approve', 'throttle:60,1,storefront-orders-approve']);

            Route::post('/{order}/reject', [OrderController::class, 'reject'])
                ->middleware(['tenant', 'perm:storefront-orders,approve', 'throttle:60,1,storefront-orders-reject']);

            Route::patch('/{order}/cancel', [OrderController::class, 'cancel'])
                ->middleware(['tenant', 'perm:storefront-orders,cancel', 'throttle:30,1,storefront-orders-cancel']);

            Route::patch('/{order}/dispatch', [OrderController::class, 'dispatch'])
                ->middleware(['tenant', 'perm:storefront-orders,dispatch', 'throttle:30,1,storefront-orders-dispatch']);

            // Desfaz "saiu para entrega" — método novo (undispatch), só
            // exposto aqui na tela da loja.
            Route::patch('/{order}/undispatch', [OrderController::class, 'undispatch'])
                ->middleware(['tenant', 'perm:storefront-orders,undispatch', 'throttle:30,1,storefront-orders-undispatch']);

            Route::patch('/{order}/deliver', [OrderController::class, 'deliver'])
                ->middleware(['tenant', 'perm:storefront-orders,deliver', 'throttle:30,1,storefront-orders-deliver']);

            // Reaproveitam os MESMOS métodos de OrderController (undeliver/
            // pay) já usados pelas rotas /orders/*, só com a permissão
            // isolada da tela da loja. pay sem `amount` no body = pagamento
            // integral (a tela da loja nunca faz pagamento parcial).
            Route::patch('/{order}/undeliver', [OrderController::class, 'undeliver'])
                ->middleware(['tenant', 'perm:storefront-orders,undeliver', 'throttle:30,1,storefront-orders-undeliver']);

            Route::patch('/{order}/pay', [OrderController::class, 'pay'])
                ->middleware(['tenant', 'perm:storefront-orders,pay', 'throttle:30,1,storefront-orders-pay']);

            Route::get('/{order}/workflow-transitions', [WorkflowTransitionLogController::class, 'order'])
                ->middleware(['tenant', 'perm:storefront-orders,read', 'throttle:120,1,storefront-orders-workflow-transitions']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/indicators', [ReportController::class, 'indicators'])
                ->middleware(['tenant', 'perm:dashboard,read', 'throttle:60,1,reports-indicators']);

            Route::get('/charts', [ReportController::class, 'charts'])
                ->middleware(['tenant', 'perm:dashboard,read', 'throttle:60,1,reports-charts']);

            Route::get('/operation-health', [ReportController::class, 'operationHealth'])
                ->middleware(['tenant', 'perm:dashboard,read', 'throttle:60,1,reports-operation-health']);

            Route::get('/orders', [ReportController::class, 'orders'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-orders']);

            Route::get('/orders/summary', [ReportController::class, 'ordersSummary'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-orders-summary']);

            // Resultado por canal (roadmap A1.3) — drill-down até o pedido
            // via GET /orders?origin=X&date_from=Y&date_to=Z (já existente).
            Route::get('/by-channel', [ReportController::class, 'byChannel'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-by-channel']);

            // CMV real (roadmap A3.13) — custo médio ponderado a partir das
            // entradas de estoque com unit_cost preenchido.
            Route::get('/cmv', [ReportController::class, 'cmv'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-cmv']);

            Route::post('/orders/pdf', [ReportController::class, 'ordersPdf'])
                ->middleware(['tenant', 'perm:reports,export_pdf', 'throttle:10,1,reports-orders-pdf']);

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

                Route::get('/overdue-orders', [AnalyticsController::class, 'overdueOrders'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-overdue-orders']);

                Route::get('/abc-analysis', [AnalyticsController::class, 'abcAnalysis'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-abc-analysis']);

                Route::get('/margin-summary', [AnalyticsController::class, 'marginSummary'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-margin-summary']);

                Route::get('/coupon-roi', [AnalyticsController::class, 'couponRoi'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-coupon-roi']);

                Route::get('/revenue-concentration', [AnalyticsController::class, 'revenueConcentration'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-revenue-concentration']);

                Route::get('/delivery-otif', [AnalyticsController::class, 'deliveryOtif'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-delivery-otif']);

                Route::get('/churn-clients', [AnalyticsController::class, 'churnClients'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-churn-clients']);

                Route::get('/stalled-products', [AnalyticsController::class, 'stalledProducts'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-stalled-products']);

                Route::get('/stock-ruptures', [AnalyticsController::class, 'stockRuptures'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-stock-ruptures']);

                Route::get('/sales-by-hour', [AnalyticsController::class, 'salesByHour'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-sales-by-hour']);
            });
        });

        // Apoio à conciliação financeira (roadmap A3.12) — leitura agregada
        // de payments/refunds/webhook_events, já existentes desde o roadmap
        // 2A/1B. Functionality própria `finance`, reaproveita a action
        // `read` já existente. Ver App\Services\Finance\ReconciliationService.
        Route::prefix('finance')->group(function () {
            Route::get('/reconciliation', [ReconciliationController::class, 'index'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-reconciliation']);

            Route::get('/reconciliation/summary', [ReconciliationController::class, 'summary'])
                ->middleware(['tenant', 'perm:finance,read', 'throttle:60,1,finance-reconciliation-summary']);
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

        // Estornos do PRÓPRIO tenant (roadmap 2026-07-24) — pedido pago
        // cancelado, arrependimento de assinatura e contestação, todos
        // reunidos numa única visão para o proprietário (hoje espalhados
        // entre /orders e /subscription sem lista dedicada).
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
