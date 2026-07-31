<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/*
|--------------------------------------------------------------------------
| Auth Events
|--------------------------------------------------------------------------
*/
use App\Events\Auth\LoginSucceeded;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\TokenRefreshed;
use App\Events\Auth\LogoutSucceeded;
use App\Events\Auth\LogoutFailed;
use App\Listeners\Audit\AuditAuthListener;

/*
|--------------------------------------------------------------------------
| Group Events
|--------------------------------------------------------------------------
*/
use App\Events\Group\GroupUsersSynced;
use App\Events\Group\GroupPermissionsSynced;
use App\Events\Group\AuditGroupCreated;
use App\Events\Group\AuditGroupUpdated;
use App\Events\Group\AuditGroupDeleted;
use App\Listeners\Audit\AuditGroupListener;

/*
|--------------------------------------------------------------------------
| User Events
|--------------------------------------------------------------------------
*/
use App\Events\User\UserCreated;
use App\Events\User\UserUpdated;
use App\Events\User\UserDeleted;
use App\Events\User\UserProfileUpdated;
use App\Events\User\UserPasswordChanged;
use App\Events\User\UserEmailChanged;
use App\Listeners\User\AuditUserCreated;
use App\Listeners\User\AuditUserUpdated;
use App\Listeners\User\AuditUserDeleted;
use App\Listeners\User\AuditUserProfileUpdated;
use App\Listeners\User\AuditUserPasswordChanged;
use App\Listeners\User\AuditUserEmailChanged;

/*
|--------------------------------------------------------------------------
| Functionality Events
|--------------------------------------------------------------------------
*/
use App\Events\Functionality\FunctionalityCreated;
use App\Events\Functionality\FunctionalityUpdated;
use App\Events\Functionality\FunctionalityDeleted;
use App\Listeners\Functionality\AuditFunctionalityCreated;
use App\Listeners\Functionality\AuditFunctionalityUpdated;
use App\Listeners\Functionality\AuditFunctionalityDeleted;

/*
|--------------------------------------------------------------------------
| Tenant Events
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantCreated;
use App\Events\Tenant\TenantUpdated;
use App\Events\Tenant\TenantDeleted;
use App\Listeners\Tenant\AuditTenantCreated;
use App\Listeners\Tenant\AuditTenantUpdated;
use App\Listeners\Tenant\AuditTenantDeleted;
use App\Listeners\Tenant\CreateDefaultStockLocation;

/*
|--------------------------------------------------------------------------
| Stock
|--------------------------------------------------------------------------
*/
use App\Events\Stock\StockLocationCreated;
use App\Events\Stock\StockLocationUpdated;
use App\Events\Stock\StockLocationDeleted;
use App\Events\Stock\StockMovementCreated;
use App\Listeners\Stock\AuditStockLocationCreated;
use App\Listeners\Stock\AuditStockLocationUpdated;
use App\Listeners\Stock\AuditStockLocationDeleted;
use App\Listeners\Stock\AuditStockMovementCreated;

