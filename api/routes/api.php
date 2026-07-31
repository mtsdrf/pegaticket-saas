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
use App\Http\Controllers\Fiscal\FiscalOperationProfileController;
use App\Http\Controllers\Fiscal\FiscalReadinessController;
use App\Http\Controllers\Fiscal\TaxRuleController;
use App\Http\Controllers\Client\ClientCategoryController;
use App\Http\Controllers\Product\ProductCategoryController;
use App\Http\Controllers\Product\ProductCategoryPriceController;
use App\Http\Controllers\Product\ProductTypeController;
use App\Http\Controllers\Location\EstadoController;
use App\Http\Controllers\Location\CidadeController;
use App\Http\Controllers\Location\BairroController;
use App\Http\Controllers\Location\EnderecoController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Client\DiaIdealController;
use App\Http\Controllers\Client\PeriodoIdealController;
use App\Http\Controllers\TenantSettings\TenantSettingsController;
use App\Http\Controllers\Storefront\ReactivationRuleController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\ProductImportController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Stock\StockLocationController;
use App\Http\Controllers\Stock\StockMovementController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\OrderFiscalDocumentController;
use App\Http\Controllers\Order\OrderFiscalPreviewController;
use App\Http\Controllers\Order\OrderInstallmentController;
use App\Http\Controllers\Order\OrderTrackingController;
use App\Http\Controllers\Order\OrderPrepViewController;
use App\Http\Controllers\Pdv\CashSessionController;
use App\Http\Controllers\Pdv\PdvOfflineSnapshotController;
use App\Http\Controllers\Pdv\PdvSaleController;
use App\Http\Controllers\Pdv\OperatorPinController;
use App\Http\Controllers\Balcao\StationController;
use App\Http\Controllers\Balcao\TableController;
use App\Http\Controllers\Balcao\ComandaController;
use App\Http\Controllers\Balcao\BalcaoOfflineSnapshotController;
use App\Http\Controllers\Balcao\TableReservationController;
use App\Http\Controllers\Balcao\TableWaitlistController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\ReceivableInteractionController;
use App\Http\Controllers\Report\AnalyticsController;
use App\Http\Controllers\Finance\ReconciliationController;
use App\Http\Controllers\Route\RouteCandidateController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalLinkController;
use App\Http\Controllers\Portal\PortalCashbackController;
use App\Http\Controllers\Portal\PortalAddressController;
use App\Http\Controllers\Portal\PortalCouponController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalFavoriteController;
use App\Http\Controllers\Portal\PushSubscriptionController;
use App\Http\Controllers\User\UserAvatarController;
use App\Http\Controllers\Product\ProductImageController;
use App\Http\Controllers\Tenant\TenantLogoController;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Controllers\Storefront\CartEventController;
use App\Http\Controllers\Storefront\StorefrontCheckoutController;
use App\Http\Controllers\Storefront\StorefrontManifestController;
use App\Http\Controllers\Storefront\StorefrontLocationController;
use App\Http\Controllers\Storefront\StorefrontTableReservationController;
use App\Http\Controllers\Storefront\StoreBusinessHoursController;
use App\Http\Controllers\Storefront\StoreAddressController;
use App\Http\Controllers\Storefront\StoreDeliveryFeeController;
use App\Http\Controllers\Storefront\CouponController;
use App\Http\Controllers\Storefront\ProductPromotionController;
use App\Http\Controllers\Accounting\AccountingAuthController;
use App\Http\Controllers\Accounting\AccountingAccessRequestController;
use App\Http\Controllers\Accounting\AccountingAccessApprovalController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\AccountingMessageController;
use App\Http\Controllers\Accounting\AccountingProductController;
use App\Http\Controllers\Accounting\AccountingClientController;
use App\Http\Controllers\Accounting\AccountingTaxRuleController;
use App\Http\Controllers\Accounting\TenantAccountingMessageController;
use App\Http\Controllers\Support\SupportTicketController;
use App\Http\Controllers\ApiKey\ApiKeyController;
use App\Http\Controllers\Marketplace\MarketplaceIntegrationController;
use App\Http\Controllers\Marketplace\MarketplaceWebhookController;
use App\Http\Controllers\Workflow\WorkflowTransitionLogController;
use App\Http\Controllers\Webhook\WebhookSubscriptionController;
use App\Http\Controllers\Public\PublicOrderController;
use App\Http\Controllers\Public\PublicProductController;

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

    // Webhook público de marketplace (Fase iFood) — nesta etapa a assinatura
    // exata do iFood ainda depende da confirmação integral do portal autenticado,
    // então a ingestão é deliberadamente defensiva e idempotente por
    // external_event_id. A URL já fica estável para a empresa configurar.
    Route::post('/webhooks/marketplace/ifood/{marketplaceIntegration:uuid}', [MarketplaceWebhookController::class, 'ifood'])
        ->name('marketplace.webhook.ifood')
        ->middleware('throttle:120,1,marketplace-ifood-webhook');

    // Acompanhamento de pedido (roadmap 5.1) — 100% público, sem
    // jwt/tenant/perm, protegido só pelo uuid do pedido ser imprevisível
    // (link enviado por WhatsApp na criação do pedido). Ver
    // App\Http\Controllers\Order\OrderTrackingController.
    Route::get('/rastreio/{order:uuid}', [OrderTrackingController::class, 'show'])
        ->middleware('throttle:60,1,order-tracking-public');

    // Tela de preparo do pedido no celular (roadmap Loja) — 100% pública,
    // sem jwt/tenant/customer.jwt, protegida só pelo token temporário
    // (OrderPrepLink) via ?token=. 404 genérico pra token errado/expirado/
    // de outro pedido/pedido inexistente (nunca revela existência). Ver
    // App\Http\Controllers\Order\OrderPrepViewController.
    Route::get('/storefront-orders/{uuid}/prep', [OrderPrepViewController::class, 'show'])
        ->middleware('throttle:20,1,storefront-order-prep-view');

    // Imagens guardadas em BLOB no banco (avatar/produto/logo) — antes eram
    // arquivo estático em /storage/*, sem passar por middleware nenhum;
    // agora são lidas do banco e servidas por rota de API, 100% pública
    // (mesma exposição de sempre, só sem depender de disco/symlink na
    // hospedagem compartilhada). Efeito colateral bom: por estarem sob
    // /api/v1, passam pelo HandleCors normal do Laravel, diferente do
    // arquivo estático de antes. Ver architecture-decisions.md.
    Route::get('/users/{user}/avatar', [UserAvatarController::class, 'show'])
        ->middleware('throttle:100,1,users-avatar-show');

    Route::get('/products/{product}/image', [ProductImageController::class, 'show'])
        ->middleware('throttle:100,1,products-image-show');

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
    Route::get('/loja/{slug}/produtos', [StorefrontController::class, 'products'])
        ->middleware(['customer.jwt.optional', 'throttle:100,1,storefront-products']);

    // Categorias com produto disponível (vitrine estilo iFood) — mesmo
    // espírito de /loja/{slug}/produtos.
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

    Route::get('/reservas/{slug}', [StorefrontTableReservationController::class, 'show'])
        ->middleware('throttle:60,1,public-table-reservation-show');

    Route::post('/reservas/{slug}', [StorefrontTableReservationController::class, 'storePublic'])
        ->middleware('throttle:30,1,public-table-reservation-store');

    Route::post('/loja/{slug}/reservas', [StorefrontTableReservationController::class, 'store'])
        ->middleware('throttle:30,1,storefront-table-reservation');

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

        // Favoritos de produto (roadmap Delivery, Fase 4 — retenção).
        // Toggle idempotente: favorito existente remove, inexistente cria.
        Route::post('/favorites/{product_uuid}/toggle', [PortalFavoriteController::class, 'toggle'])
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

        // Saldo de cashback (roadmap Delivery, Fase 5) — reaproveitado
        // também pelo checkout da loja (mesmo guard customer.jwt), ver
        // PortalCashbackController.
        Route::get('/cashback', [PortalCashbackController::class, 'index'])
            ->middleware('throttle:60,1,portal-cashback-balance');

        // "Meus endereços" (roadmap Loja) — edita o Client.endereco de cada
        // loja onde o cliente tem vínculo confirmado. Guard de posse por
        // client_uuid (404 se não pertencer ao cliente autenticado).
        Route::get('/addresses', [PortalAddressController::class, 'index'])
            ->middleware('throttle:60,1,portal-addresses-list');

        Route::put('/addresses/{client_uuid}', [PortalAddressController::class, 'update'])
            ->middleware('throttle:30,1,portal-addresses-update');

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

    // Módulo do contador (roadmap 2C) — identidade própria
    // (App\Models\Accounting\AccountingOffice), NÃO é User staff nem
    // FinalCustomer. TOTP obrigatório: login só funciona depois de
    // /accounting/totp/confirm. Rotas de cadastro/login públicas (sem jwt).
    Route::post('/accounting/register', [AccountingAuthController::class, 'register'])
        ->middleware('throttle:5,1,accounting-register');

    Route::post('/accounting/totp/confirm', [AccountingAuthController::class, 'confirmTotp'])
        ->middleware('throttle:10,1,accounting-totp-confirm');

    Route::post('/accounting/login', [AccountingAuthController::class, 'login'])
        ->middleware('throttle:5,1,accounting-login');

    // Autenticado como AccountingOffice via `accounting.jwt`.
    Route::middleware(['accounting.jwt'])->prefix('accounting')->group(function () {

        Route::get('/me', [AccountingAuthController::class, 'me'])
            ->middleware('throttle:60,1,accounting-me');

        // Solicitações de acesso a tenants (lado do contador).
        Route::post('/access-requests', [AccountingAccessRequestController::class, 'store'])
            ->middleware('throttle:20,1,accounting-access-request');

        Route::get('/access-requests', [AccountingAccessRequestController::class, 'index'])
            ->middleware('throttle:60,1,accounting-access-list');

        // Consultas escopadas a um tenant específico (vínculo aprovado
        // resolvido por `accounting.tenant` a partir do path param).
        Route::middleware(['accounting.tenant'])->prefix('tenants/{tenant_uuid}')->group(function () {

            Route::get('/reports/sales', [AccountingReportController::class, 'sales'])
                ->middleware('throttle:60,1,accounting-report-sales');

            Route::get('/reports/cash-flow', [AccountingReportController::class, 'cashFlow'])
                ->middleware('throttle:60,1,accounting-report-cashflow');

            Route::get('/reports/dre', [AccountingReportController::class, 'dre'])
                ->middleware('throttle:60,1,accounting-report-dre');

            Route::get('/messages', [AccountingMessageController::class, 'index'])
                ->middleware('throttle:60,1,accounting-messages-list');

            Route::post('/messages', [AccountingMessageController::class, 'store'])
                ->middleware('throttle:30,1,accounting-messages-create');

            Route::get('/products', [AccountingProductController::class, 'index'])
                ->middleware(['accounting.scope:fiscal.read,fiscal.write', 'throttle:60,1,accounting-products-list']);

            Route::put('/products/{product}', [AccountingProductController::class, 'updateFiscal'])
                ->middleware(['accounting.scope:fiscal.write', 'throttle:30,1,accounting-products-update-fiscal']);

            Route::get('/clients', [AccountingClientController::class, 'index'])
                ->middleware(['accounting.scope:fiscal.read,fiscal.write', 'throttle:60,1,accounting-clients-list']);

            Route::put('/clients/{client}', [AccountingClientController::class, 'updateFiscal'])
                ->middleware(['accounting.scope:fiscal.write', 'throttle:30,1,accounting-clients-update-fiscal']);

            Route::get('/tax-rules', [AccountingTaxRuleController::class, 'index'])
                ->middleware(['accounting.scope:fiscal.read,fiscal.write', 'throttle:60,1,accounting-tax-rules-list']);

            Route::post('/tax-rules', [AccountingTaxRuleController::class, 'store'])
                ->middleware(['accounting.scope:fiscal.write', 'throttle:30,1,accounting-tax-rules-store']);

            Route::put('/tax-rules/{uuid}', [AccountingTaxRuleController::class, 'update'])
                ->middleware(['accounting.scope:fiscal.write', 'throttle:30,1,accounting-tax-rules-update']);

            Route::delete('/tax-rules/{uuid}', [AccountingTaxRuleController::class, 'destroy'])
                ->middleware(['accounting.scope:fiscal.write', 'throttle:30,1,accounting-tax-rules-delete']);
        });
    });

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
        // — visão cross-tenant EXCLUSIVA do staff interno da Maskats, mesmo
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

        Route::prefix('client-categories')->group(function () {
            Route::get('/', [ClientCategoryController::class, 'index'])
                ->middleware(['tenant', 'perm:client_categories,read', 'throttle:100,1,client-categories-list']);

            Route::post('/', [ClientCategoryController::class, 'store'])
                ->middleware(['tenant', 'perm:client_categories,create', 'throttle:30,1,client-categories-create']);

            Route::put('/{clientCategory}', [ClientCategoryController::class, 'update'])
                ->middleware(['tenant', 'perm:client_categories,update', 'throttle:30,1,client-categories-update']);

            Route::delete('/{clientCategory}', [ClientCategoryController::class, 'destroy'])
                ->middleware(['tenant', 'perm:client_categories,delete', 'throttle:10,1,client-categories-delete']);
        });

        Route::prefix('product-categories')->group(function () {
            Route::get('/', [ProductCategoryController::class, 'index'])
                ->middleware(['tenant', 'perm:product_categories,read', 'throttle:100,1,product-categories-list']);

            Route::post('/', [ProductCategoryController::class, 'store'])
                ->middleware(['tenant', 'perm:product_categories,create', 'throttle:30,1,product-categories-create']);

            Route::put('/{productCategory}', [ProductCategoryController::class, 'update'])
                ->middleware(['tenant', 'perm:product_categories,update', 'throttle:30,1,product-categories-update']);

            Route::delete('/{productCategory}', [ProductCategoryController::class, 'destroy'])
                ->middleware(['tenant', 'perm:product_categories,delete', 'throttle:10,1,product-categories-delete']);
        });

        Route::prefix('product-types')->group(function () {
            Route::get('/', [ProductTypeController::class, 'index'])
                ->middleware(['tenant', 'perm:product_types,read', 'throttle:100,1,product-types-list']);

            Route::post('/', [ProductTypeController::class, 'store'])
                ->middleware(['tenant', 'perm:product_types,create', 'throttle:30,1,product-types-create']);

            Route::put('/{productType}', [ProductTypeController::class, 'update'])
                ->middleware(['tenant', 'perm:product_types,update', 'throttle:30,1,product-types-update']);

            Route::delete('/{productType}', [ProductTypeController::class, 'destroy'])
                ->middleware(['tenant', 'perm:product_types,delete', 'throttle:10,1,product-types-delete']);
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

        Route::prefix('dias-ideais')->group(function () {
            Route::get('/', [DiaIdealController::class, 'index'])
                ->middleware(['tenant', 'perm:dias_ideais,read', 'throttle:100,1,dias-ideais-list']);

            Route::post('/', [DiaIdealController::class, 'store'])
                ->middleware(['tenant', 'perm:dias_ideais,create', 'throttle:30,1,dias-ideais-create']);

            Route::put('/{diaIdeal}', [DiaIdealController::class, 'update'])
                ->middleware(['tenant', 'perm:dias_ideais,update', 'throttle:30,1,dias-ideais-update']);

            Route::delete('/{diaIdeal}', [DiaIdealController::class, 'destroy'])
                ->middleware(['tenant', 'perm:dias_ideais,delete', 'throttle:10,1,dias-ideais-delete']);
        });

        Route::prefix('periodos-ideais')->group(function () {
            Route::get('/', [PeriodoIdealController::class, 'index'])
                ->middleware(['tenant', 'perm:periodos_ideais,read', 'throttle:100,1,periodos-ideais-list']);

            Route::post('/', [PeriodoIdealController::class, 'store'])
                ->middleware(['tenant', 'perm:periodos_ideais,create', 'throttle:30,1,periodos-ideais-create']);

            Route::put('/{periodoIdeal}', [PeriodoIdealController::class, 'update'])
                ->middleware(['tenant', 'perm:periodos_ideais,update', 'throttle:30,1,periodos-ideais-update']);

            Route::delete('/{periodoIdeal}', [PeriodoIdealController::class, 'destroy'])
                ->middleware(['tenant', 'perm:periodos_ideais,delete', 'throttle:10,1,periodos-ideais-delete']);
        });

        Route::prefix('tenant-settings')->group(function () {
            Route::get('/', [TenantSettingsController::class, 'show'])
                ->middleware(['tenant', 'perm:tenant_settings,read', 'throttle:100,1,tenant-settings-show']);

            Route::put('/', [TenantSettingsController::class, 'update'])
                ->middleware(['tenant', 'perm:tenant_settings,update', 'throttle:30,1,tenant-settings-update']);
        });

        // Régua de reativação de cliente (roadmap A5, item 18) — 1 regra
        // por tenant, mesmo padrão singleton de tenant-settings.
        Route::prefix('reactivation-rule')->group(function () {
            Route::get('/', [ReactivationRuleController::class, 'show'])
                ->middleware(['tenant', 'perm:reactivation,read', 'throttle:100,1,reactivation-rule-show']);

            Route::put('/', [ReactivationRuleController::class, 'update'])
                ->middleware(['tenant', 'perm:reactivation,update', 'throttle:30,1,reactivation-rule-update']);
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

        // Configuração de horário de funcionamento da loja (Delivery Fase 2)
        // — sempre exatamente 7 linhas por tenant (get/replace em lote, não
        // é CRUD genérico). Ver App\Services\Storefront\StoreBusinessHoursService.
        Route::prefix('store-settings/business-hours')->group(function () {
            Route::get('/', [StoreBusinessHoursController::class, 'show'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:100,1,store-business-hours-show']);

            Route::put('/', [StoreBusinessHoursController::class, 'update'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,store-business-hours-update']);
        });

        // Endereço próprio da empresa (loja pública) — reaproveita o model
        // genérico Endereco via tenants.endereco_id. Ver
        // App\Services\Storefront\StoreAddressService.
        Route::prefix('store-settings/address')->group(function () {
            Route::get('/', [StoreAddressController::class, 'show'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:100,1,store-address-show']);

            Route::put('/', [StoreAddressController::class, 'update'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,store-address-update']);
        });

        // Taxa de entrega por bairro (Delivery Fase 2) — CRUD normal.
        // Ver App\Services\Storefront\StoreDeliveryFeeService.
        Route::prefix('store-delivery-fees')->group(function () {
            Route::get('/', [StoreDeliveryFeeController::class, 'index'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:100,1,store-delivery-fees-list']);

            Route::post('/', [StoreDeliveryFeeController::class, 'store'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,store-delivery-fees-create']);

            Route::delete('/{uuid}', [StoreDeliveryFeeController::class, 'destroy'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:10,1,store-delivery-fees-delete']);
        });

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

        // Regras tributárias parametrizadas e versionadas (roadmap Fiscal D0)
        // — CRUD tenant-scoped, cada tenant define AS SUAS regras. Sem motor
        // de cálculo ainda. Ver App\Services\Fiscal\TaxRuleService.
        Route::prefix('tax-rules')->group(function () {
            Route::get('/', [TaxRuleController::class, 'index'])
                ->middleware(['tenant', 'perm:tax-rules,read', 'throttle:100,1,tax-rules-list']);

            Route::post('/', [TaxRuleController::class, 'store'])
                ->middleware(['tenant', 'perm:tax-rules,create', 'throttle:30,1,tax-rules-create']);

            Route::put('/{uuid}', [TaxRuleController::class, 'update'])
                ->middleware(['tenant', 'perm:tax-rules,update', 'throttle:30,1,tax-rules-update']);

            Route::delete('/{uuid}', [TaxRuleController::class, 'destroy'])
                ->middleware(['tenant', 'perm:tax-rules,delete', 'throttle:10,1,tax-rules-delete']);
        });

        Route::prefix('fiscal-operation-profiles')->group(function () {
            Route::get('/', [FiscalOperationProfileController::class, 'index'])
                ->middleware(['tenant', 'perm:tax-rules,read', 'throttle:100,1,fiscal-operation-profiles-list']);

            Route::post('/', [FiscalOperationProfileController::class, 'store'])
                ->middleware(['tenant', 'perm:tax-rules,create', 'throttle:30,1,fiscal-operation-profiles-create']);

            Route::put('/{uuid}', [FiscalOperationProfileController::class, 'update'])
                ->middleware(['tenant', 'perm:tax-rules,update', 'throttle:30,1,fiscal-operation-profiles-update']);

            Route::delete('/{uuid}', [FiscalOperationProfileController::class, 'destroy'])
                ->middleware(['tenant', 'perm:tax-rules,delete', 'throttle:10,1,fiscal-operation-profiles-delete']);
        });

        Route::get('/fiscal-readiness', [FiscalReadinessController::class, 'show'])
            ->middleware(['tenant', 'perm:tax-rules,read', 'throttle:60,1,fiscal-readiness-show']);

        // Acesso do contador — lado do TENANT (roadmap 2C). Dono aprova/revoga
        // solicitações e conversa na central de pendências. `perm:accounting-access`
        // só é concedida ao papel `owner` por padrão (via provisionamento).
        Route::prefix('accounting-access-requests')->group(function () {
            Route::get('/', [AccountingAccessApprovalController::class, 'index'])
                ->middleware(['tenant', 'perm:accounting-access,read', 'throttle:60,1,accounting-access-requests-list']);

            Route::post('/{uuid}/approve', [AccountingAccessApprovalController::class, 'approve'])
                ->middleware(['tenant', 'perm:accounting-access,approve', 'throttle:30,1,accounting-access-approve']);

            Route::post('/{uuid}/revoke', [AccountingAccessApprovalController::class, 'revoke'])
                ->middleware(['tenant', 'perm:accounting-access,revoke', 'throttle:30,1,accounting-access-revoke']);

            Route::get('/{uuid}/messages', [TenantAccountingMessageController::class, 'index'])
                ->middleware(['tenant', 'perm:accounting-access,read', 'throttle:60,1,accounting-access-messages-list']);

            Route::post('/{uuid}/messages', [TenantAccountingMessageController::class, 'store'])
                ->middleware(['tenant', 'perm:accounting-access,create', 'throttle:30,1,accounting-access-messages-create']);
        });

        // Preço promocional "de/por" por produto (Delivery Fase 3) — upsert
        // 1 por produto, mesmo shape de store-delivery-fees. Ver
        // App\Services\Storefront\ProductPromotionService.
        Route::prefix('product-promotions')->group(function () {
            Route::get('/', [ProductPromotionController::class, 'index'])
                ->middleware(['tenant', 'perm:storefront,read', 'throttle:100,1,product-promotions-list']);

            Route::post('/', [ProductPromotionController::class, 'store'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:30,1,product-promotions-create']);

            Route::delete('/{uuid}', [ProductPromotionController::class, 'destroy'])
                ->middleware(['tenant', 'perm:storefront,update', 'throttle:10,1,product-promotions-delete']);
        });

        Route::prefix('clients')->group(function () {
            Route::get('/', [ClientController::class, 'index'])
                ->middleware(['tenant', 'perm:clients,read', 'throttle:100,1,clients-list']);

            Route::post('/', [ClientController::class, 'store'])
                ->middleware(['tenant', 'perm:clients,create', 'throttle:30,1,clients-create']);

            Route::get('/{client}', [ClientController::class, 'show'])
                ->middleware(['tenant', 'perm:clients,read', 'throttle:100,1,clients-show']);

            Route::put('/{client}', [ClientController::class, 'update'])
                ->middleware(['tenant', 'perm:clients,update', 'throttle:30,1,clients-update']);

            Route::delete('/{client}', [ClientController::class, 'destroy'])
                ->middleware(['tenant', 'perm:clients,delete', 'throttle:10,1,clients-delete']);

            Route::post('/{client}/categories/sync', [ClientController::class, 'syncCategories'])
                ->middleware(['tenant', 'perm:clients,update', 'throttle:20,1,clients-sync-categories']);

            // Diretório de clientes (cadastro completo, endereço inteiro) —
            // não confundir com /reports/clients/pdf, que é o relatório
            // financeiro de clientes em dia.
            Route::post('/export-pdf', [ClientController::class, 'pdf'])
                ->middleware(['tenant', 'perm:clients,read', 'throttle:20,1,clients-export-pdf']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])
                ->middleware(['tenant', 'perm:products,read', 'throttle:100,1,products-list']);

            Route::post('/', [ProductController::class, 'store'])
                ->middleware(['tenant', 'perm:products,create', 'throttle:30,1,products-create']);

            Route::get('/{product}', [ProductController::class, 'show'])
                ->middleware(['tenant', 'perm:products,read', 'throttle:100,1,products-show']);

            Route::put('/{product}', [ProductController::class, 'update'])
                ->middleware(['tenant', 'perm:products,update', 'throttle:30,1,products-update']);

            Route::delete('/{product}', [ProductController::class, 'destroy'])
                ->middleware(['tenant', 'perm:products,delete', 'throttle:10,1,products-delete']);

            // Ação administrativa rápida no PWA (roadmap A4, item 16) —
            // bloquear/desbloquear produto sem o payload inteiro de update().
            Route::patch('/{product}/toggle-availability', [ProductController::class, 'toggleAvailability'])
                ->middleware(['tenant', 'perm:products,update', 'throttle:60,1,products-toggle-availability']);

            Route::get('/{product}/suggested-price', [ProductController::class, 'suggestedPrice'])
                ->middleware(['tenant', 'perm:products,read', 'throttle:100,1,products-suggested-price']);

            Route::get('/{product}/category-prices', [ProductCategoryPriceController::class, 'index'])
                ->middleware(['tenant', 'perm:products,read', 'throttle:100,1,products-category-prices-list']);

            Route::post('/{product}/category-prices/sync', [ProductCategoryPriceController::class, 'sync'])
                ->middleware(['tenant', 'perm:products,update', 'throttle:30,1,products-category-prices-sync']);

            // Catálogo completo em PDF pro cliente final, não paginado.
            Route::post('/pdf', [ProductController::class, 'pdf'])
                ->middleware(['tenant', 'perm:products,read', 'throttle:20,1,products-pdf']);

            // Importação de produto por planilha CSV (roadmap A2) — preview
            // não grava nada (só parsing/validação), throttle baixo por ser
            // operação pesada (até 2000 linhas); commit grava de fato,
            // reaproveita perm:products,create dos dois lados (preview é só
            // uma simulação do que create faria). Ver ProductImportService.
            Route::post('/import/preview', [ProductImportController::class, 'preview'])
                ->middleware(['tenant', 'perm:products,create', 'throttle:10,1,products-import-preview']);

            Route::post('/import/commit', [ProductImportController::class, 'commit'])
                ->middleware(['tenant', 'perm:products,create', 'throttle:5,1,products-import-commit']);
        });

        Route::prefix('stock-locations')->group(function () {
            Route::get('/', [StockLocationController::class, 'index'])
                ->middleware(['tenant', 'perm:stock_locations,read', 'throttle:100,1,stock-locations-list']);

            Route::post('/', [StockLocationController::class, 'store'])
                ->middleware(['tenant', 'perm:stock_locations,create', 'throttle:30,1,stock-locations-create']);

            Route::put('/{stockLocation}', [StockLocationController::class, 'update'])
                ->middleware(['tenant', 'perm:stock_locations,update', 'throttle:30,1,stock-locations-update']);

            Route::delete('/{stockLocation}', [StockLocationController::class, 'destroy'])
                ->middleware(['tenant', 'perm:stock_locations,delete', 'throttle:10,1,stock-locations-delete']);
        });

        Route::prefix('stock')->group(function () {
            Route::get('/balances', [StockMovementController::class, 'balances'])
                ->middleware(['tenant', 'perm:stock,read', 'throttle:100,1,stock-balances-list']);

            Route::get('/movements', [StockMovementController::class, 'movements'])
                ->middleware(['tenant', 'perm:stock,read', 'throttle:100,1,stock-movements-list']);

            Route::post('/movements/entry', [StockMovementController::class, 'entry'])
                ->middleware(['tenant', 'perm:stock,entry', 'throttle:60,1,stock-movements-entry']);

            Route::post('/movements/exit', [StockMovementController::class, 'exit'])
                ->middleware(['tenant', 'perm:stock,exit', 'throttle:60,1,stock-movements-exit']);

            Route::post('/movements/adjustment', [StockMovementController::class, 'adjustment'])
                ->middleware(['tenant', 'perm:stock,adjustment', 'throttle:60,1,stock-movements-adjustment']);

            Route::post('/movements/transfer', [StockMovementController::class, 'transfer'])
                ->middleware(['tenant', 'perm:stock,transfer', 'throttle:60,1,stock-movements-transfer']);

            Route::post('/movements/return', [StockMovementController::class, 'returnMovement'])
                ->middleware(['tenant', 'perm:stock,entry', 'throttle:60,1,stock-movements-return']);

            Route::post('/movements/loss', [StockMovementController::class, 'loss'])
                ->middleware(['tenant', 'perm:stock,exit', 'throttle:60,1,stock-movements-loss']);

            Route::post('/movements/block', [StockMovementController::class, 'block'])
                ->middleware(['tenant', 'perm:stock,block', 'throttle:60,1,stock-movements-block']);

            Route::post('/movements/unblock', [StockMovementController::class, 'unblock'])
                ->middleware(['tenant', 'perm:stock,block', 'throttle:60,1,stock-movements-unblock']);

            Route::post('/movements/reserve', [StockMovementController::class, 'reserve'])
                ->middleware(['tenant', 'perm:stock,reserve', 'throttle:60,1,stock-movements-reserve']);

            Route::post('/movements/reserve-cancel', [StockMovementController::class, 'reserveCancel'])
                ->middleware(['tenant', 'perm:stock,reserve', 'throttle:60,1,stock-movements-reserve-cancel']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:100,1,orders-list']);

            Route::post('/', [OrderController::class, 'store'])
                ->middleware(['tenant', 'perm:orders,create', 'throttle:30,1,orders-create']);

            Route::get('/{order}', [OrderController::class, 'show'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:100,1,orders-show']);

            Route::get('/{order}/fiscal-preview', [OrderFiscalPreviewController::class, 'show'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:60,1,orders-fiscal-preview']);

            Route::get('/{order}/fiscal-document', [OrderFiscalDocumentController::class, 'show'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:60,1,orders-fiscal-document-show']);

            Route::get('/{order}/fiscal-document/xml-preview', [OrderFiscalDocumentController::class, 'xmlPreview'])
                ->middleware(['tenant', 'perm:orders,read', 'throttle:30,1,orders-fiscal-document-xml-preview']);

            Route::post('/{order}/fiscal-document', [OrderFiscalDocumentController::class, 'store'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:20,1,orders-fiscal-document-store']);

            Route::post('/{order}/fiscal-document/submit', [OrderFiscalDocumentController::class, 'submit'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:20,1,orders-fiscal-document-submit']);

            Route::post('/{order}/fiscal-document/sync-status', [OrderFiscalDocumentController::class, 'syncStatus'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:20,1,orders-fiscal-document-sync-status']);

            Route::post('/{order}/fiscal-document/cancel', [OrderFiscalDocumentController::class, 'cancel'])
                ->middleware(['tenant', 'perm:orders,update', 'throttle:20,1,orders-fiscal-document-cancel']);

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

        // PDV — caixa (abertura/fechamento/sangria/suprimento) + venda rápida
        // de balcão (roadmap PDV, Fase PDV-1). Functionality própria `pdv`,
        // operada pelo STAFF do tenant (reaproveita a sessão já autenticada,
        // sem identidade nova). Pagamento no PDV-1 é sempre "declarado"
        // (múltiplas linhas `payments` status=paid na hora). Ver
        // App\Services\Pdv\CashSessionService / PdvSaleService.
        Route::prefix('pdv')->group(function () {
            Route::get('/offline-snapshot', [PdvOfflineSnapshotController::class, 'show'])
                ->middleware(['tenant', 'perm:pdv,read', 'throttle:30,1,pdv-offline-snapshot']);

            Route::get('/cash-sessions', [CashSessionController::class, 'index'])
                ->middleware(['tenant', 'perm:pdv,read', 'throttle:100,1,pdv-cash-sessions-list']);

            Route::get('/cash-sessions/current', [CashSessionController::class, 'current'])
                ->middleware(['tenant', 'perm:pdv,read', 'throttle:120,1,pdv-cash-sessions-current']);

            Route::post('/cash-sessions', [CashSessionController::class, 'store'])
                ->middleware(['tenant', 'perm:pdv,open', 'throttle:30,1,pdv-cash-sessions-open']);

            Route::post('/cash-sessions/{uuid}/movements', [CashSessionController::class, 'movements'])
                ->middleware(['tenant', 'perm:pdv,movement', 'throttle:60,1,pdv-cash-sessions-movements']);

            Route::post('/cash-sessions/{uuid}/close', [CashSessionController::class, 'close'])
                ->middleware(['tenant', 'perm:pdv,close', 'throttle:30,1,pdv-cash-sessions-close']);

            Route::post('/sales', [PdvSaleController::class, 'store'])
                ->middleware(['tenant', 'perm:pdv,sell', 'throttle:120,1,pdv-sales-create']);

            // PIN de operador (roadmap A4, item 15) — camada de identificação
            // DENTRO da sessão de staff já autenticada, não uma perm de
            // `pdv` (é ação sobre o próprio operador / verificação de PIN,
            // não sobre o recurso PDV em si). Ver App\Services\Pdv\UserPinService.
            Route::put('/operator-pin', [OperatorPinController::class, 'setOwnPin'])
                ->middleware(['tenant', 'perm:pdv,read', 'throttle:10,1,pdv-operator-pin-set']);

            Route::post('/operator-session', [OperatorPinController::class, 'resolve'])
                ->middleware(['tenant', 'perm:pdv,read', 'throttle:20,1,pdv-operator-session']);
        });

        // Balcão — mesa/comanda/cozinha/bar + fechamento (roadmap Balcão,
        // Fases 1+2). Agregado próprio (Station/Table/Comanda/ComandaItem) que
        // vive o ciclo da mesa; o Order (origin='counter') só é materializado
        // no fechamento, reaproveitando a infra de pedido/pagamento do PDV.
        // Functionality própria `balcao`, operada pelo STAFF do tenant.
        Route::prefix('balcao')->group(function () {
            Route::get('/offline-snapshot', [BalcaoOfflineSnapshotController::class, 'show'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:30,1,balcao-offline-snapshot']);

            // CRUD de estações (cozinha/bar/chapa).
            Route::get('/stations', [StationController::class, 'index'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-stations-list']);
            Route::post('/stations', [StationController::class, 'store'])
                ->middleware(['tenant', 'perm:balcao,create', 'throttle:30,1,balcao-stations-create']);
            Route::put('/stations/{station}', [StationController::class, 'update'])
                ->middleware(['tenant', 'perm:balcao,update', 'throttle:30,1,balcao-stations-update']);
            Route::delete('/stations/{station}', [StationController::class, 'destroy'])
                ->middleware(['tenant', 'perm:balcao,delete', 'throttle:30,1,balcao-stations-delete']);

            // Fila do KDS de uma estação (polling).
            Route::get('/stations/{station}/tickets', [StationController::class, 'tickets'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:240,1,balcao-station-tickets']);

            // CRUD de mesas.
            Route::get('/tables', [TableController::class, 'index'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-tables-list']);
            Route::post('/tables', [TableController::class, 'store'])
                ->middleware(['tenant', 'perm:balcao,create', 'throttle:30,1,balcao-tables-create']);
            Route::put('/tables/{table}', [TableController::class, 'update'])
                ->middleware(['tenant', 'perm:balcao,update', 'throttle:30,1,balcao-tables-update']);
            Route::delete('/tables/{table}', [TableController::class, 'destroy'])
                ->middleware(['tenant', 'perm:balcao,delete', 'throttle:30,1,balcao-tables-delete']);

            Route::get('/reservas', [TableReservationController::class, 'index'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-reservations-list']);
            Route::get('/reservas/disponibilidade', [TableReservationController::class, 'availability'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-reservations-availability']);
            Route::post('/reservas', [TableReservationController::class, 'store'])
                ->middleware(['tenant', 'perm:balcao,create', 'throttle:30,1,balcao-reservations-create']);
            Route::post('/reservas/{uuid}/seat', [TableReservationController::class, 'seat'])
                ->middleware(['tenant', 'perm:balcao,open', 'throttle:30,1,balcao-reservations-seat']);
            Route::post('/reservas/{uuid}/cancel', [TableReservationController::class, 'cancel'])
                ->middleware(['tenant', 'perm:balcao,update', 'throttle:30,1,balcao-reservations-cancel']);
            Route::post('/reservas/{uuid}/no-show', [TableReservationController::class, 'noShow'])
                ->middleware(['tenant', 'perm:balcao,update', 'throttle:30,1,balcao-reservations-no-show']);

            Route::get('/fila-espera', [TableWaitlistController::class, 'index'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-waitlist-list']);
            Route::post('/fila-espera', [TableWaitlistController::class, 'store'])
                ->middleware(['tenant', 'perm:balcao,create', 'throttle:30,1,balcao-waitlist-create']);
            Route::post('/fila-espera/{uuid}/call', [TableWaitlistController::class, 'call'])
                ->middleware(['tenant', 'perm:balcao,update', 'throttle:30,1,balcao-waitlist-call']);
            Route::post('/fila-espera/{uuid}/seat', [TableWaitlistController::class, 'seat'])
                ->middleware(['tenant', 'perm:balcao,open', 'throttle:30,1,balcao-waitlist-seat']);
            Route::post('/fila-espera/{uuid}/cancel', [TableWaitlistController::class, 'cancel'])
                ->middleware(['tenant', 'perm:balcao,update', 'throttle:30,1,balcao-waitlist-cancel']);

            // Comandas.
            Route::get('/comandas', [ComandaController::class, 'index'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-comandas-list']);
            Route::post('/comandas', [ComandaController::class, 'store'])
                ->middleware(['tenant', 'perm:balcao,open', 'throttle:60,1,balcao-comandas-open']);
            Route::post('/comandas/{uuid}/items', [ComandaController::class, 'addItem'])
                ->middleware(['tenant', 'perm:balcao,add_item', 'throttle:240,1,balcao-comandas-add-item']);
            Route::patch('/comandas/{uuid}/items/{itemUuid}/prep-status', [ComandaController::class, 'updatePrepStatus'])
                ->middleware(['tenant', 'perm:balcao,prep', 'throttle:240,1,balcao-comandas-prep-status']);
            Route::get('/comandas/{uuid}/items/{itemUuid}/workflow-transitions', [WorkflowTransitionLogController::class, 'comandaItem'])
                ->middleware(['tenant', 'perm:balcao,read', 'throttle:120,1,balcao-comandas-workflow-transitions']);
            Route::post('/comandas/{uuid}/close', [ComandaController::class, 'close'])
                ->middleware(['tenant', 'perm:balcao,close', 'throttle:60,1,balcao-comandas-close']);
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

            Route::get('/clients', [ReportController::class, 'clients'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-clients']);

            Route::get('/receivables', [ReportController::class, 'receivables'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-receivables']);

            Route::get('/receivables/summary', [ReportController::class, 'receivablesSummary'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-receivables-summary']);

            // CMV real (roadmap A3.13) — custo médio ponderado a partir das
            // entradas de estoque com unit_cost preenchido.
            Route::get('/cmv', [ReportController::class, 'cmv'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-cmv']);

            Route::get('/receivables/{order}/interactions', [ReceivableInteractionController::class, 'index'])
                ->middleware(['tenant', 'perm:reports,read', 'throttle:60,1,reports-receivable-interactions']);

            Route::post('/receivables/{order}/interactions', [ReceivableInteractionController::class, 'store'])
                ->middleware(['tenant', 'perm:reports,update', 'throttle:30,1,reports-receivable-interactions-create']);

            Route::post('/orders/pdf', [ReportController::class, 'ordersPdf'])
                ->middleware(['tenant', 'perm:reports,export_pdf', 'throttle:10,1,reports-orders-pdf']);

            Route::post('/clients/pdf', [ReportController::class, 'clientsPdf'])
                ->middleware(['tenant', 'perm:reports,export_pdf', 'throttle:10,1,reports-clients-pdf']);

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

                Route::get('/cashback-liability', [AnalyticsController::class, 'cashbackLiability'])
                    ->middleware(['tenant', 'perm:analytics,read', 'throttle:60,1,analytics-cashback-liability']);

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

        // Rota de entrega/cobrança com mapa (Fase 3.2 do roadmap) —
        // Functionality própria `routes`, só nos planos Ouro/Diamante;
        // endpoint de leitura, otimização de rota (OSRM) é 100% frontend.
        Route::prefix('routes')->group(function () {
            Route::get('/candidates', [RouteCandidateController::class, 'index'])
                ->middleware(['tenant', 'perm:routes,read', 'throttle:60,1,routes-candidates']);
        });

        // Central de chamados nativa (roadmap A4, item 17) — reaproveita o
        // padrão de dado do módulo do contador (anexo opcional, status
        // simples). Ver App\Services\Support\SupportTicketService.
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportTicketController::class, 'index'])
                ->middleware(['tenant', 'perm:support,read', 'throttle:60,1,support-tickets-list']);

            Route::post('/tickets', [SupportTicketController::class, 'store'])
                ->middleware(['tenant', 'perm:support,create', 'throttle:20,1,support-tickets-create']);
        });

        // API pública + webhooks de saída (roadmap A6, item 20) — gestão de
        // API keys e webhook subscriptions pelo staff (JWT normal, mesma
        // Functionality 'api-access' pras duas coisas). A API key em texto
        // puro/secret do webhook só aparecem na resposta do respectivo
        // POST (nunca mais depois). Ver App\Services\ApiKey\ApiKeyService
        // e App\Services\Webhook\WebhookSubscriptionService.
        Route::prefix('api-keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,api-keys-list']);

            Route::post('/', [ApiKeyController::class, 'store'])
                ->middleware(['tenant', 'perm:api-access,create', 'throttle:10,1,api-keys-create']);

            Route::delete('/{apiKey:uuid}', [ApiKeyController::class, 'destroy'])
                ->middleware(['tenant', 'perm:api-access,delete', 'throttle:20,1,api-keys-delete']);
        });

        Route::prefix('webhook-subscriptions')->group(function () {
            Route::get('/', [WebhookSubscriptionController::class, 'index'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,webhook-subscriptions-list']);

            Route::post('/', [WebhookSubscriptionController::class, 'store'])
                ->middleware(['tenant', 'perm:api-access,create', 'throttle:20,1,webhook-subscriptions-create']);

            Route::get('/{webhookSubscription:uuid}', [WebhookSubscriptionController::class, 'show'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,webhook-subscriptions-show']);

            Route::put('/{webhookSubscription:uuid}', [WebhookSubscriptionController::class, 'update'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,webhook-subscriptions-update']);

            Route::delete('/{webhookSubscription:uuid}', [WebhookSubscriptionController::class, 'destroy'])
                ->middleware(['tenant', 'perm:api-access,delete', 'throttle:20,1,webhook-subscriptions-delete']);

            Route::get('/{webhookSubscription:uuid}/deliveries', [WebhookSubscriptionController::class, 'deliveries'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:30,1,webhook-subscriptions-deliveries']);
        });

        // Integrações de marketplace (Fase iFood) — por ora geridas no mesmo
        // escopo de "API e Webhooks" para evitar abrir outra functionality
        // antes de a frente estabilizar. O tenant configura a credencial,
        // sincroniza lojas, executa polling manual e opera ações externas do
        // pedido a partir do próprio Maskats.
        Route::prefix('marketplace')->group(function () {
            Route::get('/integrations', [MarketplaceIntegrationController::class, 'index'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,marketplace-integrations-list']);

            Route::post('/integrations', [MarketplaceIntegrationController::class, 'store'])
                ->middleware(['tenant', 'perm:api-access,create', 'throttle:10,1,marketplace-integrations-create']);

            Route::put('/integrations/{marketplaceIntegration:uuid}', [MarketplaceIntegrationController::class, 'update'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:10,1,marketplace-integrations-update']);

            Route::post('/integrations/{marketplaceIntegration:uuid}/sync-merchants', [MarketplaceIntegrationController::class, 'syncMerchants'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:10,1,marketplace-integrations-sync-merchants']);

            Route::post('/integrations/{marketplaceIntegration:uuid}/poll', [MarketplaceIntegrationController::class, 'poll'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-integrations-poll']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/events', [MarketplaceIntegrationController::class, 'events'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,marketplace-integrations-events']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/operations-summary', [MarketplaceIntegrationController::class, 'operationsSummary'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,marketplace-integrations-operations-summary']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/catalog/preview', [MarketplaceIntegrationController::class, 'catalogPreview'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:30,1,marketplace-integrations-catalog-preview']);

            Route::post('/integrations/{marketplaceIntegration:uuid}/catalog/sync', [MarketplaceIntegrationController::class, 'syncCatalog'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:10,1,marketplace-integrations-catalog-sync']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/catalog/syncs', [MarketplaceIntegrationController::class, 'catalogSyncs'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:30,1,marketplace-integrations-catalog-syncs']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/merchant-status', [MarketplaceIntegrationController::class, 'merchantStatus'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:30,1,marketplace-integrations-merchant-status']);

            Route::post('/integrations/{marketplaceIntegration:uuid}/interruptions', [MarketplaceIntegrationController::class, 'createInterruption'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:10,1,marketplace-integrations-interruptions-create']);

            Route::delete('/integrations/{marketplaceIntegration:uuid}/interruptions/{interruptionId}', [MarketplaceIntegrationController::class, 'deleteInterruption'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-integrations-interruptions-delete']);

            Route::post('/integrations/{marketplaceIntegration:uuid}/opening-hours/sync', [MarketplaceIntegrationController::class, 'syncOpeningHours'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:10,1,marketplace-integrations-opening-hours-sync']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/orders', [MarketplaceIntegrationController::class, 'orders'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,marketplace-integrations-orders']);

            Route::get('/orders/{marketplaceOrder:uuid}', [MarketplaceIntegrationController::class, 'showOrder'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,marketplace-orders-show']);

            Route::get('/orders/{marketplaceOrder:uuid}/cancellation-reasons', [MarketplaceIntegrationController::class, 'cancellationReasons'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:60,1,marketplace-order-cancellation-reasons']);

            Route::get('/integrations/{marketplaceIntegration:uuid}/health', [MarketplaceIntegrationController::class, 'health'])
                ->middleware(['tenant', 'perm:api-access,read', 'throttle:20,1,marketplace-integrations-health']);

            Route::post('/orders/{marketplaceOrder:uuid}/actions', [MarketplaceIntegrationController::class, 'performAction'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-order-actions']);

            Route::post('/orders/{marketplaceOrder:uuid}/import', [MarketplaceIntegrationController::class, 'importOrder'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-order-import']);

            Route::post('/orders/{marketplaceOrder:uuid}/refresh', [MarketplaceIntegrationController::class, 'refreshOrder'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-order-refresh']);

            Route::post('/events/{marketplaceEvent:uuid}/retry', [MarketplaceIntegrationController::class, 'retryEvent'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-event-retry']);

            Route::post('/catalog/syncs/{marketplaceCatalogSync:uuid}/refresh', [MarketplaceIntegrationController::class, 'refreshCatalogSync'])
                ->middleware(['tenant', 'perm:api-access,update', 'throttle:20,1,marketplace-catalog-sync-refresh']);
        });
    });

    // API pública (roadmap A6, item 20) — leitura de pedidos/produtos
    // autenticada por API key (`api.key`, não `jwt`+`tenant`). Escopo
    // inicial deliberadamente restrito a leitura (ver
    // App\Http\Controllers\Public\PublicOrderController/
    // PublicProductController — reaproveitam OrderService/ProductService
    // já usados no staff). Sem `perm:`: a posse da chave já autoriza.
    Route::prefix('public')->middleware('api.key')->group(function () {
        Route::get('/orders', [PublicOrderController::class, 'index'])
            ->middleware('throttle:60,1,public-orders-list');

        Route::get('/orders/{order:uuid}', [PublicOrderController::class, 'show'])
            ->middleware('throttle:100,1,public-orders-show');

        Route::get('/products', [PublicProductController::class, 'index'])
            ->middleware('throttle:60,1,public-products-list');
    });
});
