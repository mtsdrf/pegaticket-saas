<?php

namespace App\Providers;

use App\Events\Affiliate\AffiliateCreated;
/*
|--------------------------------------------------------------------------
| Auth Events
|--------------------------------------------------------------------------
*/
use App\Events\Affiliate\AffiliateUpdated;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\LoginSucceeded;
use App\Events\Auth\LogoutFailed;
use App\Events\Auth\LogoutSucceeded;
use App\Events\Auth\TokenRefreshed;
/*
|--------------------------------------------------------------------------
| Group Events
|--------------------------------------------------------------------------
*/
use App\Events\CashSession\CashSessionClosed;
use App\Events\CashSession\CashSessionOpened;
use App\Events\Event\EventCategoryCreated;
use App\Events\Event\EventCategoryDeleted;
use App\Events\Event\EventCategoryUpdated;
use App\Events\Event\EventCreated;
/*
|--------------------------------------------------------------------------
| User Events
|--------------------------------------------------------------------------
*/
use App\Events\Event\EventDeleted;
use App\Events\Event\EventGateCreated;
use App\Events\Event\EventGateDeleted;
use App\Events\Event\EventGateUpdated;
use App\Events\Event\EventProductCreated;
use App\Events\Event\EventProductDeleted;
use App\Events\Event\EventProductUpdated;
use App\Events\Event\EventSessionCreated;
use App\Events\Event\EventSessionDeleted;
use App\Events\Event\EventSessionUpdated;
use App\Events\Event\EventStatusChanged;
use App\Events\Event\EventUpdated;
use App\Events\Event\TicketBatchCreated;
use App\Events\Event\TicketBatchDeleted;
use App\Events\Event\TicketBatchUpdated;
/*
|--------------------------------------------------------------------------
| Functionality Events
|--------------------------------------------------------------------------
*/
use App\Events\Event\TicketTypeCreated;
use App\Events\Event\TicketTypeDeleted;
use App\Events\Event\TicketTypeUpdated;
use App\Events\Functionality\FunctionalityCreated;
use App\Events\Functionality\FunctionalityDeleted;
use App\Events\Functionality\FunctionalityUpdated;
/*
|--------------------------------------------------------------------------
| Tenant Events
|--------------------------------------------------------------------------
*/
use App\Events\Group\AuditGroupCreated;
use App\Events\Group\AuditGroupDeleted;
use App\Events\Group\AuditGroupUpdated;
use App\Events\Group\GroupPermissionsSynced;
use App\Events\Group\GroupUsersSynced;
use App\Events\GuestList\GuestListEntryRedeemed;
use App\Events\Legal\ReleaseNoteCreated;
/*
|--------------------------------------------------------------------------
| Tenant Roles
|--------------------------------------------------------------------------
*/
use App\Events\Legal\ReleaseNoteDeleted;
use App\Events\Legal\ReleaseNoteUpdated;
use App\Events\Plan\PlanCreated;
use App\Events\Plan\PlanDeleted;
use App\Events\Plan\PlanFunctionalitiesSynced;
use App\Events\Plan\PlanUpdated;
/*
|--------------------------------------------------------------------------
| Tenant Role Permissions
|--------------------------------------------------------------------------
*/
use App\Events\Portal\FinalCustomerRegistered;
use App\Events\Portal\PortalLinkConfirmed;
use App\Events\Portal\PortalOtpRequested;
use App\Events\Portal\PortalOtpVerificationFailed;
use App\Events\Portal\PortalOtpVerified;
use App\Events\Sale\SaleApproved;
use App\Events\Sale\SaleCancellationApproved;
use App\Events\Sale\SaleCancellationRejected;
use App\Events\Sale\SaleCancellationRequested;
use App\Events\Sale\SaleCancelled;
use App\Events\Sale\SaleCreated;
use App\Events\Sale\SaleInstallmentCreated;
/*
|--------------------------------------------------------------------------
| Tenant User
|--------------------------------------------------------------------------
*/
use App\Events\Sale\SaleInstallmentDeleted;
use App\Events\Sale\SaleInstallmentPaid;
use App\Events\Sale\SaleInstallmentUnpaid;
use App\Events\Sale\SaleInstallmentUpdated;
use App\Events\Sale\SaleItemsUpdated;
use App\Events\Sale\SalePaid;
/*
|--------------------------------------------------------------------------
| Tenant User Invite
|--------------------------------------------------------------------------
*/
use App\Events\Sale\SalePaymentCharged;
use App\Events\Sale\SalePaymentRefundRequested;
use App\Events\Sale\SaleRefundCreated;
use App\Events\Sale\SaleRejected;
use App\Events\Sale\SaleUnpaid;
use App\Events\Storefront\CouponCreated;
use App\Events\Storefront\CouponDeleted;
use App\Events\Storefront\CouponUpdated;
use App\Events\Subscription\SubscriptionCanceled;
use App\Events\Subscription\SubscriptionCreated;
use App\Events\Subscription\SubscriptionPlanChanged;
use App\Events\Subscription\SubscriptionWithdrawalRequested;
/*
|--------------------------------------------------------------------------
| Event Category
|--------------------------------------------------------------------------
*/
use App\Events\Support\HelpRequestCreated;
use App\Events\Tenant\TenantCreated;
use App\Events\Tenant\TenantDataExported;
use App\Events\Tenant\TenantDeleted;
use App\Events\Tenant\TenantFeatureOverridesSynced;
use App\Events\Tenant\TenantRoleCreated;
/*
|--------------------------------------------------------------------------
| Event
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantRoleDeleted;
use App\Events\Tenant\TenantRolePermissionsSynced;
use App\Events\Tenant\TenantRoleUpdated;
use App\Events\Tenant\TenantUpdated;
use App\Events\Tenant\TenantUserCreated;
use App\Events\Tenant\TenantUserDeleted;
use App\Events\Tenant\TenantUserInviteAccepted;
use App\Events\Tenant\TenantUserInvited;
/*
|--------------------------------------------------------------------------
| Ticket Type
|--------------------------------------------------------------------------
*/
use App\Events\Tenant\TenantUserUpdated;
use App\Events\TenantSettings\TenantSettingsUpdated;
use App\Events\Ticket\TicketCheckedIn;
use App\Events\Ticket\TicketResent;
use App\Events\Ticket\TicketsCancelled;
use App\Events\Ticket\TicketsIssued;
use App\Events\Ticket\TicketTransferred;
use App\Events\TicketTypeWaitlist\TicketTypeWaitlistEntryCreated;
/*
|--------------------------------------------------------------------------
| Event Product (adicional/estacionamento)
|--------------------------------------------------------------------------
*/
use App\Events\User\UserCreated;
use App\Events\User\UserDeleted;
use App\Events\User\UserEmailChanged;
use App\Events\User\UserPasswordChanged;
use App\Events\User\UserProfileUpdated;
use App\Events\User\UserUpdated;
/*
|--------------------------------------------------------------------------
| Event Session Events
|--------------------------------------------------------------------------
*/
use App\Events\Venue\SeatCreated;
use App\Events\Venue\SeatDeleted;
use App\Events\Venue\SeatUpdated;
use App\Events\Venue\VenueCreated;
use App\Events\Venue\VenueDeleted;
use App\Events\Venue\VenuePublished;
/*
|--------------------------------------------------------------------------
| Ticket Batch Events
|--------------------------------------------------------------------------
*/
use App\Events\Venue\VenueUpdated;
use App\Listeners\Affiliate\AuditAffiliateCreated;
use App\Listeners\Affiliate\AuditAffiliateUpdated;
use App\Listeners\Audit\AuditAuthListener;
use App\Listeners\Audit\AuditGroupListener;
use App\Listeners\CashSession\AuditCashSessionClosed;
/*
|--------------------------------------------------------------------------
| Venue Events
|--------------------------------------------------------------------------
*/
use App\Listeners\CashSession\AuditCashSessionOpened;
use App\Listeners\Event\AuditEventCategoryCreated;
use App\Listeners\Event\AuditEventCategoryDeleted;
use App\Listeners\Event\AuditEventCategoryUpdated;
use App\Listeners\Event\AuditEventCreated;
use App\Listeners\Event\AuditEventDeleted;
use App\Listeners\Event\AuditEventGateCreated;
use App\Listeners\Event\AuditEventGateDeleted;
use App\Listeners\Event\AuditEventGateUpdated;
use App\Listeners\Event\AuditEventProductCreated;
use App\Listeners\Event\AuditEventProductDeleted;
use App\Listeners\Event\AuditEventProductUpdated;
use App\Listeners\Event\AuditEventSessionCreated;
use App\Listeners\Event\AuditEventSessionDeleted;
use App\Listeners\Event\AuditEventSessionUpdated;
use App\Listeners\Event\AuditEventStatusChanged;
use App\Listeners\Event\AuditEventUpdated;
/*
|--------------------------------------------------------------------------
| Tenant Settings
|--------------------------------------------------------------------------
*/
use App\Listeners\Event\AuditTicketBatchCreated;
use App\Listeners\Event\AuditTicketBatchDeleted;
/*
|--------------------------------------------------------------------------
| Feature flag por tenant individual (roadmap A5, item 19)
|--------------------------------------------------------------------------
*/
use App\Listeners\Event\AuditTicketBatchUpdated;
use App\Listeners\Event\AuditTicketTypeCreated;
/*
|--------------------------------------------------------------------------
| Storefront (Delivery Fase 3) — cupons
|--------------------------------------------------------------------------
*/
use App\Listeners\Event\AuditTicketTypeDeleted;
use App\Listeners\Event\AuditTicketTypeUpdated;
use App\Listeners\Functionality\AuditFunctionalityCreated;
use App\Listeners\Functionality\AuditFunctionalityDeleted;
use App\Listeners\Functionality\AuditFunctionalityUpdated;
use App\Listeners\GuestList\AuditGuestListEntryRedeemed;
use App\Listeners\Legal\AuditReleaseNoteCreated;
/*
|--------------------------------------------------------------------------
| Sale
|--------------------------------------------------------------------------
*/
use App\Listeners\Legal\AuditReleaseNoteDeleted;
use App\Listeners\Legal\AuditReleaseNoteUpdated;
use App\Listeners\Plan\AuditPlanCreated;
use App\Listeners\Plan\AuditPlanDeleted;
use App\Listeners\Plan\AuditPlanFunctionalitiesSynced;
use App\Listeners\Plan\AuditPlanUpdated;
use App\Listeners\Portal\WritePortalAuditLog;
use App\Listeners\Risk\FlagRiskOnSaleRefundCreated;
use App\Listeners\Sale\AuditSaleApproved;
use App\Listeners\Sale\AuditSaleCancellationApproved;
use App\Listeners\Sale\AuditSaleCancellationRejected;
use App\Listeners\Sale\AuditSaleCancellationRequested;
use App\Listeners\Sale\AuditSaleCancelled;
use App\Listeners\Sale\AuditSaleCreated;
use App\Listeners\Sale\AuditSaleInstallmentCreated;
use App\Listeners\Sale\AuditSaleInstallmentDeleted;
use App\Listeners\Sale\AuditSaleInstallmentPaid;
use App\Listeners\Sale\AuditSaleInstallmentUnpaid;
use App\Listeners\Sale\AuditSaleInstallmentUpdated;
use App\Listeners\Sale\AuditSaleItemsUpdated;
use App\Listeners\Sale\AuditSalePaid;
use App\Listeners\Sale\AuditSalePaymentCharged;
use App\Listeners\Sale\AuditSalePaymentRefundRequested;
use App\Listeners\Sale\AuditSaleRefundCreated;
use App\Listeners\Sale\AuditSaleRejected;
use App\Listeners\Sale\AuditSaleUnpaid;
use App\Listeners\Sale\CancelTicketsOnSaleCancelled;
use App\Listeners\Sale\CreateAffiliateCommissionOnSalePaid;
use App\Listeners\Sale\CreateReceivableOnSalePaid;
use App\Listeners\Sale\FlagRiskOnSalePaid;
use App\Listeners\Sale\IssueTicketsOnSalePaid;
use App\Listeners\Sale\RegisterFinancialAdjustmentOnSaleRefund;
use App\Listeners\Sale\SendPushOnSaleApproved;
use App\Listeners\Sale\SendPushOnSaleRejected;
use App\Listeners\Storefront\AuditCouponCreated;
use App\Listeners\Storefront\AuditCouponDeleted;
use App\Listeners\Storefront\AuditCouponUpdated;
use App\Listeners\Subscription\WriteSubscriptionAuditLog;
use App\Listeners\Support\AuditHelpRequestCreated;
use App\Listeners\Tenant\AuditTenantCreated;
use App\Listeners\Tenant\AuditTenantDataExported;
use App\Listeners\Tenant\AuditTenantDeleted;
use App\Listeners\Tenant\AuditTenantFeatureOverridesSynced;
use App\Listeners\Tenant\AuditTenantRoleCreated;
use App\Listeners\Tenant\AuditTenantRoleDeleted;
use App\Listeners\Tenant\AuditTenantRolePermissionsSynced;
use App\Listeners\Tenant\AuditTenantRoleUpdated;
use App\Listeners\Tenant\AuditTenantUpdated;
use App\Listeners\Tenant\AuditTenantUserCreated;
use App\Listeners\Tenant\AuditTenantUserDeleted;
use App\Listeners\Tenant\AuditTenantUserInviteAccepted;
use App\Listeners\Tenant\AuditTenantUserInvited;
use App\Listeners\Tenant\AuditTenantUserUpdated;
use App\Listeners\TenantSettings\AuditTenantSettingsUpdated;
use App\Listeners\Ticket\AuditTicketCheckedIn;
use App\Listeners\Ticket\AuditTicketResent;
use App\Listeners\Ticket\AuditTicketsCancelled;
use App\Listeners\Ticket\AuditTicketsIssued;
use App\Listeners\Ticket\AuditTicketTransferred;
use App\Listeners\Ticket\SendIssuedTicketsMail;
use App\Listeners\Ticket\SendResentTicketMail;
use App\Listeners\Ticket\SendTransferredTicketMail;
use App\Listeners\TicketTypeWaitlist\AuditTicketTypeWaitlistEntryCreated;
use App\Listeners\User\AuditUserCreated;
/*
|--------------------------------------------------------------------------
| Portal do cliente final
|--------------------------------------------------------------------------
*/
use App\Listeners\User\AuditUserDeleted;
use App\Listeners\User\AuditUserEmailChanged;
use App\Listeners\User\AuditUserPasswordChanged;
use App\Listeners\User\AuditUserProfileUpdated;
use App\Listeners\User\AuditUserUpdated;
use App\Listeners\Venue\AuditSeatCreated;
/*
|--------------------------------------------------------------------------
| Assinatura / cobrança de planos (roadmap 1B)
|--------------------------------------------------------------------------
*/
use App\Listeners\Venue\AuditSeatDeleted;
use App\Listeners\Venue\AuditSeatUpdated;
use App\Listeners\Venue\AuditVenueCreated;
use App\Listeners\Venue\AuditVenueDeleted;
use App\Listeners\Venue\AuditVenuePublished;
use App\Listeners\Venue\AuditVenueUpdated;
use App\Listeners\Workflow\WriteWorkflowTransitionLog;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

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
        | Affiliates
        |--------------------------------------------------------------------------
        */
        AffiliateCreated::class => [AuditAffiliateCreated::class],
        AffiliateUpdated::class => [AuditAffiliateUpdated::class],

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
        EventStatusChanged::class => [AuditEventStatusChanged::class],

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
        | Event Gate
        |--------------------------------------------------------------------------
        */
        EventGateCreated::class => [AuditEventGateCreated::class],
        EventGateUpdated::class => [AuditEventGateUpdated::class],
        EventGateDeleted::class => [AuditEventGateDeleted::class],

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
        SalePaid::class => [AuditSalePaid::class, IssueTicketsOnSalePaid::class, CreateReceivableOnSalePaid::class, CreateAffiliateCommissionOnSalePaid::class, FlagRiskOnSalePaid::class, WriteWorkflowTransitionLog::class],
        SaleUnpaid::class => [AuditSaleUnpaid::class],
        SaleInstallmentPaid::class => [AuditSaleInstallmentPaid::class],
        SaleInstallmentUnpaid::class => [AuditSaleInstallmentUnpaid::class],
        SaleRefundCreated::class => [
            AuditSaleRefundCreated::class,
            RegisterFinancialAdjustmentOnSaleRefund::class,
            FlagRiskOnSaleRefundCreated::class,
        ],
        CashSessionOpened::class => [AuditCashSessionOpened::class],
        CashSessionClosed::class => [AuditCashSessionClosed::class],
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
        TicketsIssued::class => [AuditTicketsIssued::class, SendIssuedTicketsMail::class],
        TicketsCancelled::class => [AuditTicketsCancelled::class],
        TicketResent::class => [AuditTicketResent::class, SendResentTicketMail::class],
        TicketCheckedIn::class => [AuditTicketCheckedIn::class],
        TicketTransferred::class => [AuditTicketTransferred::class, SendTransferredTicketMail::class],
        GuestListEntryRedeemed::class => [AuditGuestListEntryRedeemed::class],
        TicketTypeWaitlistEntryCreated::class => [AuditTicketTypeWaitlistEntryCreated::class],

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