/*
|--------------------------------------------------------------------------
| Tenant Roles
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantRoleCreated;
use App\Events\Tenant\TenantRoleUpdated;
use App\Events\Tenant\TenantRoleDeleted;
use App\Listeners\Tenant\AuditTenantRoleCreated;
use App\Listeners\Tenant\AuditTenantRoleUpdated;
use App\Listeners\Tenant\AuditTenantRoleDeleted;

/*
|--------------------------------------------------------------------------
| Tenant Role Permissions
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantRolePermissionsSynced;
use App\Listeners\Tenant\AuditTenantRolePermissionsSynced;
use App\Events\Tenant\TenantDataExported;
use App\Listeners\Tenant\AuditTenantDataExported;
use App\Events\Legal\ReleaseNoteCreated;
use App\Events\Legal\ReleaseNoteUpdated;
use App\Events\Legal\ReleaseNoteDeleted;
use App\Listeners\Legal\AuditReleaseNoteCreated;
use App\Listeners\Legal\AuditReleaseNoteUpdated;
use App\Listeners\Legal\AuditReleaseNoteDeleted;

/*
|--------------------------------------------------------------------------
| Tenant User
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantUserCreated;
use App\Events\Tenant\TenantUserUpdated;
use App\Events\Tenant\TenantUserDeleted;
use App\Listeners\Tenant\AuditTenantUserCreated;
use App\Listeners\Tenant\AuditTenantUserUpdated;
use App\Listeners\Tenant\AuditTenantUserDeleted;

/*
|--------------------------------------------------------------------------
| Tenant User Invite
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantUserInvited;
use App\Events\Tenant\TenantUserInviteAccepted;
use App\Listeners\Tenant\AuditTenantUserInvited;
use App\Listeners\Tenant\AuditTenantUserInviteAccepted;
use App\Events\Plan\PlanCreated;
use App\Events\Plan\PlanUpdated;
use App\Events\Plan\PlanDeleted;
use App\Events\Plan\PlanFunctionalitiesSynced;
use App\Listeners\Plan\AuditPlanCreated;
use App\Listeners\Plan\AuditPlanUpdated;
use App\Listeners\Plan\AuditPlanDeleted;
use App\Listeners\Plan\AuditPlanFunctionalitiesSynced;

/*
|--------------------------------------------------------------------------
| Client Category
|--------------------------------------------------------------------------
*/
use App\Events\Client\ClientCategoryCreated;
use App\Events\Client\ClientCategoryUpdated;
use App\Events\Client\ClientCategoryDeleted;
use App\Listeners\Client\AuditClientCategoryCreated;
use App\Listeners\Client\AuditClientCategoryUpdated;
use App\Listeners\Client\AuditClientCategoryDeleted;

/*
|--------------------------------------------------------------------------
| Client
|--------------------------------------------------------------------------
*/
use App\Events\Client\ClientCreated;
use App\Events\Client\ClientUpdated;
use App\Events\Client\ClientDeleted;
use App\Events\Client\ClientCategoriesSynced;
use App\Listeners\Client\AuditClientCreated;
use App\Listeners\Client\AuditClientUpdated;
use App\Listeners\Client\AuditClientDeleted;
use App\Listeners\Client\AuditClientCategoriesSynced;

/*
|--------------------------------------------------------------------------
| Product Category
|--------------------------------------------------------------------------
*/
use App\Events\Product\ProductCategoryCreated;
use App\Events\Product\ProductCategoryUpdated;
use App\Events\Product\ProductCategoryDeleted;
use App\Listeners\Product\AuditProductCategoryCreated;
use App\Listeners\Product\AuditProductCategoryUpdated;
use App\Listeners\Product\AuditProductCategoryDeleted;

/*
|--------------------------------------------------------------------------
| Product Type
|--------------------------------------------------------------------------
*/
use App\Events\Product\ProductTypeCreated;
use App\Events\Product\ProductTypeUpdated;
use App\Events\Product\ProductTypeDeleted;
use App\Listeners\Product\AuditProductTypeCreated;
use App\Listeners\Product\AuditProductTypeUpdated;
use App\Listeners\Product\AuditProductTypeDeleted;

/*
|--------------------------------------------------------------------------
| Product
|--------------------------------------------------------------------------
*/
use App\Events\Product\ProductCreated;
use App\Events\Product\ProductUpdated;
use App\Events\Product\ProductDeleted;
use App\Listeners\Product\AuditProductCreated;
use App\Listeners\Product\AuditProductUpdated;
use App\Listeners\Product\AuditProductDeleted;

/*
|--------------------------------------------------------------------------
| Product Import (roadmap A2) — evento agregado, ver ProductImportCommitted
|--------------------------------------------------------------------------
*/
use App\Events\Product\ProductImportCommitted;
use App\Listeners\Product\AuditProductImportCommitted;

/*
|--------------------------------------------------------------------------
| Product Category Prices
|--------------------------------------------------------------------------
*/
use App\Events\Product\ProductCategoryPricesSynced;
use App\Listeners\Product\AuditProductCategoryPricesSynced;

/*
|--------------------------------------------------------------------------
| Estado
|--------------------------------------------------------------------------
*/
use App\Events\Location\EstadoCreated;
use App\Events\Location\EstadoUpdated;
use App\Events\Location\EstadoDeleted;
use App\Listeners\Location\AuditEstadoCreated;
use App\Listeners\Location\AuditEstadoUpdated;
use App\Listeners\Location\AuditEstadoDeleted;

