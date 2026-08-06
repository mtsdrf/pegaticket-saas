<?php

return [

    'health' => [
        'unhealthy' => 'One or more health checks failed.',
    ],

    /*
    |--------------------------------------------------------------------------
    | General Messages
    |--------------------------------------------------------------------------
    */
    'general' => [
        'success' => 'Operation completed successfully.',
        'error' => 'An error occurred while processing the request.',
        'unexpected' => 'An unexpected error occurred.',
        'invalid_payload' => 'Invalid payload.',
        'service_unavailable' => 'Service temporarily unavailable.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication / Authorization (JWT)
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'login_success' => 'Login realizado com sucesso.',
        'logout_success' => 'Logout realizado com sucesso.',
        'refresh_success' => 'Sessão renovada com sucesso.',
        'invalid_credentials' => 'Credenciais inválidas.',
        'account_locked' => 'Muitas tentativas de login. Tente novamente mais tarde.',
        'invalid_refresh_token' => 'Sessão inválida ou expirada. Faça login novamente.',
        'unauthenticated' => 'Sessão não autenticada.',
        'forbidden' => 'Acesso negado.',
        'token_blacklisted' => 'Sessão revogada.',
        'token_expired' => 'Sessão expirada.',
        'token_invalid' => 'Sessão inválida.',
        'token_missing' => 'Sessão não informada.',
        'token_not_provided' => 'Sessão não informada.',
        'session_revoked' => 'Sessão revogada.',
        'account_inactive' => 'Conta inativa.',
        'password_changed' => 'Senha alterada com sucesso.',
        'my_tenants' => 'Empresas do usuário listadas com sucesso.',
        'tenant_switched' => 'Empresa alterada com sucesso.',
        'tenant_forbidden' => 'Usuário não pertence a esta empresa.',
        'signup_plans_listed' => 'Planos disponíveis para cadastro listados com sucesso.',
        'signup_success' => 'Cadastro concluído com sucesso. Sua empresa já está pronta para o uso inicial.',
        'password_reset_requested' => 'If the email exists in our records, you will receive a password reset link.',
        'password_reset_success' => 'Password reset successfully.',
        'invalid_or_expired_reset_token' => 'This reset link is invalid or has expired. Please request a new password reset.',
        'password_reset_mail_subject' => 'Password reset on PegaTicket',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP / API
    |--------------------------------------------------------------------------
    */
    'http' => [
        'ok' => 'OK.',
        'created' => 'Resource created successfully.',
        'accepted' => 'Request accepted.',
        'no_content' => 'No content.',
        'bad_request' => 'Requisição inválida.',
        'unauthorized' => 'Não autorizado.',
        'forbidden' => 'Acesso negado.',
        'not_found' => 'Recurso não encontrado.',
        'method_not_allowed' => 'Method not allowed.',
        'not_acceptable' => 'Not acceptable.',
        'conflict' => 'Conflict while processing the request.',
        'unprocessable_entity' => 'Unprocessable entity.',
        'too_many_requests' => 'Too many requests. Please try again later.',
        'server_error' => 'Erro interno no servidor.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    'user' => [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted successfully.',
        'restored' => 'User restored successfully.',
        'not_found' => 'User not found.',
        'already_exists' => 'User already exists.',
        'email_already_used' => 'Email is already in use.',
        'cannot_delete_self' => 'You cannot delete your own user.',
        'inactive' => 'User is inactive.',
        'list' => 'Users list returned successfully.',
        'show' => 'User returned successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile ("Meus dados" — self-service, the logged staff user editing
    | themselves, not the /users admin CRUD)
    |--------------------------------------------------------------------------
    */
    'profile' => [
        'show' => 'Profile returned successfully.',
        'updated' => 'Profile updated successfully.',
        'password_changed' => 'Password changed successfully.',
        'email_change_requested' => 'A confirmation link was sent to the new email address.',
        'email_confirmed' => 'Email changed successfully.',
        'invalid_current_password' => 'Current password is incorrect.',
        'email_already_pending' => 'This email is already pending confirmation for another account.',
        'invalid_or_expired_token' => 'This confirmation link is invalid or has expired. Please request the email change again.',
        'mail_subject' => 'Confirm your new email on PegaTicket',
    ],

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    */
    'custom_report' => [
        'schema' => 'Available fields for custom reports.',
        'list' => 'Custom reports listed successfully.',
        'show' => 'Custom report found.',
        'created' => 'Custom report created successfully.',
        'updated' => 'Custom report updated successfully.',
        'deleted' => 'Custom report deleted successfully.',
        'executed' => 'Report executed successfully.',
        'execution_failed' => 'Could not execute the report. Please try again.',
    ],

    'group' => [
        'created' => 'Group created successfully.',
        'updated' => 'Group updated successfully.',
        'deleted' => 'Group deleted successfully.',
        'restored' => 'Group restored successfully.',
        'not_found' => 'Group not found.',
        'already_exists' => 'Group already exists.',
        'users_synced' => 'Group users synced successfully.',
        'permissions_synced' => 'Group permissions synced successfully.',
        'list' => 'Groups list returned successfully.',
        'show' => 'Group returned successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Functionalities
    |--------------------------------------------------------------------------
    */
    'functionality' => [
        'created' => 'Functionality created successfully.',
        'updated' => 'Functionality updated successfully.',
        'deleted' => 'Functionality deleted successfully.',
        'restored' => 'Functionality restored successfully.',
        'not_found' => 'Functionality not found.',
        'already_exists' => 'Functionality already exists.',
        'slug_already_used' => 'Slug is already in use.',
        'list' => 'Functionalities list returned successfully.',
        'show' => 'Functionality returned successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    */
    'audit_log' => [
        'list' => 'Audit log list returned successfully.',
    ],

    'communication_log' => [
        'list' => 'Communication log list returned successfully.',
    ],

    'email_template' => [
        'list' => 'Email templates list returned successfully.',
        'show' => 'Email template shown successfully.',
        'updated' => 'Email template updated successfully.',
        'reset' => 'Email template reset to default.',
        'invalid_type' => 'Email type ":type" cannot be customized.',
    ],

    'workflow' => [
        'timeline_list' => 'Operational history returned successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenants
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        'list' => 'Lista de empresas.',
        'shown' => 'Empresa retornada com sucesso.',
        'created' => 'Empresa criada com sucesso.',
        'updated' => 'Empresa atualizada com sucesso.',
        'deleted' => 'Empresa removida com sucesso.',
        'name_required' => 'Nome é obrigatório.',
        'slug_required' => 'Slug é obrigatório.',
        'slug_exists' => 'Slug já está em uso.',
        'invalid' => 'Empresa inválida ou inativa.',
        'owner_required' => 'Não foi possível identificar o proprietário da empresa.',
    ],

    'plan' => [
        'list' => 'Plans list returned successfully.',
        'show' => 'Plan returned successfully.',
        'created' => 'Plan created successfully.',
        'updated' => 'Plan updated successfully.',
        'deleted' => 'Plan deleted successfully.',
        'upgrade_required' => 'Esta funcionalidade não está disponível no plano ativo desta empresa. Atualize o plano para continuar.',
    ],

    'plan_functionality' => [
        'list' => 'Plan functionalities.',
        'synced' => 'Plan functionalities synced successfully.',
    ],

    'release_note' => [
        'list' => 'Release notes list returned successfully.',
        'created' => 'Release note created successfully.',
        'updated' => 'Release note updated successfully.',
        'deleted' => 'Release note deleted successfully.',
    ],

    'legal_document' => [
        'shown' => 'Legal document returned successfully.',
    ],

    'subscription' => [
        'shown' => 'Subscription returned successfully.',
        'created' => 'Subscription created successfully.',
        'plan_changed' => 'Subscription plan changed successfully.',
        'canceled' => 'Subscription canceled successfully.',
        'cancel_scheduled' => 'Cancellation scheduled for the end of the current cycle.',
        'withdrawal_requested' => 'Withdrawal request registered. The refund will be processed.',
        'not_found' => 'No subscription found for this company.',
        'already_exists' => 'This company already has an active or ongoing subscription.',
        'plan_required' => 'Set a plan for the company before starting the subscription.',
        'invalid_transition' => 'Invalid subscription status transition.',
        'withdrawal_window_expired' => 'The 7-day withdrawal window has already expired.',
        'withdrawal_already_processed' => 'This subscription has already been canceled and cannot be withdrawn again.',
        'no_active_price' => 'There is no active price for the given plan and billing period.',
        'suspended_access' => 'This company subscription is suspended or canceled. Please regularize the subscription to continue.',
        'renewed' => 'Scheduled cancellation reverted. The subscription remains active.',
        'renew_not_allowed' => 'Only a subscription with a cancellation scheduled for the end of the cycle can be renewed.',
        'payment_method_updated' => 'Subscription card updated successfully.',
        'invoice_payment_charge_created' => 'Invoice Pix charge created successfully.',
        'invoice_payment_not_available' => 'This invoice is not available to generate a new Pix charge right now.',
        'no_active_preapproval' => 'This subscription has no active automatic recurring charge to change the card for.',
        'invoices_list' => 'Invoices returned successfully.',
        'plan_pricing_shown' => 'Plan pricing returned successfully.',
        'available_plans_shown' => 'Plans available for change returned successfully.',
        'change_plan_not_allowed' => 'It is not possible to change plans right now. If there is a scheduled cancellation, reactivate the subscription before changing plans.',
        'plan_unchanged' => 'This company is already on the selected plan and billing period.',
        'history_list' => 'Subscription history returned successfully.',
        'plan_not_found' => 'Plan not found or unavailable for subscription.',
        'refunds_listed' => 'Refunds returned successfully.',
        'card_token_required' => 'Provide the card details to subscribe or switch to a paid plan.',
        'owner_only' => 'Only the company owner can manage the subscription.',
    ],

    'webhook' => [
        'not_implemented' => 'No real payment provider is configured yet.',
        'received' => 'Event received.',
        'invalid_signature' => 'Invalid webhook signature.',
        'processing_failed' => 'Temporary failure processing the event. It will be retried on the next delivery.',
    ],

    'payment' => [
        'provider_unavailable' => 'We could not process the payment right now. Please try again shortly or contact support.',
        'payer_collector_mismatch' => 'We could not start the subscription in this environment. For Mercado Pago tests, the company owner must use a Mercado Pago test account email and the integration credential must belong to the same environment.',
        'card_authorization_failed' => 'We could not authorize the card. Please check the details and try again.',
        'payer_tax_id_required' => 'Please provide the payer CPF or CNPJ to continue.',
        'card_data_required' => 'We could not read the encrypted card data. Refresh the page and try again.',
        'card_holder_name_required' => 'Please provide the card holder name.',
        'card_holder_tax_id_required' => 'Please provide the card holder CPF or CNPJ.',
        'installments_required' => 'Please provide the number of credit card installments.',
        'debit_authentication_required' => '3DS authentication is required for debit card payments.',
        'method_not_supported' => 'This payment method is not available in the current environment.',
        'idempotency_locked' => 'There is already a payment attempt in progress. Please wait a few moments before trying again.',
        'idempotency_ambiguous' => 'The last payment attempt is still being confirmed. Please wait a few moments and check its status before trying again.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant roles
    |--------------------------------------------------------------------------
    */
    'tenant_role' => [
        'list' => 'Roles list.',
        'created' => 'Role created successfully.',
        'updated' => 'Role updated successfully.',
        'deleted' => 'Role deleted successfully.',
        'slug_exists' => 'Slug já está em uso nesta empresa.',
        'protected_owner_role' => 'The Owner role is protected and cannot be modified.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant permission roles
    |--------------------------------------------------------------------------
    */
    'tenant_role_permission' => [
        'list' => 'Role permissions.',
        'synced' => 'Permissions synced successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant user
    |--------------------------------------------------------------------------
    */
    'tenant_user' => [
        'list' => 'Usuários da empresa listados com sucesso.',
        'created' => 'Usuário vinculado à empresa com sucesso.',
        'updated' => 'Vínculo da empresa atualizado com sucesso.',
        'deleted' => 'Usuário removido da empresa.',
        'already_exists' => 'Usuário já pertence a esta empresa.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant user invite
    |--------------------------------------------------------------------------
    */
    'tenant_user_invite' => [
        'created' => 'Invite sent successfully.',
        'accepted' => 'Invite accepted successfully. Welcome!',
        'mail_subject' => 'You have been invited to join :tenant on PegaTicket',
        'email_already_registered' => 'An account with this email already exists. Ask this person to request access from a company administrator.',
        'pending_invite_exists' => 'A pending invite for this email already exists in this company.',
        'invalid_token' => 'Invalid invite.',
        'already_accepted' => 'This invite has already been accepted.',
        'expired' => 'This invite has expired. Please request a new one.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Client category
    |--------------------------------------------------------------------------
    */
    'client_category' => [
        'list' => 'Client categories list.',
        'created' => 'Client category created successfully.',
        'updated' => 'Client category updated successfully.',
        'deleted' => 'Client category deleted successfully.',
        'name_exists' => 'Já existe uma categoria de cliente com este nome nesta empresa.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product category
    |--------------------------------------------------------------------------
    */
    'event_category' => [
        'list' => 'Event categories list.',
        'created' => 'Event category created successfully.',
        'updated' => 'Event category updated successfully.',
        'deleted' => 'Event category deleted successfully.',
        'name_exists' => 'An event category with this name already exists for this company.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event
    |--------------------------------------------------------------------------
    */
    'event' => [
        'list' => 'Events list.',
        'show' => 'Event shown successfully.',
        'created' => 'Event created successfully.',
        'updated' => 'Event updated successfully.',
        'deleted' => 'Event deleted successfully.',
        'published' => 'Event published successfully.',
        'sales_paused' => 'Event sales paused successfully.',
        'sales_resumed' => 'Event sales resumed successfully.',
        'sales_closed' => 'Event sales closed successfully.',
        'canceled' => 'Event canceled successfully.',
        'archived' => 'Event archived successfully.',
        'invalid_category' => 'Invalid event category for this company.',
        'invalid_venue' => 'Invalid venue for this company.',
        'slug_exists' => 'An event with this slug already exists for this company.',
        'venue_requires_published_map' => 'Publish the venue map before linking it to the event.',
        'invalid_status_transition' => 'This event status transition is not allowed from the current state.',
        'publish_requires_sellable_item' => 'Create at least one active ticket type or add-on before publishing the event.',
    ],

    'event_session' => [
        'list' => 'Event sessions list.',
        'show' => 'Event session shown successfully.',
        'created' => 'Event session created successfully.',
        'updated' => 'Event session updated successfully.',
        'deleted' => 'Event session deleted successfully.',
        'has_sales' => 'This session already has linked sales and cannot be deleted.',
    ],

    'event_gate' => [
        'list' => 'Gates list.',
        'show' => 'Gate shown successfully.',
        'created' => 'Gate created successfully.',
        'updated' => 'Gate updated successfully.',
        'deleted' => 'Gate deleted successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket type
    |--------------------------------------------------------------------------
    */
    'ticket_type' => [
        'list' => 'Ticket types list.',
        'show' => 'Ticket type shown successfully.',
        'created' => 'Ticket type created successfully.',
        'updated' => 'Ticket type updated successfully.',
        'deleted' => 'Ticket type deleted successfully.',
        'invalid_event' => 'Invalid event for this company.',
        'sku_exists' => 'A ticket type with this SKU already exists for this company.',
        'suggested_price' => 'Suggested price calculated successfully.',
        'status_updated' => 'Ticket type status updated successfully.',
    ],

    'ticket_batch' => [
        'list' => 'Ticket batches list.',
        'show' => 'Ticket batch shown successfully.',
        'created' => 'Ticket batch created successfully.',
        'updated' => 'Ticket batch updated successfully.',
        'deleted' => 'Ticket batch deleted successfully.',
        'has_sales' => 'This batch already has sales and cannot be deleted.',
    ],

    'ticket_type_channel_quota' => [
        'list' => 'Channel quotas list.',
        'show' => 'Channel quota shown successfully.',
        'created' => 'Channel quota created successfully.',
        'updated' => 'Channel quota updated successfully.',
        'deleted' => 'Channel quota deleted successfully.',
        'channel_quota_exceeded' => 'The channel inventory quota is exhausted for one or more selected items.',
    ],

    'ticket' => [
        'list' => 'Tickets list.',
        'show' => 'Ticket shown successfully.',
        'resent' => 'Ticket resend registered successfully.',
        'transferred' => 'Ticket transferred successfully.',
        'not_transferable' => 'This ticket cannot be transferred in its current status.',
        'mail_subject_issued' => 'Your tickets for sale #:code',
        'mail_subject_resent' => 'Ticket resend for sale #:code',
        'mail_subject_reminder' => 'Your event is coming up — sale #:code',
        'mail_subject_transferred' => 'Ticket transfer confirmed — sale #:code',
    ],

    'ticket_resale' => [
        'list' => 'Resale listings shown successfully.',
        'eligible_tickets_shown' => 'Resale-eligible tickets shown successfully.',
        'my_listings_shown' => 'My resale listings shown successfully.',
        'created' => 'Ticket listed for resale successfully.',
        'cancelled' => 'Resale listing cancelled successfully.',
        'purchased' => 'Resale completed successfully. The ticket is now in your name.',
        'payout_released' => 'Seller payout released successfully.',
        'ticket_not_eligible' => 'This ticket cannot be listed for resale in its current status.',
        'already_listed' => 'This ticket is already listed for resale.',
        'price_above_cap' => 'The resale price cannot exceed the originally paid amount.',
        'not_cancellable' => 'This listing cannot be cancelled in its current status.',
        'no_longer_available' => 'This listing is no longer available for purchase.',
        'cannot_buy_own_listing' => 'You cannot buy your own resale listing.',
        'payout_not_releasable' => 'This payout is not pending release.',
    ],

    'guest_list' => [
        'list' => 'Guest lists shown successfully.',
        'created' => 'Guest list created successfully.',
        'show' => 'Guest list shown successfully.',
        'entry_added' => 'Guest added successfully.',
        'invite_shown' => 'Invite shown successfully.',
        'redeemed' => 'Invite redeemed successfully.',
        'already_redeemed' => 'This invite has already been redeemed.',
    ],

    'ticket_type_waitlist' => [
        'list' => 'Waitlist entries shown successfully.',
        'entry_created' => 'You have joined the waitlist. We will email you if a spot opens up.',
        'mail_subject' => 'Spot available: :ticket_type',
    ],

    'affiliate' => [
        'list' => 'Affiliates shown successfully.',
        'created' => 'Affiliate created successfully.',
        'show' => 'Affiliate shown successfully.',
        'updated' => 'Affiliate updated successfully.',
        'commissions_list' => 'Affiliate commissions shown successfully.',
    ],

    'ticket_checkin' => [
        'history' => 'Check-in history loaded successfully.',
        'summary' => 'Operational gate summary loaded successfully.',
        'valido' => 'Entry granted.',
        'reentrada_autorizada' => 'Re-entry granted.',
        'reentrada_nao_permitida' => 'This ticket does not allow re-entry.',
        'reentrada_limite_excedido' => 'This ticket has reached its re-entry limit.',
        'reentrada_intervalo_nao_atingido' => 'Re-entry is not available yet: wait for the configured interval.',
        'ja_utilizado' => 'This ticket has already been used.',
        'cancelado' => 'This ticket was cancelled.',
        'estornado' => 'This ticket was refunded.',
        'bloqueado' => 'This ticket is blocked.',
        'evento_incorreto' => 'Ticket does not belong to this event.',
        'sessao_incorreta' => 'Ticket does not belong to this session.',
        'portaria_incorreta' => 'This ticket type is not allowed through this gate.',
        'nao_encontrado' => 'Ticket not found.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event product (add-on/parking)
    |--------------------------------------------------------------------------
    */
    'event_product' => [
        'list' => 'Event products list.',
        'show' => 'Event product shown successfully.',
        'created' => 'Event product created successfully.',
        'updated' => 'Event product updated successfully.',
        'deleted' => 'Event product deleted successfully.',
        'invalid_event' => 'Invalid event for this company.',
    ],

    'venue' => [
        'list' => 'Venues list.',
        'show' => 'Venue shown successfully.',
        'created' => 'Venue created successfully.',
        'updated' => 'Venue updated successfully.',
        'deleted' => 'Venue deleted successfully.',
        'published' => 'Venue map published successfully.',
    ],

    'seat' => [
        'list' => 'Seats list.',
        'show' => 'Seat shown successfully.',
        'created' => 'Seat created successfully.',
        'updated' => 'Seat updated successfully.',
        'deleted' => 'Seat deleted successfully.',
        'version_published' => 'This map version has already been published and can no longer be changed.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Customers (FinalCustomer, staff search)
    |--------------------------------------------------------------------------
    */
    'customers' => [
        'listed' => 'Customers list.',
        'crm_listed' => 'Customers list (CRM) shown successfully.',
        'recompra_mail_subject' => 'We miss you at :tenant!',
    ],

    /*
    |--------------------------------------------------------------------------
    | Location (general utilities, not Endereco-specific)
    |--------------------------------------------------------------------------
    */
    'location' => [
        'reverse_geocoded' => 'Location converted successfully.',
        'cep_found' => 'CEP found successfully.',
        'cep_not_found' => 'CEP not found. Please fill in the address manually.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    */
    'client' => [
        'list' => 'Client list.',
        'show' => 'Client shown successfully.',
        'created' => 'Client created successfully.',
        'updated' => 'Client updated successfully.',
        'deleted' => 'Client deleted successfully.',
        'cpf_cnpj_required' => 'Please provide the client\'s CPF or CNPJ. This is required to issue Pix charges correctly.',
        'cpf_cnpj_invalid' => 'The CPF or CNPJ provided is invalid. CPF must contain 11 digits; CNPJ may be numeric or alphanumeric, with 14 characters.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Location
    |--------------------------------------------------------------------------
    */
    'tenant_settings' => [
        'show' => 'Company settings.',
        'updated' => 'Company settings updated successfully.',
        'no_fulfillment_method_enabled' => 'The company must accept at least one fulfillment method: on-site pickup or delivery.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding checklist — roadmap A2
    |--------------------------------------------------------------------------
    */
    'onboarding' => [
        'checklist' => 'Onboarding checklist.',
        'dismissed' => 'Onboarding checklist dismissed successfully.',
        'restored' => 'Onboarding checklist restored successfully.',
    ],

    'stock_location' => [
        'list' => 'Stock location list.',
        'created' => 'Stock location created successfully.',
        'updated' => 'Stock location updated successfully.',
        'deleted' => 'Stock location deleted successfully.',
        'name_exists' => 'Já existe um local de estoque com este nome nesta empresa.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock (balances / movements)
    |--------------------------------------------------------------------------
    */
    'stock' => [
        'balances_list' => 'Stock balance list.',
        'movements_list' => 'Stock movement list.',
        'entry_created' => 'Stock entry recorded successfully.',
        'exit_created' => 'Stock exit recorded successfully.',
        'return_created' => 'Stock return recorded successfully.',
        'loss_created' => 'Stock loss recorded successfully.',
        'adjustment_created' => 'Stock adjustment recorded successfully.',
        'transfer_created' => 'Stock transfer recorded successfully.',
        'block_created' => 'Stock block recorded successfully.',
        'unblock_created' => 'Stock unblock recorded successfully.',
        'reserve_created' => 'Stock reserve recorded successfully.',
        'reserve_cancel_created' => 'Stock reserve cancellation recorded successfully.',
        'invalid_product' => 'Invalid ticket type for this company.',
        'invalid_location' => 'Local de estoque inválido para esta empresa.',
        'invalid_reserve_movement' => 'Movimento de reserva inválido ou inexistente para esta empresa.',
        'transfer_same_location' => 'The destination location must be different from the origin location.',
        'insufficient_balance' => 'Insufficient available balance for this operation.',
        'insufficient_blocked_balance' => 'Insufficient blocked balance for this operation.',
        'insufficient_reserved_balance' => 'Insufficient reserved balance for this operation.',
        'reserve_already_cancelled' => 'This reservation has already been cancelled.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash session
    |--------------------------------------------------------------------------
    */
    'cash_session' => [
        'list' => 'Cash sessions list.',
        'current' => 'Current cash session shown successfully.',
        'opened' => 'Cash session opened successfully.',
        'closed' => 'Cash session closed successfully.',
        'already_open' => 'There is already an open cash session. Close it before opening a new one.',
        'no_open_session' => 'There is no open cash session to close.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order
    |--------------------------------------------------------------------------
    */
    'sale' => [
        'risk_multiple_purchases_reason' => 'This customer made :count paid purchases for the same event in the last :hours hours (possible automated scalping/reselling). This is only an alert — review manually before acting.',
        'risk_failed_payment_velocity_reason' => 'This customer had :count failed/declined payment attempts in the last :minutes minutes (possible stolen card testing). This is only an alert — review manually before acting.',
        'risk_multiple_cards_reason' => 'This customer used :count different cards in the last :minutes minutes (possible stolen card testing). This is only an alert — review manually before acting.',
        'risk_card_shared_across_customers_reason' => 'The card used in this purchase was used by :count different customers in the last :minutes minutes (possible stolen card being tested across multiple accounts). This is only an alert — review manually before acting.',
        'risk_ip_velocity_reason' => ':count different customers completed a paid purchase from the same IP address in the last :minutes minutes (possible bot/fraud buying at scale disguised as multiple customers). This is only an alert — review manually before acting.',
        'risk_refund_abuse_reason' => 'This customer had :count refunds registered in the last :days days (possible refund abuse / preventive chargeback pattern). This is only an alert — review manually before acting.',
        'list' => 'Order list.',
        'show' => 'Order shown successfully.',
        'fiscal_preview' => 'Order fiscal preview shown successfully.',
        'fiscal_document_shown' => 'Order fiscal document shown successfully.',
        'fiscal_document_not_found' => 'This order does not have a prepared fiscal document yet.',
        'fiscal_document_prepared' => 'Internal order fiscal document prepared successfully.',
        'fiscal_document_submitted' => 'Internal fiscal document forwarded to the manual flow successfully.',
        'fiscal_document_status_synced' => 'Internal fiscal document progress refreshed successfully.',
        'fiscal_document_canceled' => 'Prepared fiscal document canceled successfully.',
        'fiscal_document_cancel_unavailable' => 'There is no active prepared fiscal document to cancel for this order.',
        'fiscal_document_submit_unavailable' => 'There is no prepared fiscal draft ready to be submitted for this order.',
        'fiscal_document_sync_unavailable' => 'There is no processing fiscal document available to check for this order.',
        'fiscal_document_already_submitted' => 'This fiscal document has already been submitted to the provider and is waiting for the next operational return.',
        'fiscal_document_already_pending' => 'This fiscal document is already pending in the provider.',
        'fiscal_document_provider_reference_missing' => 'This fiscal document does not yet have a provider reference for status checks.',
        'fiscal_document_canceled_default_reason' => 'Prepared fiscal document canceled manually.',
        'fiscal_document_invalidated_after_order_update' => 'Prepared fiscal document canceled because the order was changed.',
        'fiscal_document_invalidated_after_order_cancel' => 'Prepared fiscal document canceled because the order was canceled.',
        'fiscal_provider_submission_recorded' => 'Internal fiscal flow entry recorded in the operational history.',
        'fiscal_provider_status_sync_recorded' => 'Internal fiscal flow update recorded in the operational history.',
        'fiscal_prepare_blocked' => 'There are fiscal issues in this order. Fix them before preparing the fiscal document.',
        'fiscal_prepare_authorized_exists' => 'This order already has an authorized fiscal document. Use a complementary fiscal flow instead of preparing a new document.',
        'fiscal_prepare_submitted_exists' => 'This order already has a fiscal document submitted to the provider. Wait for the operational return or cancel the current flow before preparing another one.',
        'fiscal_submit_blocked' => 'The fiscal draft cannot be submitted to the provider yet. Fix the indicated items before submission.',
        'fiscal_submit_authorized_exists' => 'This order already has an authorized fiscal document. It is not possible to resend a new document through the default flow.',
        'fiscal_provider_token_missing' => 'The configured fiscal provider for this company is still missing the API token.',
        'fiscal_provider_certificate_missing' => 'The configured fiscal provider for this company requires the A1 certificate before issuance.',
        'fiscal_provider_certificate_password_missing' => 'The configured fiscal provider for this company requires the A1 certificate password.',
        'fiscal_nfce_csc_missing' => 'NFC-e issuance requires both CSC ID and CSC configured for the company.',
        'created' => 'Order created successfully.',
        'installment_paid' => 'Installment marked as paid successfully.',
        'installment_unpaid' => 'Installment payment undone successfully.',
        'cancelled' => 'Order cancelled successfully.',
        'invalid_client' => 'Cliente inválido para esta empresa.',
        'invalid_stock_location' => 'Local de estoque inválido para esta empresa.',
        'invalid_product' => 'Invalid ticket type or event product for this company.',
        'item_missing_sellable' => 'Each item must reference exactly one ticket type or event product.',
        'installments_count_required' => 'Installments count is required for installment sales.',
        'already_cancelled' => 'This order has already been cancelled.',
        'already_completed' => 'This order has already been marked as completed.',
        'not_completed' => 'This order has not been marked as completed yet.',
        'already_paid' => 'This order has already been marked as paid.',
        'not_paid' => 'This order has not been marked as paid yet.',
        'not_installment' => 'This order is not an installment order.',
        'installment_already_paid' => 'This installment has already been paid.',
        'installment_not_paid' => 'This installment has not been paid yet.',
        'cannot_cancel_paid' => 'Cannot cancel an order with a payment already recorded. A manual refund is required.',
        'payment_charge_created' => 'Payment charge created successfully.',
        'payment_checkout_config_loaded' => 'Checkout configuration loaded successfully.',
        'payment_charge_already_active' => 'This order already has an open payment charge.',
        'missing_reservation' => 'Stock reservation not found for this order item.',
        'no_default_stock_location' => 'Nenhum local de estoque padrão encontrado para esta empresa.',
        'installment_created' => 'Installment created successfully.',
        'installment_updated' => 'Installment updated successfully.',
        'installment_deleted' => 'Installment deleted successfully.',
        'installment_immutable_when_paid' => 'This installment has already been paid and cannot be modified or removed.',
        'installment_number_duplicate' => 'An installment with this number already exists for this order.',
        'installment_sum_mismatch' => 'The sum of all installments (:sum) must exactly match the order total (:total). Difference: :diff.',
        'installment_not_found_in_order' => 'One of the referenced installments does not belong to this order or does not exist.',
        'installment_duplicate_reference' => 'The same installment cannot be referenced twice in the same request.',
        'installments_reallocated' => 'Installments updated successfully.',
        'refund_created' => 'Refund registered successfully.',
        'refund_list' => 'Order refund list.',
        'refund_partial_requires_tickets' => 'Partial refund requires selecting at least one affected ticket.',
        'refund_ticket_not_found_in_order' => 'One of the given tickets does not belong to this order, does not exist, or was already refunded.',
        'refund_no_eligible_tickets' => 'There are no eligible tickets for refund in this order.',
        'refund_amount_exceeds_paid' => 'The refund amount exceeds the amount available for refund (:available).',
        'tracking_shown' => 'Order tracking shown successfully.',
        'items_updated' => 'Order items updated successfully.',
        'item_not_found_in_order' => 'One of the referenced items does not belong to this order or does not exist.',
        'item_duplicate_reference' => 'The same item cannot be referenced twice in the same request.',
        'discount_limit_exceeded' => 'The applied discount exceeds the :limit% limit allowed for your profile.',
        'not_pending_approval' => 'This order is not awaiting approval.',
        'approved' => 'Order approved successfully.',
        'rejected' => 'Order rejected successfully.',
        'awaiting_approval' => 'This order is still awaiting approval. Approve it before delivering or registering payment.',
        'order_rejected' => 'This order was rejected and cannot be delivered or paid.',
        'prep_link_generated' => 'Preparation link generated successfully.',
        'prep_shown' => 'Order preparation shown successfully.',
        'cancellation_request_not_storefront' => 'Only sales placed through the store can have cancellation requested through this channel.',
        'cancellation_already_requested' => 'There is already an open cancellation request for this order.',
        'no_cancellation_requested' => 'This order does not have an open cancellation request.',
        'cancellation_requested_by_client_default' => 'Cancellation requested by the customer.',
        'cancellation_approved' => 'Cancellation approved successfully.',
        'cancellation_rejected' => 'Cancellation request rejected successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public ticketing
    |--------------------------------------------------------------------------
    */
    'storefront' => [
        'tenant_shown' => 'Ticketing page shown successfully.',
        'products_listed' => 'Public catalog listed successfully.',
        'categories_listed' => 'Categories listed successfully.',
        'checkout_created' => 'Purchase completed successfully. Awaiting ticketing confirmation.',
        'storefront_disabled' => 'This company storefront is currently disabled.',
        'store_closed' => 'This ticketing page is currently closed. Check the business hours.',
        'below_minimum_order' => 'Purchase below the minimum value. Missing R$ :missing to reach the minimum.',
        'delivery_area_not_served' => 'We do not deliver to this neighborhood yet.',
        'delivery_fee_shown' => 'Delivery fee shown successfully.',
        'favorite_added' => 'Event added to favorites.',
        'favorite_removed' => 'Event removed from favorites.',
        'favorites_listed' => 'Favorite events list.',
        'order_rated' => 'Rating submitted successfully.',
        'order_already_rated' => 'This purchase has already been rated.',
        'cart_event_recorded' => 'Cart event recorded successfully.',
        'funnel_event_recorded' => 'Funnel event recorded successfully.',
        'store_pickup_not_enabled' => 'This company does not accept on-site pickup yet.',
        'store_pickup_address_missing' => 'The company has not configured its pickup point yet, so this purchase cannot be picked up on-site right now.',
        'delivery_not_enabled' => 'This company is not accepting deliveries right now.',
        'availability_shown' => 'Availability shown successfully.',
    ],

    'inventory_hold' => [
        'created' => 'Temporary hold created successfully.',
        'show' => 'Temporary hold shown successfully.',
        'renewed' => 'Temporary hold renewed successfully.',
        'released' => 'Temporary hold released successfully.',
        'invalid_item' => 'Each hold item must inform either a ticket or an add-on, but not both.',
        'session_required' => 'Select a session before reserving items for this event.',
        'seat_requires_ticket_type' => 'Seat selection must be linked to a ticket type.',
        'seat_quantity_invalid' => 'Individual seats can only be reserved once per item.',
        'seat_capacity_exceeded' => 'The requested quantity exceeds the available capacity for this place.',
        'invalid_seat' => 'Invalid seat for this event.',
        'duplicate_seat' => 'The same seat cannot be reserved twice in the same operation.',
        'ticket_type_session_mismatch' => 'The selected ticket type does not belong to the informed session.',
        'max_per_order_exceeded' => 'The requested quantity exceeds the purchase limit.',
        'insufficient_availability' => 'There is not enough availability for one or more selected items.',
        'not_active' => 'This temporary hold is no longer active.',
        'checkout_mismatch' => 'The checkout items no longer match the active temporary hold.',
    ],

    'virtual_queue' => [
        'status_shown' => 'Queue status shown successfully.',
        'not_admitted' => 'You have not been admitted to reserve this event yet. Please wait your turn in the queue.',
    ],

    'security' => [
        'suspicious_submission' => 'We could not process your request. Please try again.',
    ],

    'table_reservation' => [
        'list' => 'Table reservations listed successfully.',
        'created' => 'Reservation created successfully.',
        'public_available' => 'Reservation data loaded successfully.',
        'public_created' => 'Online reservation confirmed successfully.',
        'seated' => 'Guest seated and tab opened successfully.',
        'cancelled' => 'Reservation cancelled successfully.',
        'no_show' => 'Reservation marked as no-show.',
        'availability' => 'Table availability loaded successfully.',
        'past_time' => 'Cannot create a reservation for a time that has already passed.',
        'no_availability' => 'There are no tables available for that time and party size.',
        'table_capacity_insufficient' => 'The selected table does not fit the informed party size.',
        'invalid_state_for_seating' => 'This reservation can no longer be seated.',
        'table_required_to_seat' => 'The reservation must be linked to a table before seating the guest.',
        'already_finished' => 'This reservation has already been finished and can no longer be changed.',
        'public_unavailable' => 'This store is not accepting online reservations right now.',
    ],

    'table_waitlist' => [
        'list' => 'Waitlist entries listed successfully.',
        'created' => 'Guest added to the waitlist successfully.',
        'called' => 'Guest called successfully.',
        'seated' => 'Guest seated and tab opened successfully.',
        'cancelled' => 'Waitlist entry cancelled successfully.',
        'invalid_state_for_call' => 'This waitlist entry cannot be called right now.',
        'invalid_state_for_seating' => 'This waitlist entry can no longer be seated.',
        'table_capacity_insufficient' => 'The selected table does not fit the informed party size.',
        'already_finished' => 'This waitlist entry has already been finished and can no longer be changed.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticketing business hours
    |--------------------------------------------------------------------------
    */
    'store_business_hours' => [
        'show' => 'Business hours shown successfully.',
        'updated' => 'Business hours updated successfully.',
        'invalid_days' => 'Exactly the 7 days of the week (0 to 6) must be provided, with no repetition.',
        'closes_at_equal_to_opens_at' => 'Opening and closing time cannot be the same.',
        'too_many_shifts' => 'A maximum of 4 shifts per day is allowed.',
        'overlapping_shifts' => 'Shifts on the same day cannot overlap.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discount coupons
    |--------------------------------------------------------------------------
    */
    'coupon' => [
        'list' => 'Coupon list.',
        'created' => 'Coupon created successfully.',
        'updated' => 'Coupon updated successfully.',
        'deleted' => 'Coupon deleted successfully.',
        'invalid' => 'Invalid coupon.',
        'expired' => 'This coupon has expired.',
        'not_yet_available' => 'This coupon is not yet available.',
        'below_minimum_order' => 'This coupon requires a minimum purchase amount of R$ :minimum.',
        'usage_limit_reached' => 'This coupon has already reached its usage limit.',
        'validated' => 'Valid coupon.',
        'payment_method_not_allowed' => 'This coupon can only be used with a specific payment method. Select the payment method before applying the coupon.',
    ],

    'tax_rule' => [
        'list' => 'Tax rule list.',
        'created' => 'Tax rule created successfully.',
        'updated' => 'Tax rule updated successfully.',
        'deleted' => 'Tax rule deleted successfully.',
        'invalid_validity_range' => 'The validity end date cannot be earlier than the start date.',
    ],

    'fiscal_operation_profile' => [
        'list' => 'Fiscal operation profile list.',
        'created' => 'Fiscal operation profile created successfully.',
        'updated' => 'Fiscal operation profile updated successfully.',
        'deleted' => 'Fiscal operation profile deleted successfully.',
    ],

    'fiscal_readiness' => [
        'shown' => 'Fiscal readiness checklist shown successfully.',
        'provider_manual_mode' => 'The company is currently in manual fiscal mode. The system can prepare and track internal drafts, but it does not officially transmit documents to tax authorities yet.',
        'provider_token_missing' => 'The configured fiscal provider is still missing the API token.',
        'provider_certificate_missing' => 'The configured fiscal provider is still missing the A1 digital certificate.',
        'provider_certificate_password_missing' => 'The configured fiscal provider is still missing the A1 certificate password.',
        'nfce_csc_missing' => 'There is an active NFC-e profile, but CSC ID and CSC are still missing in the company record.',
        'provider_ready' => 'The fiscal provider ":provider" already has the minimum configuration expected for the next issuance step.',
    ],

    'tenant_feature_override' => [
        'list' => 'List of tenant feature overrides.',
        'synced' => 'Tenant feature overrides synced successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product promotional price (Delivery Phase 3)
    |--------------------------------------------------------------------------
    */
    'product_promotion' => [
        'list' => 'Promotion list.',
        'upserted' => 'Promotion saved successfully.',
        'deleted' => 'Promotion deleted successfully.',
        'discount_percentage_not_allowed' => 'Only set the discount percentage when the type is percentage.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports / Indicators / Dashboard
    |--------------------------------------------------------------------------
    */
    'report' => [
        'operation_snapshot' => 'Operational snapshot retrieved successfully.',
        'indicators' => 'Indicators retrieved successfully.',
        'charts' => 'Charts retrieved successfully.',
        'sales_list' => 'Order report list.',
        'sales_summary' => 'Order report summary.',
        'by_channel' => 'Sales result by channel.',
        'clients_list' => 'Client report list.',
        'receivables_list' => 'Receivables report list.',
        'receivables_summary' => 'Receivables report summary.',
        'receivable_interactions_list' => 'Receivable interaction history.',
        'receivable_interaction_created' => 'Receivable interaction recorded successfully.',
        'cmv' => 'CMV report retrieved successfully.',
        'alerts' => 'Alerts retrieved successfully.',
        'scheduled_subscriptions_listed' => 'Scheduled report subscriptions retrieved successfully.',
        'scheduled_subscription_created' => 'Scheduled report subscription created successfully.',
        'scheduled_subscription_cancelled' => 'Scheduled report subscription cancelled successfully.',
        'scheduled_summary_mail_subject' => ':frequency summary — :tenant',
    ],

    'analytics' => [
        'sales_summary' => 'Sales summary retrieved successfully.',
        'top_addons' => 'Top products retrieved successfully.',
        'sales_by_location' => 'Sales by location retrieved successfully.',
        'sales_history' => 'Sales history retrieved successfully.',
        'top_clients' => 'Top clients retrieved successfully.',
        'payment_delays' => 'Payment delays retrieved successfully.',
        'overdue_sales' => 'Overdue sales retrieved successfully.',
        'abc_analysis' => 'ABC analysis retrieved successfully.',
        'margin_summary' => 'Margin summary retrieved successfully.',
        'coupon_roi' => 'Coupon ticket comparison retrieved successfully.',
        'revenue_concentration' => 'Revenue concentration retrieved successfully.',
        'churn_clients' => 'Churned clients retrieved successfully.',
        'stalled_products' => 'Stalled products retrieved successfully.',
        'stock_ruptures' => 'Stock ruptures retrieved successfully.',
        'sales_by_hour' => 'Sales by hour retrieved successfully.',
        'checkin_insights' => 'Access analytics loaded successfully.',
        'sales_by_dimension' => 'Sales by dimension retrieved successfully.',
        'payments_summary' => 'Payments report retrieved successfully.',
        'affiliates_report' => 'Affiliates report retrieved successfully.',
        'coupons_report' => 'Coupons report retrieved successfully.',
        'refunds_report' => 'Refunds report retrieved successfully.',
        'inventory_report' => 'Inventory report retrieved successfully.',
        'funnel_report' => 'Conversion funnel retrieved successfully.',
        'compare_events_report' => 'Event comparison retrieved successfully.',
        'risk_report' => 'Fraud risk report retrieved successfully.',
        'resale_report' => 'Resale/transfer report retrieved successfully.',
        'operator_report' => 'Operator ticketing report retrieved successfully.',
        'cohorts_report' => 'Retention cohorts retrieved successfully.',
        'ltv_report' => 'Historical LTV retrieved successfully.',
        'event_affinity_report' => 'Event affinity retrieved successfully.',
    ],

    'route' => [
        'candidates_list' => 'Route candidates retrieved successfully.',
    ],

    'finance' => [
        'dashboard_loaded' => 'Financial dashboard loaded successfully.',
        'admin_dashboard_loaded' => 'Administrative financial dashboard loaded successfully.',
        'receivables_listed' => 'Receivables listed successfully.',
        'admin_receivables_listed' => 'Administrative receivables listed successfully.',
        'receivables_summary' => 'Receivables summary retrieved successfully.',
        'settlements_listed' => 'Settlements listed successfully.',
        'admin_settlements_listed' => 'Administrative settlements listed successfully.',
        'settlements_summary' => 'Settlements summary retrieved successfully.',
        'reconciliation_listed' => 'Financial reconciliation listed successfully.',
        'reconciliation_summary' => 'Financial reconciliation summary retrieved successfully.',
        'adjustments_listed' => 'Financial adjustments listed successfully.',
        'admin_adjustments_listed' => 'Administrative financial adjustments listed successfully.',
        'adjustments_summary' => 'Financial adjustments summary retrieved successfully.',
        'adjustment_manual_created' => 'Manual financial adjustment recorded successfully.',
        'adjustment_recovery_resolved' => 'Recovery exception resolved successfully.',
        'adjustment_review_resolved' => 'Review exception resolved successfully.',
        'adjustment_negative_receivable' => 'The manual adjustment would leave the receivable with a negative net amount.',
        'adjustment_negative_settlement' => 'The manual adjustment would leave the settlement with a negative net amount.',
        'adjustment_not_pending_recovery' => 'This adjustment is not pending recovery.',
        'adjustment_not_pending_review' => 'This adjustment is not pending review.',
        'adjustment_invalid_recovery_resolution' => 'Invalid resolution type for a recovery exception.',
        'adjustment_invalid_review_resolution' => 'Invalid resolution type for a review exception.',
        'event_closeout_loaded' => 'Event financial closeout loaded successfully.',
    ],

    'payment_admin' => [
        'issues_listed' => 'Payment issues listed successfully.',
        'issue_reprocessed' => 'Issue reprocessed successfully.',
        'issue_not_found' => 'Issue not found for the given type/reference, or it is no longer eligible for reprocessing.',
        'issue_not_reprocessable' => 'This issue type requires manual review and has no automated reprocessing.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions (Group × Functionality × Action)
    |--------------------------------------------------------------------------
    */
    'permission' => [
        'denied' => 'You do not have permission to perform this action.',
        'invalid_action' => 'Invalid action.',
        'invalid_functionality' => 'Invalid functionality.',
        'sync_success' => 'Permissions synced successfully.',
        'no_permissions' => 'No permissions found.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    */
    'push' => [
        'subscribed' => 'Push notification subscription registered successfully.',
        'order_approved_title' => 'Order approved',
        'order_approved_body' => 'Your order was approved!',
        'order_rejected_title' => 'Order rejected',
        'order_rejected_body' => 'Your order was rejected.',
        'order_completed_title' => 'Purchase completed',
        'order_completed_body' => 'Your purchase was completed!',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit / Logs
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'recorded' => 'Audit record created.',
        'failed' => 'Failed to record audit.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    | Tip: in FormRequest:
    | public function messages(){ return __('messages.validation.messages'); }
    | public function attributes(){ return __('messages.validation.attributes'); }
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'failed' => 'Validation failed.',
        'messages' => [
            // Basics
            'required' => 'The :attribute field is required.',
            'present' => 'The :attribute field must be present.',
            'nullable' => 'The :attribute field may be null.',
            'string' => 'The :attribute must be a string.',
            'boolean' => 'The :attribute field must be true or false.',
            'array' => 'The :attribute must be an array.',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'email' => 'The :attribute must be a valid email address.',
            'uuid' => 'The :attribute must be a valid UUID.',
            'date' => 'The :attribute is not a valid date.',

            // Size
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'between' => 'The :attribute must be between :min and :max.',
            'size' => 'The :attribute must be :size.',

            // Common rules
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'same' => 'The :attribute and :other must match.',
            'different' => 'The :attribute and :other must be different.',

            // Password (if used)
            'password' => 'The provided password is invalid.',
        ],

        // Friendly attribute names
        'attributes' => [
            // Auth
            'email' => 'email',
            'password' => 'password',
            'refresh_token' => 'refresh token',

            // User
            'name' => 'name',
            'is_active' => 'active',
            'group_uuids' => 'groups',
            'group_uuids.*' => 'group',

            // Profile
            'avatar' => 'photo',
            'current_password' => 'current password',
            'new_password' => 'new password',
            'new_email' => 'new email',
            'token' => 'token',

            // Group
            'slug' => 'slug',
            'user_uuids' => 'users',
            'user_uuids.*' => 'user',
            'permissions' => 'permissions',
            'permissions.*.functionality_slug' => 'functionality slug',
            'permissions.*.actions' => 'actions',
            'permissions.*.actions.*' => 'action',

            // Functionality
            'description' => 'description',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination / Meta
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'invalid_per_page' => 'Invalid per_page parameter.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    */
    'throttle' => [
        'too_many_requests' => 'Too many requests. Please try again in :seconds seconds.',
        'too_many_attempts' => 'Too many attempts. Please try again in :minutes minute(s).',
    ],

    /*
    |--------------------------------------------------------------------------
    | Final customer portal
    |--------------------------------------------------------------------------
    */
    'portal' => [
        'otp_mail_subject' => 'Your PegaTicket access code',
        'otp_sent' => 'If the provided email has an account, an access code was sent.',
        'otp_verified' => 'Login successful.',
        'invalid_code' => 'Invalid code.',
        'expired_code' => 'This code has expired. Request a new code.',
        'too_many_attempts' => 'Too many wrong attempts. Request a new code.',
        'otp_delivery_unavailable' => 'We could not send the access code by email right now. Please try again shortly or review the SMTP configuration.',
        'link_confirmed' => 'Store successfully linked to your history.',
        'sales_shown' => 'Customer sales.',
        'me_shown' => 'Customer profile.',
        'sale_items_shown' => 'Order items for reordering.',
        'addresses_listed' => 'Your addresses.',
        'address_updated' => 'Address updated successfully.',
        'coupon_redemptions_listed' => 'Your used coupons.',
        'cancellation_requested' => 'Cancellation requested successfully. Wait for the store to approve it.',
        'tickets_shown' => 'Your tickets.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Accountant module (roadmap 2C)
    |--------------------------------------------------------------------------
    */
    'accounting_auth' => [
        'registered' => 'Office registered. Set up TOTP and confirm the first code to activate access.',
        'email_already_registered' => 'An office is already registered with this email.',
        'totp_enabled' => 'TOTP confirmed. Login enabled.',
        'totp_not_configured' => 'TOTP is not configured for this office yet.',
        'totp_setup_required' => 'Complete TOTP setup before logging in.',
        'invalid_totp' => 'Invalid TOTP code.',
        'invalid_credentials' => 'Invalid email or password.',
        'logged_in' => 'Login successful.',
        'me' => 'Office data.',
    ],

    'accounting_access' => [
        'requested' => 'Access request sent. Awaiting company approval.',
        'my_links' => 'Your company links.',
        'list' => 'Accountant access requests.',
        'approved' => 'Accountant access approved.',
        'revoked' => 'Accountant access revoked.',
        'tenant_not_found' => 'No active company found with this tax id.',
        'already_approved' => 'The accountant already has approved access to this company.',
        'already_pending' => 'There is already a pending request for this company.',
        'not_approved' => 'Access not approved for this company.',
    ],

    'accounting_report' => [
        'sales' => 'Sales report.',
        'cash_flow' => 'Cash book.',
        'dre' => 'Simplified income statement.',
    ],

    'accounting_message' => [
        'list' => 'Request center messages.',
        'sent' => 'Message sent successfully.',
    ],

    'help_request' => [
        'list' => 'Help requests.',
        'created' => 'Help request opened successfully.',
    ],

    'station' => [
        'list' => 'Stations.',
        'created' => 'Station created successfully.',
        'updated' => 'Station updated successfully.',
        'deleted' => 'Station deleted successfully.',
        'tickets' => 'Station queue.',
        'name_exists' => 'A station with this name already exists.',
    ],

    'table' => [
        'list' => 'Tables.',
        'created' => 'Table created successfully.',
        'updated' => 'Table updated successfully.',
        'deleted' => 'Table deleted successfully.',
        'label_exists' => 'A table with this label already exists.',
    ],

    'comanda' => [
        'list' => 'Open tabs.',
        'opened' => 'Tab opened successfully.',
        'offline_snapshot_ready' => 'Counter offline snapshot updated successfully.',
        'closed' => 'Tab closed successfully.',
        'item_added' => 'Item added to the tab.',
        'item_prep_status_updated' => 'Preparation status updated.',
        'not_open' => 'This tab is not open.',
        'already_closed' => 'This tab has already been closed.',
        'already_cancelled' => 'This tab has been cancelled.',
        'no_items_to_close' => 'The tab has no items to close.',
        'item_already_sent' => 'This item has already been sent to the station.',
        'item_terminal_state' => 'This item is already delivered or cancelled.',
        'invalid_prep_transition' => 'Invalid preparation transition.',
        'cancel_reason_required' => 'Please provide a reason for cancelling the item.',
        'no_default_stock_location' => 'No default stock location configured.',
        'payment_mismatch' => 'The sum of payment methods does not match the tab total.',
    ],

    'api_key' => [
        'list' => 'API keys.',
        'created' => 'API key created successfully. Copy it now: it will not be shown again.',
        'revoked' => 'API key revoked successfully.',
        'missing' => 'Provide the API key in the Authorization header.',
        'invalid' => 'Invalid or revoked API key, or inactive company.',
    ],

    'webhook_subscription' => [
        'list' => 'Webhook subscriptions.',
        'show' => 'Webhook subscription.',
        'created' => 'Webhook subscription created successfully. Save the secret: it will not be shown again.',
        'updated' => 'Webhook subscription updated successfully.',
        'deleted' => 'Webhook subscription deleted successfully.',
        'deliveries' => 'Webhook delivery history.',
    ],

    'public_api' => [
        'sales_list' => 'Orders.',
        'sales_show' => 'Order.',
        'products_list' => 'Products.',
    ],
];
