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
| Event Category
|--------------------------------------------------------------------------
*/
use App\Events\Event\EventCategoryCreated;
use App\Events\Event\EventCategoryUpdated;
use App\Events\Event\EventCategoryDeleted;
use App\Listeners\Event\AuditEventCategoryCreated;
use App\Listeners\Event\AuditEventCategoryUpdated;
use App\Listeners\Event\AuditEventCategoryDeleted;

/*
|--------------------------------------------------------------------------
| Event
|--------------------------------------------------------------------------
*/
use App\Events\Event\EventCreated;
use App\Events\Event\EventUpdated;
use App\Events\Event\EventDeleted;
use App\Listeners\Event\AuditEventCreated;
use App\Listeners\Event\AuditEventUpdated;
use App\Listeners\Event\AuditEventDeleted;

/*
|--------------------------------------------------------------------------
| Ticket Type
|--------------------------------------------------------------------------
*/
use App\Events\Event\TicketTypeCreated;
use App\Events\Event\TicketTypeUpdated;
use App\Events\Event\TicketTypeDeleted;
use App\Listeners\Event\AuditTicketTypeCreated;
use App\Listeners\Event\AuditTicketTypeUpdated;
use App\Listeners\Event\AuditTicketTypeDeleted;

/*
|--------------------------------------------------------------------------
| Event Product (adicional/estacionamento)
|--------------------------------------------------------------------------
*/
use App\Events\Event\EventProductCreated;
use App\Events\Event\EventProductUpdated;
use App\Events\Event\EventProductDeleted;
use App\Listeners\Event\AuditEventProductCreated;
use App\Listeners\Event\AuditEventProductUpdated;
use App\Listeners\Event\AuditEventProductDeleted;

/*
|--------------------------------------------------------------------------
| Event Session Events
|--------------------------------------------------------------------------
*/
use App\Events\Event\EventSessionCreated;
use App\Events\Event\EventSessionUpdated;
use App\Events\Event\EventSessionDeleted;
use App\Listeners\Event\AuditEventSessionCreated;
use App\Listeners\Event\AuditEventSessionUpdated;
use App\Listeners\Event\AuditEventSessionDeleted;

/*
|--------------------------------------------------------------------------
| Ticket Batch Events
|--------------------------------------------------------------------------
*/
use App\Events\Event\TicketBatchCreated;
use App\Events\Event\TicketBatchUpdated;
use App\Events\Event\TicketBatchDeleted;
use App\Listeners\Event\AuditTicketBatchCreated;
use App\Listeners\Event\AuditTicketBatchUpdated;
use App\Listeners\Event\AuditTicketBatchDeleted;

/*
|--------------------------------------------------------------------------
| Venue Events
|--------------------------------------------------------------------------
*/
use App\Events\Venue\VenueCreated;
use App\Events\Venue\VenueUpdated;
use App\Events\Venue\VenueDeleted;
use App\Events\Venue\VenuePublished;
use App\Events\Venue\SeatCreated;
use App\Events\Venue\SeatUpdated;
use App\Events\Venue\SeatDeleted;
use App\Listeners\Venue\AuditVenueCreated;
use App\Listeners\Venue\AuditVenueUpdated;
use App\Listeners\Venue\AuditVenueDeleted;
use App\Listeners\Venue\AuditVenuePublished;
use App\Listeners\Venue\AuditSeatCreated;
use App\Listeners\Venue\AuditSeatUpdated;
use App\Listeners\Venue\AuditSeatDeleted;

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
| Storefront — horário de funcionamento
|--------------------------------------------------------------------------
*/
use App\Events\Storefront\StoreBusinessHoursUpdated;
use App\Listeners\Storefront\AuditStoreBusinessHoursUpdated;

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
use App\Events\Support\HelpRequestCreated;
use App\Listeners\Support\AuditHelpRequestCreated;
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
        | Event Category
        |--------------------------------------------------------------------------
        */
        EventCategoryCreated::class => [AuditEventCategoryCreated::class],
        EventCategoryUpdated::class => [AuditEventCategoryUpdated::class],
        EventCategoryDeleted::class => [AuditEventCategoryDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Event
        |--------------------------------------------------------------------------
        */
        EventCreated::class => [AuditEventCreated::class],
        EventUpdated::class => [AuditEventUpdated::class],
        EventDeleted::class => [AuditEventDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Ticket Type
        |--------------------------------------------------------------------------
        */
        TicketTypeCreated::class => [AuditTicketTypeCreated::class],
        TicketTypeUpdated::class => [AuditTicketTypeUpdated::class],
        TicketTypeDeleted::class => [AuditTicketTypeDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Event Product
        |--------------------------------------------------------------------------
        */
        EventProductCreated::class => [AuditEventProductCreated::class],
        EventProductUpdated::class => [AuditEventProductUpdated::class],
        EventProductDeleted::class => [AuditEventProductDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Event Session
        |--------------------------------------------------------------------------
        */
        EventSessionCreated::class => [AuditEventSessionCreated::class],
        EventSessionUpdated::class => [AuditEventSessionUpdated::class],
        EventSessionDeleted::class => [AuditEventSessionDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Ticket Batch
        |--------------------------------------------------------------------------
        */
        TicketBatchCreated::class => [AuditTicketBatchCreated::class],
        TicketBatchUpdated::class => [AuditTicketBatchUpdated::class],
        TicketBatchDeleted::class => [AuditTicketBatchDeleted::class],

        /*
        |--------------------------------------------------------------------------
        | Venue / Seat
        |--------------------------------------------------------------------------
        */
        VenueCreated::class => [AuditVenueCreated::class],
        VenueUpdated::class => [AuditVenueUpdated::class],
        VenueDeleted::class => [AuditVenueDeleted::class],
        VenuePublished::class => [AuditVenuePublished::class],
        SeatCreated::class => [AuditSeatCreated::class],
        SeatUpdated::class => [AuditSeatUpdated::class],
        SeatDeleted::class => [AuditSeatDeleted::class],

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
        OrderPaid::class => [AuditOrderPaid::class],
        OrderPartiallyPaid::class => [AuditOrderPartiallyPaid::class],
        OrderUnpaid::class => [AuditOrderUnpaid::class],
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
        | Storefront
        |--------------------------------------------------------------------------
        */
        StoreBusinessHoursUpdated::class => [AuditStoreBusinessHoursUpdated::class],

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
        | Central de chamados (roadmap A4, item 17)
        |--------------------------------------------------------------------------
        */
        HelpRequestCreated::class => [AuditHelpRequestCreated::class],

    ];
}