/*
|--------------------------------------------------------------------------
| Cidade
|--------------------------------------------------------------------------
*/
use App\Events\Location\CidadeCreated;
use App\Events\Location\CidadeUpdated;
use App\Events\Location\CidadeDeleted;
use App\Listeners\Location\AuditCidadeCreated;
use App\Listeners\Location\AuditCidadeUpdated;
use App\Listeners\Location\AuditCidadeDeleted;

/*
|--------------------------------------------------------------------------
| Bairro
|--------------------------------------------------------------------------
*/
use App\Events\Location\BairroCreated;
use App\Events\Location\BairroUpdated;
use App\Events\Location\BairroDeleted;
use App\Listeners\Location\AuditBairroCreated;
use App\Listeners\Location\AuditBairroUpdated;
use App\Listeners\Location\AuditBairroDeleted;

/*
|--------------------------------------------------------------------------
| Endereco
|--------------------------------------------------------------------------
*/
use App\Events\Location\EnderecoCreated;
use App\Events\Location\EnderecoUpdated;
use App\Events\Location\EnderecoDeleted;
use App\Listeners\Location\AuditEnderecoCreated;
use App\Listeners\Location\AuditEnderecoUpdated;
use App\Listeners\Location\AuditEnderecoDeleted;

/*
|--------------------------------------------------------------------------
| Dia Ideal
|--------------------------------------------------------------------------
*/
use App\Events\Client\DiaIdealCreated;
use App\Events\Client\DiaIdealUpdated;
use App\Events\Client\DiaIdealDeleted;
use App\Listeners\Client\AuditDiaIdealCreated;
use App\Listeners\Client\AuditDiaIdealUpdated;
use App\Listeners\Client\AuditDiaIdealDeleted;

/*
|--------------------------------------------------------------------------
| Periodo Ideal
|--------------------------------------------------------------------------
*/
use App\Events\Client\PeriodoIdealCreated;
use App\Events\Client\PeriodoIdealUpdated;
use App\Events\Client\PeriodoIdealDeleted;
use App\Listeners\Client\AuditPeriodoIdealCreated;
use App\Listeners\Client\AuditPeriodoIdealUpdated;
use App\Listeners\Client\AuditPeriodoIdealDeleted;

/*
|--------------------------------------------------------------------------
| Tenant Settings
|--------------------------------------------------------------------------
*/
use App\Events\TenantSettings\TenantSettingsUpdated;
use App\Listeners\TenantSettings\AuditTenantSettingsUpdated;

/*
|--------------------------------------------------------------------------
| Feature flag por tenant individual (roadmap A5, item 19)
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantFeatureOverridesSynced;
use App\Listeners\Tenant\AuditTenantFeatureOverridesSynced;

/*
|--------------------------------------------------------------------------
| Storefront (Delivery Fase 2) — horário de funcionamento / taxa de entrega
|--------------------------------------------------------------------------
*/
use App\Events\Storefront\StoreBusinessHoursUpdated;
use App\Events\Storefront\StoreDeliveryFeeUpserted;
use App\Events\Storefront\StoreDeliveryFeeDeleted;
use App\Listeners\Storefront\AuditStoreBusinessHoursUpdated;
use App\Listeners\Storefront\AuditStoreDeliveryFeeUpserted;
use App\Listeners\Storefront\AuditStoreDeliveryFeeDeleted;

/*
|--------------------------------------------------------------------------
| Storefront (Delivery Fase 3) — cupons / promoções de produto
|--------------------------------------------------------------------------
*/
use App\Events\Storefront\CouponCreated;
use App\Events\Storefront\CouponUpdated;
use App\Events\Storefront\CouponDeleted;
use App\Events\Storefront\ProductPromotionUpserted;
use App\Events\Storefront\ProductPromotionDeleted;
use App\Listeners\Storefront\AuditCouponCreated;
use App\Listeners\Storefront\AuditCouponUpdated;
use App\Listeners\Storefront\AuditCouponDeleted;
use App\Listeners\Storefront\AuditProductPromotionUpserted;
use App\Listeners\Storefront\AuditProductPromotionDeleted;

