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
| Storefront (Delivery Fase 3) — cupons
|--------------------------------------------------------------------------
*/
use App\Events\Storefront\CouponCreated;
use App\Events\Storefront\CouponUpdated;
use App\Events\Storefront\CouponDeleted;
use App\Listeners\Storefront\AuditCouponCreated;
use App\Listeners\Storefront\AuditCouponUpdated;
use App\Listeners\Storefront\AuditCouponDeleted;

/*
|--------------------------------------------------------------------------
| Sale
|--------------------------------------------------------------------------
*/
use App\Events\Sale\SaleCreated;
use App\Events\Sale\SaleCompleted;
use App\Events\Sale\SaleReopened;
use App\Events\Sale\SalePaid;
use App\Events\Sale\SalePartiallyPaid;
use App\Events\Sale\SaleUnpaid;
use App\Events\Sale\SaleInstallmentPaid;
use App\Events\Sale\SaleInstallmentUnpaid;
use App\Events\Sale\SaleInstallmentCreated;
use App\Events\Sale\SaleInstallmentUpdated;
use App\Events\Sale\SaleInstallmentDeleted;
use App\Events\Sale\SaleCancelled;
use App\Events\Sale\SaleItemsUpdated;
use App\Events\Sale\SaleApproved;
use App\Events\Sale\SaleRejected;
use App\Events\Sale\SalePaymentCharged;
use App\Events\Sale\SalePaymentRefundRequested;
use App\Events\Sale\SaleCancellationRequested;
use App\Events\Sale\SaleCancellationApproved;
use App\Events\Sale\SaleCancellationRejected;
use App\Listeners\Sale\AuditSalePaymentCharged;
use App\Listeners\Sale\AuditSalePaymentRefundRequested;
use App\Listeners\Sale\AuditSaleCreated;
use App\Listeners\Sale\AuditSaleCompleted;
use App\Listeners\Sale\AuditSaleReopened;
use App\Listeners\Sale\AuditSalePaid;
use App\Listeners\Sale\AuditSalePartiallyPaid;
use App\Listeners\Sale\AuditSaleUnpaid;
use App\Listeners\Sale\AuditSaleInstallmentPaid;
use App\Listeners\Sale\AuditSaleInstallmentUnpaid;
use App\Listeners\Sale\AuditSaleInstallmentCreated;
use App\Listeners\Sale\AuditSaleInstallmentUpdated;
use App\Listeners\Sale\AuditSaleInstallmentDeleted;
use App\Listeners\Sale\AuditSaleCancelled;
use App\Listeners\Sale\AuditSaleItemsUpdated;
use App\Listeners\Sale\AuditSaleApproved;
use App\Listeners\Sale\AuditSaleRejected;
use App\Listeners\Sale\AuditSaleCancellationRequested;
use App\Listeners\Sale\AuditSaleCancellationApproved;
use App\Listeners\Sale\AuditSaleCancellationRejected;
use App\Events\Sale\SaleRefundCreated;
use App\Listeners\Sale\AuditSaleRefundCreated;
use App\Listeners\Sale\SendPushOnSaleApproved;
use App\Listeners\Sale\SendPushOnSaleRejected;
use App\Listeners\Sale\SendPushOnSaleCompleted;
use App\Listeners\Sale\IssueTicketsOnSalePaid;
use App\Listeners\Sale\CancelTicketsOnSaleCancelled;
use App\Events\Ticket\TicketsIssued;
use App\Events\Ticket\TicketsCancelled;
use App\Events\Ticket\TicketResent;
use App\Events\Ticket\TicketCheckedIn;
use App\Listeners\Ticket\AuditTicketsIssued;
use App\Listeners\Ticket\AuditTicketsCancelled;
use App\Listeners\Ticket\AuditTicketResent;
use App\Listeners\Ticket\AuditTicketCheckedIn;

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
        TenantCreated::class => [AuditTenantCreated::class],
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
        | Sale
        |--------------------------------------------------------------------------
        */
        SaleCreated::class => [AuditSaleCreated::class, WriteWorkflowTransitionLog::class],
        SaleCompleted::class => [AuditSaleCompleted::class, SendPushOnSaleCompleted::class, WriteWorkflowTransitionLog::class],
        SaleReopened::class => [AuditSaleReopened::class],
        SalePaid::class => [AuditSalePaid::class, IssueTicketsOnSalePaid::class],
        SalePartiallyPaid::class => [AuditSalePartiallyPaid::class],
        SaleUnpaid::class => [AuditSaleUnpaid::class],
        SaleInstallmentPaid::class => [AuditSaleInstallmentPaid::class],
        SaleInstallmentUnpaid::class => [AuditSaleInstallmentUnpaid::class],
        SaleRefundCreated::class => [AuditSaleRefundCreated::class],
        SaleInstallmentCreated::class => [AuditSaleInstallmentCreated::class],
        SaleInstallmentUpdated::class => [AuditSaleInstallmentUpdated::class],
        SaleInstallmentDeleted::class => [AuditSaleInstallmentDeleted::class],
        SaleCancelled::class => [AuditSaleCancelled::class, WriteWorkflowTransitionLog::class, CancelTicketsOnSaleCancelled::class],
        SalePaymentCharged::class => [AuditSalePaymentCharged::class],
        SalePaymentRefundRequested::class => [AuditSalePaymentRefundRequested::class],
        SaleItemsUpdated::class => [AuditSaleItemsUpdated::class],
        SaleApproved::class => [AuditSaleApproved::class, SendPushOnSaleApproved::class, WriteWorkflowTransitionLog::class],
        SaleRejected::class => [AuditSaleRejected::class, SendPushOnSaleRejected::class, WriteWorkflowTransitionLog::class],
        SaleCancellationRequested::class => [AuditSaleCancellationRequested::class],
        SaleCancellationApproved::class => [AuditSaleCancellationApproved::class],
        SaleCancellationRejected::class => [AuditSaleCancellationRejected::class],

        /*
        |--------------------------------------------------------------------------
        | Ticket
        |--------------------------------------------------------------------------
        */
        TicketsIssued::class => [AuditTicketsIssued::class],
        TicketsCancelled::class => [AuditTicketsCancelled::class],
        TicketResent::class => [AuditTicketResent::class],
        TicketCheckedIn::class => [AuditTicketCheckedIn::class],

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
        | Storefront (Delivery Fase 3)
        |--------------------------------------------------------------------------
        */
        CouponCreated::class => [AuditCouponCreated::class],
        CouponUpdated::class => [AuditCouponUpdated::class],
        CouponDeleted::class => [AuditCouponDeleted::class],

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