/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/
use App\Events\Order\OrderCreated;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderUndelivered;
use App\Events\Order\OrderPaid;
use App\Events\Order\OrderPartiallyPaid;
use App\Events\Order\OrderUnpaid;
use App\Events\Order\OrderInstallmentPaid;
use App\Events\Order\OrderInstallmentUnpaid;
use App\Events\Order\OrderInstallmentCreated;
use App\Events\Order\OrderInstallmentUpdated;
use App\Events\Order\OrderInstallmentDeleted;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderItemsUpdated;
use App\Events\Order\OrderApproved;
use App\Events\Order\OrderRejected;
use App\Events\Order\OrderOutForDelivery;
use App\Events\Order\OrderUndispatched;
use App\Events\Order\OrderPaymentCharged;
use App\Events\Order\OrderPaymentRefundRequested;
use App\Events\Order\OrderCancellationRequested;
use App\Events\Order\OrderCancellationApproved;
use App\Events\Order\OrderCancellationRejected;
use App\Listeners\Order\AuditOrderPaymentCharged;
use App\Listeners\Order\AuditOrderPaymentRefundRequested;
use App\Events\Storefront\CashbackCredited;
use App\Listeners\Order\CreditCashbackOnOrderPaid;
use App\Listeners\Order\ReverseCashbackOnOrderUnpaid;
use App\Listeners\Storefront\AuditCashbackCredited;
use App\Listeners\Storefront\SendPushOnCashbackCredited;
use App\Events\Storefront\ReactivationRuleUpdated;
use App\Events\Storefront\ReactivationDispatched;
use App\Listeners\Storefront\AuditReactivationRuleUpdated;
use App\Listeners\Storefront\AuditReactivationDispatched;
use App\Listeners\Storefront\SendPushOnReactivationDispatched;
use App\Listeners\Order\AuditOrderCreated;
use App\Listeners\Order\AuditOrderDelivered;
use App\Listeners\Order\AuditOrderUndelivered;
use App\Listeners\Order\AuditOrderPaid;
use App\Listeners\Order\AuditOrderPartiallyPaid;
use App\Listeners\Order\AuditOrderUnpaid;
use App\Listeners\Order\AuditOrderInstallmentPaid;
use App\Listeners\Order\AuditOrderInstallmentUnpaid;
use App\Listeners\Order\AuditOrderInstallmentCreated;
use App\Listeners\Order\AuditOrderInstallmentUpdated;
use App\Listeners\Order\AuditOrderInstallmentDeleted;
use App\Listeners\Order\AuditOrderCancelled;
use App\Listeners\Order\AuditOrderItemsUpdated;
use App\Listeners\Order\AuditOrderApproved;
use App\Listeners\Order\AuditOrderRejected;
use App\Listeners\Order\AuditOrderOutForDelivery;
use App\Listeners\Order\AuditOrderUndispatched;
use App\Listeners\Order\AuditOrderCancellationRequested;
use App\Listeners\Order\AuditOrderCancellationApproved;
use App\Listeners\Order\AuditOrderCancellationRejected;
use App\Listeners\Order\SendPushOnOrderApproved;
use App\Listeners\Order\SendPushOnOrderRejected;
use App\Listeners\Order\SendPushOnOrderDelivered;
use App\Listeners\Order\SendPushOnOrderOutForDelivery;
use App\Events\Report\ReceivableInteractionCreated;
use App\Listeners\Report\AuditReceivableInteractionCreated;

/*
|--------------------------------------------------------------------------
| Portal do cliente final
|--------------------------------------------------------------------------
*/
use App\Events\Portal\FinalCustomerRegistered;
use App\Events\Portal\PortalOtpRequested;
use App\Events\Portal\PortalOtpVerified;
use App\Events\Portal\PortalOtpVerificationFailed;
use App\Events\Portal\PortalLinkConfirmed;
use App\Listeners\Portal\WritePortalAuditLog;

/*
|--------------------------------------------------------------------------
| Assinatura / cobrança de planos (roadmap 1B)
|--------------------------------------------------------------------------
*/
use App\Events\Pdv\CashSessionOpened;
use App\Events\Pdv\CashSessionClosed;
use App\Events\Pdv\CashMovementRegistered;
use App\Events\Pdv\PdvSaleCompleted;
use App\Listeners\Pdv\AuditCashSessionOpened;
use App\Listeners\Pdv\AuditCashSessionClosed;
use App\Listeners\Pdv\AuditCashMovementRegistered;
use App\Listeners\Pdv\AuditPdvSaleCompleted;
use App\Events\Pdv\OperatorPinSet;
use App\Events\Pdv\OperatorSessionResolved;
use App\Listeners\Pdv\AuditOperatorPinSet;
use App\Listeners\Pdv\AuditOperatorSessionResolved;
use App\Events\Support\SupportTicketCreated;
use App\Listeners\Support\AuditSupportTicketCreated;
use App\Events\Balcao\StationCreated;
use App\Events\Balcao\StationUpdated;
use App\Events\Balcao\StationDeleted;
use App\Events\Balcao\TableCreated;
use App\Events\Balcao\TableUpdated;
use App\Events\Balcao\TableDeleted;
use App\Events\Balcao\ComandaOpened;
use App\Events\Balcao\ComandaClosed;
use App\Events\Balcao\ComandaItemAdded;
use App\Events\Balcao\ComandaItemSentToStation;
use App\Events\Balcao\ComandaItemPrepStatusUpdated;
use App\Events\Balcao\ComandaItemCancelled;
use App\Listeners\Balcao\AuditStationCreated;
use App\Listeners\Balcao\AuditStationUpdated;
use App\Listeners\Balcao\AuditStationDeleted;
use App\Listeners\Balcao\AuditTableCreated;
use App\Listeners\Balcao\AuditTableUpdated;
use App\Listeners\Balcao\AuditTableDeleted;
use App\Listeners\Balcao\AuditComandaOpened;
use App\Listeners\Balcao\AuditComandaClosed;
use App\Listeners\Balcao\AuditComandaItemAdded;
use App\Listeners\Balcao\AuditComandaItemSentToStation;
use App\Listeners\Balcao\AuditComandaItemPrepStatusUpdated;
use App\Listeners\Balcao\AuditComandaItemCancelled;
use App\Events\Subscription\SubscriptionCreated;
use App\Events\Subscription\SubscriptionPlanChanged;
use App\Events\Subscription\SubscriptionCanceled;
use App\Events\Subscription\SubscriptionWithdrawalRequested;
use App\Listeners\Subscription\WriteSubscriptionAuditLog;
use App\Listeners\Workflow\WriteWorkflowTransitionLog;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

        /*
        |--------------------------------------------------------------------------
        | Auth
        |--------------------------------------------------------------------------
        */
        LoginSucceeded::class => [AuditAuthListener::class],
        LoginFailed::class => [AuditAuthListener::class],
        TokenRefreshed::class => [AuditAuthListener::class],
        LogoutSucceeded::class => [AuditAuthListener::class],
        LogoutFailed::class => [AuditAuthListener::class],

        /*
        |--------------------------------------------------------------------------
        | Groups
        |--------------------------------------------------------------------------
        */
        GroupUsersSynced::class => [AuditGroupListener::class],
        GroupPermissionsSynced::class => [AuditGroupListener::class],
        GroupCreated::class => [AuditGroupCreated::class],
        GroupUpdated::class => [AuditGroupUpdated::class],
        GroupDeleted::class => [AuditGroupDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        UserCreated::class => [AuditUserCreated::class],
        UserUpdated::class => [AuditUserUpdated::class],
        UserDeleted::class => [AuditUserDeleted::class],
        UserProfileUpdated::class => [AuditUserProfileUpdated::class],
        UserPasswordChanged::class => [AuditUserPasswordChanged::class],
        UserEmailChanged::class => [AuditUserEmailChanged::class],

        /*
        |--------------------------------------------------------------------------
        | Functionalities
        |--------------------------------------------------------------------------
        */
        FunctionalityCreated::class => [AuditFunctionalityCreated::class],
        FunctionalityUpdated::class => [AuditFunctionalityUpdated::class],
        FunctionalityDeleted::class => [AuditFunctionalityDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Tenants
        |--------------------------------------------------------------------------
        */
        TenantCreated::class => [AuditTenantCreated::class, CreateDefaultStockLocation::class],
        TenantUpdated::class => [AuditTenantUpdated::class],
        TenantDeleted::class => [AuditTenantDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Tenant Roles
        |--------------------------------------------------------------------------
        */
        TenantRoleCreated::class => [AuditTenantRoleCreated::class],
        TenantRoleUpdated::class => [AuditTenantRoleUpdated::class],
        TenantRoleDeleted::class => [AuditTenantRoleDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Tenant Role Permissions
        |--------------------------------------------------------------------------
        */
        TenantRolePermissionsSynced::class => [AuditTenantRolePermissionsSynced::class],

        /*
        |--------------------------------------------------------------------------
        | Tenant Data Export
        |--------------------------------------------------------------------------
        */
        TenantDataExported::class => [AuditTenantDataExported::class],

        /*
        |--------------------------------------------------------------------------
        | Release Notes
        |--------------------------------------------------------------------------
        */
        ReleaseNoteCreated::class => [AuditReleaseNoteCreated::class],
        ReleaseNoteUpdated::class => [AuditReleaseNoteUpdated::class],
        ReleaseNoteDeleted::class => [AuditReleaseNoteDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Tenant User
        |--------------------------------------------------------------------------
        */
        TenantUserCreated::class => [AuditTenantUserCreated::class],
        TenantUserUpdated::class => [AuditTenantUserUpdated::class],
        TenantUserDeleted::class => [AuditTenantUserDeleted::class],
        TenantUserInvited::class => [AuditTenantUserInvited::class],
        TenantUserInviteAccepted::class => [AuditTenantUserInviteAccepted::class],
        PlanCreated::class => [AuditPlanCreated::class],
        PlanUpdated::class => [AuditPlanUpdated::class],
        PlanDeleted::class => [AuditPlanDeleted::class],
        PlanFunctionalitiesSynced::class => [AuditPlanFunctionalitiesSynced::class],

        /*
        |--------------------------------------------------------------------------
        | Client Category
        |--------------------------------------------------------------------------
        */
        ClientCategoryCreated::class => [AuditClientCategoryCreated::class],
        ClientCategoryUpdated::class => [AuditClientCategoryUpdated::class],
        ClientCategoryDeleted::class => [AuditClientCategoryDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Client
        |--------------------------------------------------------------------------
        */
        ClientCreated::class => [AuditClientCreated::class],
        ClientUpdated::class => [AuditClientUpdated::class],
        ClientDeleted::class => [AuditClientDeleted::class],
        ClientCategoriesSynced::class => [AuditClientCategoriesSynced::class],

        /*
        |--------------------------------------------------------------------------
        | Product Category
        |--------------------------------------------------------------------------
        */
        ProductCategoryCreated::class => [AuditProductCategoryCreated::class],
        ProductCategoryUpdated::class => [AuditProductCategoryUpdated::class],
        ProductCategoryDeleted::class => [AuditProductCategoryDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Product Type
        |--------------------------------------------------------------------------
        */
        ProductTypeCreated::class => [AuditProductTypeCreated::class],
        ProductTypeUpdated::class => [AuditProductTypeUpdated::class],
        ProductTypeDeleted::class => [AuditProductTypeDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */
        ProductCreated::class => [AuditProductCreated::class],
        ProductUpdated::class => [AuditProductUpdated::class],
        ProductDeleted::class => [AuditProductDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Product Import
        |--------------------------------------------------------------------------
        */
        ProductImportCommitted::class => [AuditProductImportCommitted::class],

        /*
        |--------------------------------------------------------------------------
        | Product Category Prices
        |--------------------------------------------------------------------------
        */
        ProductCategoryPricesSynced::class => [AuditProductCategoryPricesSynced::class],

        /*
        |--------------------------------------------------------------------------
        | Estado
        |--------------------------------------------------------------------------
        */
        EstadoCreated::class => [AuditEstadoCreated::class],
        EstadoUpdated::class => [AuditEstadoUpdated::class],
        EstadoDeleted::class => [AuditEstadoDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Cidade
        |--------------------------------------------------------------------------
        */
        CidadeCreated::class => [AuditCidadeCreated::class],
        CidadeUpdated::class => [AuditCidadeUpdated::class],
        CidadeDeleted::class => [AuditCidadeDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Bairro
        |--------------------------------------------------------------------------
        */
        BairroCreated::class => [AuditBairroCreated::class],
        BairroUpdated::class => [AuditBairroUpdated::class],
        BairroDeleted::class => [AuditBairroDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Endereco
        |--------------------------------------------------------------------------
        */
        EnderecoCreated::class => [AuditEnderecoCreated::class],
        EnderecoUpdated::class => [AuditEnderecoUpdated::class],
        EnderecoDeleted::class => [AuditEnderecoDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Dia Ideal
        |--------------------------------------------------------------------------
        */
        DiaIdealCreated::class => [AuditDiaIdealCreated::class],
        DiaIdealUpdated::class => [AuditDiaIdealUpdated::class],
        DiaIdealDeleted::class => [AuditDiaIdealDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Periodo Ideal
        |--------------------------------------------------------------------------
        */
        PeriodoIdealCreated::class => [AuditPeriodoIdealCreated::class],
        PeriodoIdealUpdated::class => [AuditPeriodoIdealUpdated::class],
        PeriodoIdealDeleted::class => [AuditPeriodoIdealDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Stock Location
        |--------------------------------------------------------------------------
        */
        StockLocationCreated::class => [AuditStockLocationCreated::class],
        StockLocationUpdated::class => [AuditStockLocationUpdated::class],
        StockLocationDeleted::class => [AuditStockLocationDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Stock Movement
        |--------------------------------------------------------------------------
        */
        StockMovementCreated::class => [AuditStockMovementCreated::class],

        /*
        |--------------------------------------------------------------------------
        | Order
        |--------------------------------------------------------------------------
        */
        OrderCreated::class => [AuditOrderCreated::class, WriteWorkflowTransitionLog::class],
        OrderDelivered::class => [AuditOrderDelivered::class, SendPushOnOrderDelivered::class, WriteWorkflowTransitionLog::class],
        OrderUndelivered::class => [AuditOrderUndelivered::class],
        OrderPaid::class => [AuditOrderPaid::class, CreditCashbackOnOrderPaid::class],
        OrderPartiallyPaid::class => [AuditOrderPartiallyPaid::class],
        OrderUnpaid::class => [AuditOrderUnpaid::class, ReverseCashbackOnOrderUnpaid::class],
        OrderInstallmentPaid::class => [AuditOrderInstallmentPaid::class],
        OrderInstallmentUnpaid::class => [AuditOrderInstallmentUnpaid::class],
        OrderInstallmentCreated::class => [AuditOrderInstallmentCreated::class],
        OrderInstallmentUpdated::class => [AuditOrderInstallmentUpdated::class],
        OrderInstallmentDeleted::class => [AuditOrderInstallmentDeleted::class],
        OrderCancelled::class => [AuditOrderCancelled::class, WriteWorkflowTransitionLog::class],
        OrderPaymentCharged::class => [AuditOrderPaymentCharged::class],
        OrderPaymentRefundRequested::class => [AuditOrderPaymentRefundRequested::class],
        OrderItemsUpdated::class => [AuditOrderItemsUpdated::class],
        OrderApproved::class => [AuditOrderApproved::class, SendPushOnOrderApproved::class, WriteWorkflowTransitionLog::class],
        OrderRejected::class => [AuditOrderRejected::class, SendPushOnOrderRejected::class, WriteWorkflowTransitionLog::class],
        OrderOutForDelivery::class => [AuditOrderOutForDelivery::class, SendPushOnOrderOutForDelivery::class, WriteWorkflowTransitionLog::class],
        OrderUndispatched::class => [AuditOrderUndispatched::class, WriteWorkflowTransitionLog::class],
        OrderCancellationRequested::class => [AuditOrderCancellationRequested::class],
        OrderCancellationApproved::class => [AuditOrderCancellationApproved::class],
        OrderCancellationRejected::class => [AuditOrderCancellationRejected::class],
        ReceivableInteractionCreated::class => [AuditReceivableInteractionCreated::class],

        /*
        |--------------------------------------------------------------------------
        | Portal do cliente final
        |--------------------------------------------------------------------------
        */
        FinalCustomerRegistered::class => [WritePortalAuditLog::class],
        PortalOtpRequested::class => [WritePortalAuditLog::class],
        PortalOtpVerified::class => [WritePortalAuditLog::class],
        PortalOtpVerificationFailed::class => [WritePortalAuditLog::class],
        PortalLinkConfirmed::class => [WritePortalAuditLog::class],

        /*
        |--------------------------------------------------------------------------
        | Tenant Settings
        |--------------------------------------------------------------------------
        */
        TenantSettingsUpdated::class => [AuditTenantSettingsUpdated::class],

        /*
        |--------------------------------------------------------------------------
        | Storefront (Delivery Fase 2)
        |--------------------------------------------------------------------------
        */
        StoreBusinessHoursUpdated::class => [AuditStoreBusinessHoursUpdated::class],
        StoreDeliveryFeeUpserted::class => [AuditStoreDeliveryFeeUpserted::class],
        StoreDeliveryFeeDeleted::class => [AuditStoreDeliveryFeeDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Storefront (Delivery Fase 3)
        |--------------------------------------------------------------------------
        */
        CouponCreated::class => [AuditCouponCreated::class],
        CouponUpdated::class => [AuditCouponUpdated::class],
        CouponDeleted::class => [AuditCouponDeleted::class],
        ProductPromotionUpserted::class => [AuditProductPromotionUpserted::class],
        ProductPromotionDeleted::class => [AuditProductPromotionDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Cashback (Delivery Fase 5)
        |--------------------------------------------------------------------------
        */
        CashbackCredited::class => [AuditCashbackCredited::class, SendPushOnCashbackCredited::class],

        /*
        |--------------------------------------------------------------------------
        | Régua de reativação de cliente (roadmap A5, item 18)
        |--------------------------------------------------------------------------
        */
        ReactivationRuleUpdated::class => [AuditReactivationRuleUpdated::class],
        ReactivationDispatched::class => [AuditReactivationDispatched::class, SendPushOnReactivationDispatched::class],

        /*
        |--------------------------------------------------------------------------
        | Feature flag por tenant individual (roadmap A5, item 19)
        |--------------------------------------------------------------------------
        */
        TenantFeatureOverridesSynced::class => [AuditTenantFeatureOverridesSynced::class],

        /*
        |--------------------------------------------------------------------------
        | Assinatura / cobrança de planos (roadmap 1B)
        |--------------------------------------------------------------------------
        */
        SubscriptionCreated::class => [WriteSubscriptionAuditLog::class],
        SubscriptionPlanChanged::class => [WriteSubscriptionAuditLog::class],
        SubscriptionCanceled::class => [WriteSubscriptionAuditLog::class],
        SubscriptionWithdrawalRequested::class => [WriteSubscriptionAuditLog::class],

        /*
        |--------------------------------------------------------------------------
        | PDV (roadmap PDV, Fase PDV-1) — caixa + venda de balcão
        |--------------------------------------------------------------------------
        */
        CashSessionOpened::class => [AuditCashSessionOpened::class],
        CashSessionClosed::class => [AuditCashSessionClosed::class],
        CashMovementRegistered::class => [AuditCashMovementRegistered::class],
        PdvSaleCompleted::class => [AuditPdvSaleCompleted::class],
        OperatorPinSet::class => [AuditOperatorPinSet::class],
        OperatorSessionResolved::class => [AuditOperatorSessionResolved::class],

        /*
        |--------------------------------------------------------------------------
        | Central de chamados (roadmap A4, item 17)
        |--------------------------------------------------------------------------
        */
        SupportTicketCreated::class => [AuditSupportTicketCreated::class],

        /*
        |--------------------------------------------------------------------------
        | Balcão (roadmap Balcão, Fases 1+2) — mesa/comanda/estação + fechamento
        |--------------------------------------------------------------------------
        */
        StationCreated::class => [AuditStationCreated::class],
        StationUpdated::class => [AuditStationUpdated::class],
        StationDeleted::class => [AuditStationDeleted::class],
        TableCreated::class => [AuditTableCreated::class],
        TableUpdated::class => [AuditTableUpdated::class],
        TableDeleted::class => [AuditTableDeleted::class],
        ComandaOpened::class => [AuditComandaOpened::class],
        ComandaClosed::class => [AuditComandaClosed::class],
        ComandaItemAdded::class => [AuditComandaItemAdded::class],
        ComandaItemSentToStation::class => [AuditComandaItemSentToStation::class, WriteWorkflowTransitionLog::class],
        ComandaItemPrepStatusUpdated::class => [AuditComandaItemPrepStatusUpdated::class, WriteWorkflowTransitionLog::class],
        ComandaItemCancelled::class => [AuditComandaItemCancelled::class, WriteWorkflowTransitionLog::class],
    ];
}
