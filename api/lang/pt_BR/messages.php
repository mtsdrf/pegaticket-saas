<?php

return [

    'health' => [
        'unhealthy' => 'Um ou mais checks de saúde falharam.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mensagens Gerais
    |--------------------------------------------------------------------------
    */
    'general' => [
        'success' => 'Operação realizada com sucesso.',
        'error' => 'Ocorreu um erro ao processar a solicitação.',
        'unexpected' => 'Ocorreu um erro inesperado.',
        'invalid_payload' => 'Payload inválido.',
        'service_unavailable' => 'Serviço temporariamente indisponível.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autenticação / Autorização (JWT)
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'login_success' => 'Login realizado com sucesso.',
        'logout_success' => 'Logout realizado com sucesso.',
        'refresh_success' => 'Token atualizado com sucesso.',
        'invalid_credentials' => 'Credenciais inválidas.',
        'account_locked' => 'Muitas tentativas de login. Tente novamente mais tarde.',
        'invalid_refresh_token' => 'Refresh token inválido ou expirado.',
        'unauthenticated' => 'Não autenticado.',
        'forbidden' => 'Acesso negado.',
        'token_blacklisted' => 'Token revogado.',
        'token_expired' => 'Token expirado.',
        'token_invalid' => 'Token inválido.',
        'token_missing' => 'Token não informado.',
        'token_not_provided' => 'Token não fornecido.',
        'session_revoked' => 'Sessão revogada.',
        'account_inactive' => 'Conta inativa.',
        'password_changed' => 'Senha alterada com sucesso.',
        'my_tenants' => 'Empresas do usuário listadas com sucesso.',
        'tenant_switched' => 'Empresa alterada com sucesso.',
        'access_profile_loaded' => 'Perfil de acesso carregado com sucesso.',
        'tenant_forbidden' => 'Usuário não pertence a esta empresa.',
        'signup_plans_listed' => 'Planos disponíveis para cadastro listados com sucesso.',
        'signup_success' => 'Cadastro concluído com sucesso. Sua empresa já está pronta para o uso inicial.',
        'password_reset_requested' => 'Se o e-mail existir em nossa base, você receberá um link de redefinição.',
        'password_reset_success' => 'Senha redefinida com sucesso.',
        'invalid_or_expired_reset_token' => 'Este link de redefinição é inválido ou expirou. Solicite a redefinição de senha novamente.',
        'password_reset_mail_subject' => 'Redefinição de senha no PegaTicket',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP / API
    |--------------------------------------------------------------------------
    */
    'http' => [
        'ok' => 'OK.',
        'created' => 'Recurso criado com sucesso.',
        'accepted' => 'Solicitação aceita.',
        'no_content' => 'Sem conteúdo.',
        'bad_request' => 'Requisição inválida.',
        'unauthorized' => 'Não autorizado.',
        'forbidden' => 'Proibido.',
        'not_found' => 'Recurso não encontrado.',
        'method_not_allowed' => 'Método não permitido.',
        'not_acceptable' => 'Não aceitável.',
        'conflict' => 'Conflito ao processar a requisição.',
        'unprocessable_entity' => 'Entidade não processável.',
        'too_many_requests' => 'Muitas requisições. Tente novamente mais tarde.',
        'server_error' => 'Erro interno no servidor.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Usuários
    |--------------------------------------------------------------------------
    */
    'user' => [
        'created' => 'Usuário criado com sucesso.',
        'updated' => 'Usuário atualizado com sucesso.',
        'deleted' => 'Usuário removido com sucesso.',
        'restored' => 'Usuário restaurado com sucesso.',
        'not_found' => 'Usuário não encontrado.',
        'already_exists' => 'Usuário já existe.',
        'email_already_used' => 'E-mail já está em uso.',
        'cannot_delete_self' => 'Você não pode remover seu próprio usuário.',
        'inactive' => 'Usuário inativo.',
        'list' => 'Lista de usuários retornada com sucesso.',
        'show' => 'Usuário retornado com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Meus dados (auto-serviço — o próprio usuário logado editando a si
    | mesmo, não confundir com o CRUD admin /users)
    |--------------------------------------------------------------------------
    */
    'profile' => [
        'show' => 'Perfil retornado com sucesso.',
        'updated' => 'Perfil atualizado com sucesso.',
        'password_changed' => 'Senha alterada com sucesso.',
        'email_change_requested' => 'Um link de confirmação foi enviado para o novo e-mail.',
        'email_confirmed' => 'E-mail alterado com sucesso.',
        'invalid_current_password' => 'Senha atual incorreta.',
        'email_already_pending' => 'Este e-mail já está aguardando confirmação de outra conta.',
        'invalid_or_expired_token' => 'Este link de confirmação é inválido ou expirou. Solicite a troca de e-mail novamente.',
        'mail_subject' => 'Confirme seu novo e-mail no PegaTicket',
    ],

    /*
    |--------------------------------------------------------------------------
    | Grupos
    |--------------------------------------------------------------------------
    */
    'group' => [
        'created' => 'Grupo criado com sucesso.',
        'updated' => 'Grupo atualizado com sucesso.',
        'deleted' => 'Grupo removido com sucesso.',
        'restored' => 'Grupo restaurado com sucesso.',
        'not_found' => 'Grupo não encontrado.',
        'already_exists' => 'Grupo já existe.',
        'users_synced' => 'Usuários do grupo sincronizados com sucesso.',
        'permissions_synced' => 'Permissões do grupo sincronizadas com sucesso.',
        'list' => 'Lista de grupos retornada com sucesso.',
        'show' => 'Grupo retornado com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Funcionalidades
    |--------------------------------------------------------------------------
    */
    'functionality' => [
        'created' => 'Funcionalidade criada com sucesso.',
        'updated' => 'Funcionalidade atualizada com sucesso.',
        'deleted' => 'Funcionalidade removida com sucesso.',
        'restored' => 'Funcionalidade restaurada com sucesso.',
        'not_found' => 'Funcionalidade não encontrada.',
        'already_exists' => 'Funcionalidade já existe.',
        'slug_already_used' => 'Slug já está em uso.',
        'list' => 'Lista de funcionalidades retornada com sucesso.',
        'show' => 'Funcionalidade retornada com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditoria
    |--------------------------------------------------------------------------
    */
    'audit_log' => [
        'list' => 'Lista de auditoria retornada com sucesso.',
    ],

    'workflow' => [
        'timeline_list' => 'Histórico operacional retornado com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empresas
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
        'list' => 'Lista de planos retornada com sucesso.',
        'show' => 'Plano retornado com sucesso.',
        'created' => 'Plano criado com sucesso.',
        'updated' => 'Plano atualizado com sucesso.',
        'deleted' => 'Plano removido com sucesso.',
        'upgrade_required' => 'Esta funcionalidade não está disponível no plano ativo desta empresa. Atualize o plano para continuar.',
    ],

    'plan_functionality' => [
        'list' => 'Funcionalidades do plano.',
        'synced' => 'Funcionalidades do plano sincronizadas com sucesso.',
    ],

    'release_note' => [
        'list' => 'Lista de novidades retornada com sucesso.',
        'created' => 'Novidade criada com sucesso.',
        'updated' => 'Novidade atualizada com sucesso.',
        'deleted' => 'Novidade removida com sucesso.',
    ],

    'legal_document' => [
        'shown' => 'Documento legal retornado com sucesso.',
    ],

    'privacy_request' => [
        'list' => 'Solicitações de privacidade retornadas com sucesso.',
        'created' => 'Solicitação de privacidade registrada com sucesso.',
        'updated' => 'Solicitação de privacidade atualizada com sucesso.',
    ],

    'subscription' => [
        'shown' => 'Assinatura retornada com sucesso.',
        'created' => 'Assinatura criada com sucesso.',
        'plan_changed' => 'Plano da assinatura alterado com sucesso.',
        'canceled' => 'Assinatura cancelada com sucesso.',
        'cancel_scheduled' => 'Cancelamento agendado para o fim do ciclo atual.',
        'withdrawal_requested' => 'Solicitação de arrependimento registrada. O estorno será processado.',
        'not_found' => 'Nenhuma assinatura encontrada para esta empresa.',
        'already_exists' => 'Esta empresa já possui uma assinatura ativa ou em andamento.',
        'plan_required' => 'Defina um plano para a empresa antes de iniciar a assinatura.',
        'invalid_transition' => 'Transição de status de assinatura inválida.',
        'withdrawal_window_expired' => 'O prazo de arrependimento de 7 dias já expirou.',
        'withdrawal_already_processed' => 'Esta assinatura já foi cancelada e não pode ter um novo arrependimento solicitado.',
        'no_active_price' => 'Não há preço vigente para o plano e período informados.',
        'suspended_access' => 'A assinatura desta empresa está suspensa ou cancelada. Regularize a assinatura para continuar.',
        'renewed' => 'Cancelamento agendado revertido. A assinatura continua ativa.',
        'renew_not_allowed' => 'Só é possível renovar uma assinatura com cancelamento agendado para o fim do ciclo.',
        'payment_method_updated' => 'Cartão da assinatura atualizado com sucesso.',
        'invoice_payment_charge_created' => 'Cobrança Pix da fatura gerada com sucesso.',
        'invoice_payment_not_available' => 'Esta fatura não está disponível para gerar um novo Pix agora.',
        'no_active_preapproval' => 'Esta assinatura não possui cobrança recorrente automática ativa para ter o cartão trocado.',
        'invoices_list' => 'Faturas retornadas com sucesso.',
        'plan_pricing_shown' => 'Preços do plano retornados com sucesso.',
        'available_plans_shown' => 'Planos disponíveis para troca retornados com sucesso.',
        'change_plan_not_allowed' => 'Não é possível trocar de plano no momento. Se houver um cancelamento agendado, reative a assinatura antes de trocar de plano.',
        'plan_unchanged' => 'Esta empresa já está no plano e período selecionados.',
        'history_list' => 'Histórico de assinaturas retornado com sucesso.',
        'plan_not_found' => 'Plano não encontrado ou indisponível para contratação.',
        'refunds_listed' => 'Estornos retornados com sucesso.',
        'card_token_required' => 'Informe os dados do cartão para contratar ou trocar para um plano pago.',
        'owner_only' => 'Somente o proprietário da empresa pode gerenciar a assinatura.',
    ],

    'webhook' => [
        'not_implemented' => 'Nenhum provedor de pagamento real está configurado ainda.',
        'received' => 'Evento recebido.',
        'invalid_signature' => 'Assinatura do webhook inválida.',
        'processing_failed' => 'Falha temporária ao processar o evento. Será reprocessado na próxima tentativa.',
    ],

    'payment' => [
        'provider_unavailable' => 'Não foi possível processar o pagamento agora. Tente novamente em instantes ou entre em contato com o suporte.',
        'payer_collector_mismatch' => 'Não foi possível iniciar a assinatura neste ambiente. Para testes com o Mercado Pago, o proprietário da empresa precisa usar um e-mail de conta de teste do Mercado Pago e a credencial da integração precisa ser do mesmo ambiente.',
        'card_authorization_failed' => 'Não foi possível autorizar o cartão. Confira os dados e tente novamente.',
        'idempotency_locked' => 'Já existe uma tentativa de pagamento em andamento. Aguarde alguns instantes antes de tentar novamente.',
        'idempotency_ambiguous' => 'A última tentativa de pagamento ainda está sendo confirmada. Aguarde alguns instantes e verifique o status antes de tentar novamente.',
    ],

    'marketplace' => [
        'list' => 'Integrações de marketplace retornadas com sucesso.',
        'created' => 'Integração de marketplace criada com sucesso.',
        'updated' => 'Integração de marketplace atualizada com sucesso.',
        'merchants_synced' => 'Lojas externas sincronizadas com sucesso.',
        'events_polled' => 'Polling executado com sucesso.',
        'events_listed' => 'Eventos do marketplace retornados com sucesso.',
        'operations_summary' => 'Resumo operacional do marketplace retornado com sucesso.',
        'catalog_previewed' => 'Prévia do catálogo gerada com sucesso.',
        'catalog_sync_started' => 'Sincronização do catálogo iniciada com sucesso.',
        'catalog_syncs_listed' => 'Histórico de sincronizações do catálogo retornado com sucesso.',
        'catalog_sync_refreshed' => 'Status da sincronização do catálogo atualizado com sucesso.',
        'merchant_status_listed' => 'Status operacional da loja retornado com sucesso.',
        'interruption_created' => 'Pausa operacional criada com sucesso.',
        'interruption_deleted' => 'Pausa operacional removida com sucesso.',
        'opening_hours_synced' => 'Horários da loja enviados ao iFood com sucesso.',
        'orders_listed' => 'Pedidos externos retornados com sucesso.',
        'order_show' => 'Detalhes do pedido externo retornados com sucesso.',
        'order_refreshed' => 'Pedido externo sincronizado novamente com sucesso.',
        'cancellation_reasons_listed' => 'Motivos de cancelamento retornados com sucesso.',
        'health_checked' => 'Saúde da integração verificada com sucesso.',
        'action_executed' => 'Ação enviada ao marketplace com sucesso.',
        'event_retried' => 'Evento reenfileirado e reprocessado com sucesso.',
        'provider_not_supported' => 'Este parceiro ainda não é suportado pela plataforma.',
        'provider_unavailable' => 'Não foi possível acessar o parceiro agora. Verifique a credencial informada e tente novamente.',
        'credentials_required' => 'Informe a credencial da empresa para concluir a integração com o parceiro.',
        'authentication_failed' => 'Não foi possível autenticar a integração com o parceiro. Revise o client id, client secret e authorization code da empresa.',
        'merchant_not_found' => 'Não foi possível identificar a loja externa vinculada a este pedido.',
        'action_not_supported' => 'A ação solicitada ainda não está disponível para este parceiro.',
        'integration_inactive' => 'Esta integração está inativa e não pode receber eventos agora.',
        'webhook_received' => 'Webhook do parceiro recebido com sucesso.',
        'webhook_processing_failed' => 'Não foi possível processar o webhook do parceiro agora.',
        'invalid_webhook_signature' => 'A assinatura do webhook do parceiro é inválida.',
        'order_imported' => 'Pedido externo importado para o fluxo interno com sucesso.',
        'order_has_no_supported_items' => 'O pedido externo não possui itens compatíveis para importação.',
        'product_mapping_not_found' => 'Nenhum produto interno foi encontrado para o item ":item". Cadastre ou ajuste o SKU, código de barras ou nome do produto antes de importar.',
        'catalog_has_no_available_products' => 'Não há produtos disponíveis para publicar no catálogo agora.',
        'catalog_sync_item_failed' => 'O iFood recusou este item do catálogo. Revise o payload e tente novamente.',
        'catalog_limitation_simple_items_only' => 'O catálogo atual publica somente categorias e itens simples do PegaTicket.',
        'catalog_limitation_no_option_groups' => 'Complementos, grupos de opcionais, combos e pizza ainda dependem de modelagem interna própria antes da publicação no iFood.',
        'catalog_limitation_no_combos' => 'Combos e pizza ainda dependem de modelagem interna adicional antes da publicação no iFood.',
        'no_business_hours_configured' => 'Configure ao menos um turno de funcionamento da loja antes de enviar os horários para o iFood.',
        'unknown_item' => 'item sem identificação',
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras da empresa
    |--------------------------------------------------------------------------
    */
    'tenant_role' => [
        'list' => 'Lista de cargos.',
        'created' => 'Cargo criado com sucesso.',
        'updated' => 'Cargo atualizado com sucesso.',
        'deleted' => 'Cargo removido com sucesso.',
        'slug_exists' => 'Slug já está em uso nesta empresa.',
        'protected_owner_role' => 'O perfil de Proprietário é protegido e não pode ser alterado.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras e permissões da empresa
    |--------------------------------------------------------------------------
    */
    'tenant_role_permission' => [
        'list' => 'Permissões do cargo.',
        'synced' => 'Permissões sincronizadas com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Vinculo empresa-usuário
    |--------------------------------------------------------------------------
    */
    'tenant_user' => [
        'list' => 'Usuários da empresa listados com sucesso.',
        'created' => 'Usuário vinculado à empresa com sucesso.',
        'updated' => 'Vínculo atualizado com sucesso.',
        'deleted' => 'Usuário removido da empresa.',
        'already_exists' => 'Usuário já pertence a esta empresa.'
    ],

    /*
    |--------------------------------------------------------------------------
    | Convite de usuário para a empresa
    |--------------------------------------------------------------------------
    */
    'tenant_user_invite' => [
        'created' => 'Convite enviado com sucesso.',
        'accepted' => 'Convite aceito com sucesso. Bem-vindo(a)!',
        'mail_subject' => 'Você foi convidado para a empresa :tenant no PegaTicket',
        'email_already_registered' => 'Já existe uma conta com este e-mail. Peça para essa pessoa solicitar vínculo a um administrador da empresa.',
        'pending_invite_exists' => 'Já existe um convite pendente para este e-mail nesta empresa.',
        'invalid_token' => 'Convite inválido.',
        'already_accepted' => 'Este convite já foi aceito.',
        'expired' => 'Este convite expirou. Solicite um novo convite.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categoria de cliente
    |--------------------------------------------------------------------------
    */
    'client_category' => [
        'list' => 'Lista de categorias de cliente.',
        'created' => 'Categoria de cliente criada com sucesso.',
        'updated' => 'Categoria de cliente atualizada com sucesso.',
        'deleted' => 'Categoria de cliente removida com sucesso.',
        'name_exists' => 'Já existe uma categoria de cliente com este nome nesta empresa.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categoria de produto
    |--------------------------------------------------------------------------
    */
    'event_category' => [
        'list' => 'Lista de categorias de evento.',
        'created' => 'Categoria de evento criada com sucesso.',
        'updated' => 'Categoria de evento atualizada com sucesso.',
        'deleted' => 'Categoria de evento removida com sucesso.',
        'name_exists' => 'Já existe uma categoria de evento com este nome nesta empresa.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Evento
    |--------------------------------------------------------------------------
    */
    'event' => [
        'list' => 'Lista de eventos.',
        'show' => 'Evento exibido com sucesso.',
        'created' => 'Evento criado com sucesso.',
        'updated' => 'Evento atualizado com sucesso.',
        'deleted' => 'Evento removido com sucesso.',
        'invalid_category' => 'Categoria de evento inválida para esta empresa.',
        'invalid_venue' => 'Local inválido para esta empresa.',
        'slug_exists' => 'Já existe um evento com este slug nesta empresa.',
        'venue_requires_published_map' => 'Publique o mapa do local antes de vinculá-lo ao evento.',
    ],

    'event_session' => [
        'list' => 'Lista de sessões.',
        'show' => 'Sessão exibida com sucesso.',
        'created' => 'Sessão criada com sucesso.',
        'updated' => 'Sessão atualizada com sucesso.',
        'deleted' => 'Sessão removida com sucesso.',
        'has_sales' => 'Esta sessão já possui vendas vinculadas e não pode ser removida.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipo de ingresso
    |--------------------------------------------------------------------------
    */
    'ticket_type' => [
        'list' => 'Lista de tipos de ingresso.',
        'show' => 'Tipo de ingresso exibido com sucesso.',
        'created' => 'Tipo de ingresso criado com sucesso.',
        'updated' => 'Tipo de ingresso atualizado com sucesso.',
        'deleted' => 'Tipo de ingresso removido com sucesso.',
        'invalid_event' => 'Evento inválido para esta empresa.',
        'sku_exists' => 'Já existe um tipo de ingresso com este SKU nesta empresa.',
        'suggested_price' => 'Preço sugerido calculado com sucesso.',
        'status_updated' => 'Status do tipo de ingresso atualizado com sucesso.',
    ],

    'ticket_batch' => [
        'list' => 'Lista de lotes.',
        'show' => 'Lote exibido com sucesso.',
        'created' => 'Lote criado com sucesso.',
        'updated' => 'Lote atualizado com sucesso.',
        'deleted' => 'Lote removido com sucesso.',
        'has_sales' => 'Este lote já possui vendas registradas e não pode ser removido.',
    ],

    'ticket' => [
        'list' => 'Lista de ingressos.',
        'show' => 'Ingresso exibido com sucesso.',
        'resent' => 'Reenvio do ingresso registrado com sucesso.',
    ],

    'ticket_checkin' => [
        'valido' => 'Entrada liberada.',
        'ja_utilizado' => 'Este ingresso já foi utilizado.',
        'cancelado' => 'Este ingresso foi cancelado.',
        'estornado' => 'Este ingresso foi estornado.',
        'bloqueado' => 'Este ingresso está bloqueado.',
        'evento_incorreto' => 'Ingresso não pertence a este evento.',
        'sessao_incorreta' => 'Ingresso não pertence a esta sessão.',
        'nao_encontrado' => 'Ingresso não encontrado.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Produto do evento (adicional/estacionamento)
    |--------------------------------------------------------------------------
    */
    'event_product' => [
        'list' => 'Lista de produtos do evento.',
        'show' => 'Produto do evento exibido com sucesso.',
        'created' => 'Produto do evento criado com sucesso.',
        'updated' => 'Produto do evento atualizado com sucesso.',
        'deleted' => 'Produto do evento removido com sucesso.',
        'invalid_event' => 'Evento inválido para esta empresa.',
    ],

    'venue' => [
        'list' => 'Lista de locais.',
        'show' => 'Local exibido com sucesso.',
        'created' => 'Local criado com sucesso.',
        'updated' => 'Local atualizado com sucesso.',
        'deleted' => 'Local removido com sucesso.',
        'published' => 'Mapa do local publicado com sucesso.',
    ],

    'seat' => [
        'list' => 'Lista de assentos.',
        'show' => 'Assento exibido com sucesso.',
        'created' => 'Assento criado com sucesso.',
        'updated' => 'Assento atualizado com sucesso.',
        'deleted' => 'Assento removido com sucesso.',
        'version_published' => 'Esta versão do mapa já foi publicada e não pode mais ser alterada.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compradores (FinalCustomer, busca pelo staff)
    |--------------------------------------------------------------------------
    */
    'customers' => [
        'listed' => 'Lista de compradores.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Localização (utilitários gerais, não específicos de Endereço)
    |--------------------------------------------------------------------------
    */
    'location' => [
        'reverse_geocoded' => 'Localização convertida com sucesso.',
        'cep_found' => 'CEP encontrado com sucesso.',
        'cep_not_found' => 'CEP não encontrado. Preencha o endereço manualmente.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cliente
    |--------------------------------------------------------------------------
    */
    'client' => [
        'list' => 'Lista de clientes.',
        'show' => 'Cliente exibido com sucesso.',
        'created' => 'Cliente criado com sucesso.',
        'updated' => 'Cliente atualizado com sucesso.',
        'deleted' => 'Cliente removido com sucesso.',
        'cpf_cnpj_required' => 'Informe o CPF ou CNPJ do cliente. Esse dado é necessário para gerar cobranças por Pix corretamente.',
        'cpf_cnpj_invalid' => 'O CPF ou CNPJ informado é inválido. CPF deve ter 11 números; CNPJ pode ser numérico ou alfanumérico, com 14 caracteres.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local de estoque
    |--------------------------------------------------------------------------
    */
    'tenant_settings' => [
        'show' => 'Configurações da empresa.',
        'updated' => 'Configurações da empresa atualizadas com sucesso.',
        'no_fulfillment_method_enabled' => 'A empresa precisa aceitar pelo menos uma forma de atendimento: retirada presencial ou entrega.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Checklist de implantação (onboarding) — roadmap A2
    |--------------------------------------------------------------------------
    */
    'onboarding' => [
        'checklist' => 'Checklist de implantação.',
        'dismissed' => 'Checklist de implantação dispensado com sucesso.',
        'restored' => 'Checklist de implantação reativado com sucesso.',
    ],

    'stock_location' => [
        'list' => 'Lista de locais de estoque.',
        'created' => 'Local de estoque criado com sucesso.',
        'updated' => 'Local de estoque atualizado com sucesso.',
        'deleted' => 'Local de estoque removido com sucesso.',
        'name_exists' => 'Já existe um local de estoque com este nome nesta empresa.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estoque (saldos / movimentações)
    |--------------------------------------------------------------------------
    */
    'stock' => [
        'balances_list' => 'Lista de saldos de estoque.',
        'movements_list' => 'Lista de movimentações de estoque.',
        'entry_created' => 'Entrada de estoque registrada com sucesso.',
        'exit_created' => 'Saída de estoque registrada com sucesso.',
        'return_created' => 'Devolução de estoque registrada com sucesso.',
        'loss_created' => 'Perda de estoque registrada com sucesso.',
        'adjustment_created' => 'Ajuste de estoque registrado com sucesso.',
        'transfer_created' => 'Transferência de estoque registrada com sucesso.',
        'block_created' => 'Bloqueio de estoque registrado com sucesso.',
        'unblock_created' => 'Desbloqueio de estoque registrado com sucesso.',
        'reserve_created' => 'Reserva de estoque registrada com sucesso.',
        'reserve_cancel_created' => 'Cancelamento de reserva registrado com sucesso.',
        'invalid_product' => 'Tipo de ingresso inválido para esta empresa.',
        'invalid_location' => 'Local de estoque inválido para esta empresa.',
        'invalid_reserve_movement' => 'Movimento de reserva inválido ou inexistente para esta empresa.',
        'transfer_same_location' => 'O local de destino deve ser diferente do local de origem.',
        'insufficient_balance' => 'Saldo disponível insuficiente para esta operação.',
        'insufficient_blocked_balance' => 'Saldo bloqueado insuficiente para esta operação.',
        'insufficient_reserved_balance' => 'Saldo reservado insuficiente para esta operação.',
        'reserve_already_cancelled' => 'Esta reserva já foi cancelada anteriormente.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pedido
    |--------------------------------------------------------------------------
    */
    'order' => [
        'list' => 'Lista de pedidos.',
        'show' => 'Pedido exibido com sucesso.',
        'fiscal_preview' => 'Prévia fiscal do pedido exibida com sucesso.',
        'fiscal_document_shown' => 'Documento fiscal do pedido exibido com sucesso.',
        'fiscal_document_not_found' => 'Este pedido ainda não possui um documento fiscal preparado.',
        'fiscal_document_prepared' => 'Documento fiscal interno do pedido preparado com sucesso.',
        'fiscal_document_submitted' => 'Documento fiscal interno encaminhado para o fluxo manual com sucesso.',
        'fiscal_document_status_synced' => 'Andamento do documento fiscal interno atualizado com sucesso.',
        'fiscal_document_canceled' => 'Documento fiscal preparado cancelado com sucesso.',
        'fiscal_document_cancel_unavailable' => 'Não existe um documento fiscal preparado ativo para cancelar neste pedido.',
        'fiscal_document_submit_unavailable' => 'Não existe um rascunho fiscal preparado pronto para envio neste pedido.',
        'fiscal_document_sync_unavailable' => 'Não existe um documento fiscal em processamento disponível para consulta neste pedido.',
        'fiscal_document_already_submitted' => 'Este documento fiscal já foi enviado ao provider e está aguardando o próximo retorno operacional.',
        'fiscal_document_already_pending' => 'Este documento fiscal já está em processamento pendente no provider.',
        'fiscal_document_provider_reference_missing' => 'Este documento fiscal ainda não possui uma referência no provider para consulta de status.',
        'fiscal_document_canceled_default_reason' => 'Documento fiscal preparado cancelado manualmente.',
        'fiscal_document_invalidated_after_order_update' => 'Documento fiscal preparado cancelado porque o pedido foi alterado.',
        'fiscal_document_invalidated_after_order_cancel' => 'Documento fiscal preparado cancelado porque o pedido foi cancelado.',
        'fiscal_provider_submission_recorded' => 'Registro do fluxo fiscal interno salvo no histórico operacional.',
        'fiscal_provider_status_sync_recorded' => 'Atualização do fluxo fiscal interno salva no histórico operacional.',
        'fiscal_prepare_blocked' => 'Existem pendências fiscais no pedido. Corrija os itens apontados antes de preparar o documento fiscal.',
        'fiscal_prepare_authorized_exists' => 'Este pedido já possui um documento fiscal autorizado. Crie uma operação fiscal complementar em vez de preparar um novo documento.',
        'fiscal_prepare_submitted_exists' => 'Este pedido já possui um documento fiscal enviado ao provider. Aguarde o retorno operacional ou cancele o fluxo atual antes de preparar outro.',
        'fiscal_submit_blocked' => 'O rascunho fiscal ainda não pode ser enviado ao provider. Corrija os itens apontados antes da submissão.',
        'fiscal_submit_authorized_exists' => 'Este pedido já possui um documento fiscal autorizado. Não é possível reenviar um novo documento pelo fluxo padrão.',
        'fiscal_provider_token_missing' => 'O provider fiscal configurado para esta empresa ainda está sem token de API.',
        'fiscal_provider_certificate_missing' => 'O provider fiscal configurado para esta empresa exige o certificado A1 antes da emissão.',
        'fiscal_provider_certificate_password_missing' => 'O provider fiscal configurado para esta empresa exige a senha do certificado A1.',
        'fiscal_nfce_csc_missing' => 'A emissão de NFC-e exige CSC ID e CSC configurados para a empresa.',
        'created' => 'Pedido criado com sucesso.',
        'delivered' => 'Pedido marcado como entregue com sucesso.',
        'undelivered' => 'Entrega do pedido desfeita com sucesso.',
        'paid' => 'Pedido marcado como pago com sucesso.',
        'partially_paid' => 'Pagamento parcial do pedido registrado com sucesso.',
        'unpaid' => 'Pagamento do pedido desfeito com sucesso.',
        'installment_paid' => 'Parcela marcada como paga com sucesso.',
        'installment_unpaid' => 'Pagamento da parcela desfeito com sucesso.',
        'cancelled' => 'Pedido cancelado com sucesso.',
        'invalid_client' => 'Cliente inválido para esta empresa.',
        'invalid_stock_location' => 'Local de estoque inválido para esta empresa.',
        'invalid_product' => 'Tipo de ingresso ou produto do evento inválido para esta empresa.',
        'item_missing_sellable' => 'Cada item precisa referenciar exatamente um tipo de ingresso ou produto do evento.',
        'installments_count_required' => 'A quantidade de parcelas é obrigatória para pedido parcelado.',
        'already_cancelled' => 'Este pedido já foi cancelado.',
        'already_delivered' => 'Este pedido já foi marcado como entregue.',
        'not_delivered' => 'Este pedido ainda não foi marcado como entregue.',
        'already_paid' => 'Este pedido já foi marcado como pago.',
        'not_paid' => 'Este pedido ainda não foi marcado como pago.',
        'use_installment_pay' => 'Pedido parcelado deve ser pago parcela a parcela.',
        'use_installment_unpay' => 'Pedido parcelado deve ter o pagamento desfeito parcela a parcela.',
        'not_installment' => 'Este pedido não é parcelado.',
        'installment_already_paid' => 'Esta parcela já foi paga.',
        'installment_not_paid' => 'Esta parcela ainda não foi paga.',
        'cannot_cancel_paid' => 'Não é possível cancelar um pedido com pagamento já registrado. É necessário um estorno manual.',
        'payment_charge_created' => 'Cobrança de pagamento gerada com sucesso.',
        'payment_charge_already_active' => 'Este pedido já possui uma cobrança de pagamento em aberto.',
        'missing_reservation' => 'Reserva de estoque não encontrada para este item do pedido.',
        'no_default_stock_location' => 'Nenhum local de estoque padrão encontrado para esta empresa.',
        'mark_as_paid_requires_non_installment' => 'Um pedido parcelado não pode nascer marcado como pago; pague-o parcela a parcela.',
        'installment_created' => 'Parcela criada com sucesso.',
        'installment_updated' => 'Parcela atualizada com sucesso.',
        'installment_deleted' => 'Parcela removida com sucesso.',
        'installment_immutable_when_paid' => 'Esta parcela já foi paga e não pode ser modificada ou removida.',
        'installment_number_duplicate' => 'Já existe uma parcela com este número para este pedido.',
        'installment_sum_mismatch' => 'A soma de todas as parcelas (:sum) precisa bater exatamente com o total do pedido (:total). Diferença: :diff.',
        'installment_not_found_in_order' => 'Uma das parcelas referenciadas não pertence a este pedido ou não existe.',
        'installment_duplicate_reference' => 'A mesma parcela não pode ser referenciada duas vezes na mesma requisição.',
        'installments_reallocated' => 'Parcelas atualizadas com sucesso.',
        'refund_created' => 'Estorno registrado com sucesso.',
        'refund_list' => 'Lista de estornos do pedido.',
        'refund_partial_requires_tickets' => 'Estorno parcial exige selecionar ao menos um ingresso afetado.',
        'refund_ticket_not_found_in_order' => 'Um dos ingressos informados não pertence a este pedido, não existe ou já foi estornado.',
        'refund_no_eligible_tickets' => 'Não há ingressos elegíveis para estorno neste pedido.',
        'refund_amount_exceeds_paid' => 'O valor do estorno excede o valor disponível para estorno (:available).',
        'tracking_shown' => 'Acompanhamento do pedido exibido com sucesso.',
        'items_updated' => 'Itens do pedido atualizados com sucesso.',
        'item_not_found_in_order' => 'Um dos itens referenciados não pertence a este pedido ou não existe.',
        'item_duplicate_reference' => 'O mesmo item não pode ser referenciado duas vezes na mesma requisição.',
        'discount_limit_exceeded' => 'O desconto aplicado ultrapassa o limite de :limit% permitido para o seu perfil.',
        'not_pending_approval' => 'Este pedido não está aguardando aprovação.',
        'approved' => 'Pedido aprovado com sucesso.',
        'rejected' => 'Pedido recusado com sucesso.',
        'awaiting_approval' => 'Este pedido ainda está aguardando aprovação. Aprove-o antes de entregar ou registrar pagamento.',
        'order_rejected' => 'Este pedido foi recusado e não pode ser entregue ou pago.',
        'dispatched' => 'Pedido marcado como saiu para entrega com sucesso.',
        'already_out_for_delivery' => 'Este pedido já foi marcado como saiu para entrega.',
        'undispatched' => 'Status "saiu para entrega" desfeito com sucesso.',
        'not_out_for_delivery' => 'Este pedido não está marcado como saiu para entrega.',
        'prep_link_generated' => 'Link de preparo gerado com sucesso.',
        'prep_shown' => 'Preparo do pedido exibido com sucesso.',
        'cancellation_request_not_storefront' => 'Somente pedidos feitos pela loja podem ter o cancelamento solicitado por este canal.',
        'cancellation_already_requested' => 'Já existe uma solicitação de cancelamento em aberto para este pedido.',
        'no_cancellation_requested' => 'Este pedido não possui uma solicitação de cancelamento em aberto.',
        'cancellation_requested_by_client_default' => 'Cancelamento solicitado pelo cliente.',
        'cancellation_approved' => 'Cancelamento aprovado com sucesso.',
        'cancellation_rejected' => 'Solicitação de cancelamento recusada com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bilheteria pública
    |--------------------------------------------------------------------------
    */
    'storefront' => [
        'tenant_shown' => 'Bilheteria exibida com sucesso.',
        'products_listed' => 'Catálogo público listado com sucesso.',
        'categories_listed' => 'Categorias listadas com sucesso.',
        'checkout_created' => 'Pedido realizado com sucesso. Aguarde a confirmação da bilheteria.',
        'storefront_disabled' => 'A bilheteria online desta empresa está desativada no momento.',
        'store_closed' => 'A bilheteria está fechada no momento. Confira o horário de funcionamento.',
        'below_minimum_order' => 'Pedido abaixo do valor mínimo. Faltam R$ :missing para atingir o mínimo.',
        'delivery_area_not_served' => 'Ainda não entregamos neste bairro.',
        'delivery_fee_shown' => 'Taxa de entrega exibida com sucesso.',
        'favorite_added' => 'Evento adicionado aos favoritos.',
        'favorite_removed' => 'Evento removido dos favoritos.',
        'favorites_listed' => 'Lista de eventos favoritos.',
        'order_rated' => 'Avaliação registrada com sucesso.',
        'order_already_rated' => 'Este pedido já foi avaliado.',
        'cart_event_recorded' => 'Evento de carrinho registrado com sucesso.',
        'store_pickup_not_enabled' => 'Esta empresa ainda não aceita retirada presencial.',
        'store_pickup_address_missing' => 'A empresa ainda não configurou o ponto de retirada, então não é possível retirar o pedido no momento.',
        'delivery_not_enabled' => 'Esta empresa não está aceitando entregas no momento.',
        'availability_shown' => 'Disponibilidade exibida com sucesso.',
    ],

    'inventory_hold' => [
        'created' => 'Reserva temporária criada com sucesso.',
        'show' => 'Reserva temporária exibida com sucesso.',
        'renewed' => 'Reserva temporária renovada com sucesso.',
        'released' => 'Reserva temporária liberada com sucesso.',
        'invalid_item' => 'Cada item da reserva deve informar ingresso ou adicional, mas não ambos.',
        'session_required' => 'Selecione uma sessão antes de reservar itens deste evento.',
        'seat_requires_ticket_type' => 'A seleção de assento precisa estar vinculada a um tipo de ingresso.',
        'seat_quantity_invalid' => 'Assentos individuais só podem ser reservados uma vez por item.',
        'seat_capacity_exceeded' => 'A quantidade solicitada ultrapassa a capacidade disponível deste lugar.',
        'invalid_seat' => 'Assento inválido para este evento.',
        'duplicate_seat' => 'Um mesmo assento não pode ser reservado duas vezes na mesma operação.',
        'ticket_type_session_mismatch' => 'O tipo de ingresso escolhido não pertence à sessão informada.',
        'max_per_order_exceeded' => 'A quantidade solicitada ultrapassa o limite permitido por pedido.',
        'insufficient_availability' => 'Não há disponibilidade suficiente para um ou mais itens selecionados.',
        'not_active' => 'Esta reserva temporária não está mais ativa.',
        'checkout_mismatch' => 'Os itens do checkout não correspondem mais à reserva temporária ativa.',
    ],

    'table_reservation' => [
        'list' => 'Reservas de mesa listadas com sucesso.',
        'created' => 'Reserva criada com sucesso.',
        'public_available' => 'Dados de reservas carregados com sucesso.',
        'public_created' => 'Reserva online confirmada com sucesso.',
        'seated' => 'Cliente acomodado e comanda aberta com sucesso.',
        'cancelled' => 'Reserva cancelada com sucesso.',
        'no_show' => 'Reserva marcada como não compareceu.',
        'availability' => 'Disponibilidade de mesas carregada com sucesso.',
        'past_time' => 'Não é possível criar uma reserva em um horário que já passou.',
        'no_availability' => 'Não há mesas disponíveis para esse horário e quantidade de pessoas.',
        'table_capacity_insufficient' => 'A mesa escolhida não comporta a quantidade de pessoas informada.',
        'invalid_state_for_seating' => 'Esta reserva não pode mais ser acomodada.',
        'table_required_to_seat' => 'A reserva precisa estar vinculada a uma mesa antes de acomodar o cliente.',
        'already_finished' => 'Esta reserva já foi finalizada e não pode mais ser alterada.',
        'public_unavailable' => 'Esta loja não está recebendo reservas online no momento.',
    ],

    'table_waitlist' => [
        'list' => 'Fila de espera listada com sucesso.',
        'created' => 'Cliente adicionado à fila de espera com sucesso.',
        'called' => 'Cliente chamado com sucesso.',
        'seated' => 'Cliente acomodado e comanda aberta com sucesso.',
        'cancelled' => 'Entrada da fila cancelada com sucesso.',
        'invalid_state_for_call' => 'Esta entrada da fila não pode ser chamada neste momento.',
        'invalid_state_for_seating' => 'Esta entrada da fila não pode mais ser acomodada.',
        'table_capacity_insufficient' => 'A mesa escolhida não comporta a quantidade de pessoas informada.',
        'already_finished' => 'Esta entrada da fila já foi finalizada e não pode mais ser alterada.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Horário de funcionamento da bilheteria
    |--------------------------------------------------------------------------
    */
    'store_business_hours' => [
        'show' => 'Horário de funcionamento exibido com sucesso.',
        'updated' => 'Horário de funcionamento atualizado com sucesso.',
        'invalid_days' => 'É necessário informar exatamente os 7 dias da semana (0 a 6), sem repetição.',
        'closes_at_equal_to_opens_at' => 'Horário de abertura e fechamento não podem ser iguais.',
        'too_many_shifts' => 'É permitido no máximo 4 turnos por dia.',
        'overlapping_shifts' => 'Os turnos deste dia não podem se sobrepor.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cupons de desconto
    |--------------------------------------------------------------------------
    */
    'coupon' => [
        'list' => 'Lista de cupons.',
        'created' => 'Cupom criado com sucesso.',
        'updated' => 'Cupom atualizado com sucesso.',
        'deleted' => 'Cupom removido com sucesso.',
        'invalid' => 'Cupom inválido.',
        'expired' => 'Este cupom expirou.',
        'not_yet_available' => 'Este cupom ainda não está disponível.',
        'below_minimum_order' => 'Este cupom exige um pedido mínimo de R$ :minimum.',
        'usage_limit_reached' => 'Este cupom já atingiu o limite de uso.',
        'validated' => 'Cupom válido.',
        'payment_method_not_allowed' => 'Este cupom só pode ser usado com determinada forma de pagamento. Selecione a forma de pagamento antes de aplicar o cupom.',
    ],

    'tax_rule' => [
        'list' => 'Lista de regras tributárias.',
        'created' => 'Regra tributária criada com sucesso.',
        'updated' => 'Regra tributária atualizada com sucesso.',
        'deleted' => 'Regra tributária removida com sucesso.',
        'invalid_validity_range' => 'A data final da vigência não pode ser anterior à data inicial.',
    ],

    'fiscal_operation_profile' => [
        'list' => 'Lista de perfis fiscais.',
        'created' => 'Perfil fiscal criado com sucesso.',
        'updated' => 'Perfil fiscal atualizado com sucesso.',
        'deleted' => 'Perfil fiscal removido com sucesso.',
    ],

    'fiscal_readiness' => [
        'shown' => 'Checklist de prontidão fiscal exibido com sucesso.',
        'provider_manual_mode' => 'A empresa está em modo fiscal manual. O sistema prepara e acompanha rascunhos internos, mas ainda não transmite documentos oficialmente para SEFAZ ou prefeitura.',
        'provider_token_missing' => 'O provider fiscal configurado ainda está sem token de API.',
        'provider_certificate_missing' => 'O provider fiscal configurado ainda está sem certificado digital A1.',
        'provider_certificate_password_missing' => 'O provider fiscal configurado ainda está sem a senha do certificado A1.',
        'nfce_csc_missing' => 'Há perfil ativo de NFC-e, mas ainda faltam CSC ID e CSC no cadastro da empresa.',
        'provider_ready' => 'O provider fiscal ":provider" já possui a configuração mínima esperada para a próxima etapa da emissão.',
    ],

    'tenant_feature_override' => [
        'list' => 'Lista de liberações/bloqueios da empresa.',
        'synced' => 'Liberações/bloqueios da empresa sincronizados com sucesso.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Preço promocional por produto (Delivery Fase 3)
    |--------------------------------------------------------------------------
    */
    'product_promotion' => [
        'list' => 'Lista de promoções.',
        'upserted' => 'Promoção salva com sucesso.',
        'deleted' => 'Promoção removida com sucesso.',
        'discount_percentage_not_allowed' => 'Informe o percentual de desconto só quando o tipo for percentual.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Relatórios / Indicadores / Dashboard
    |--------------------------------------------------------------------------
    */
    'report' => [
        'indicators' => 'Indicadores obtidos com sucesso.',
        'charts' => 'Gráficos obtidos com sucesso.',
        'operation_health' => 'Saúde operacional obtida com sucesso.',
        'orders_list' => 'Lista do relatório de pedidos.',
        'orders_summary' => 'Resumo do relatório de pedidos.',
        'by_channel' => 'Resultado por canal de venda.',
        'clients_list' => 'Lista do relatório de clientes.',
        'receivables_list' => 'Lista do relatório de recebíveis.',
        'receivables_summary' => 'Resumo do relatório de recebíveis.',
        'receivable_interactions_list' => 'Histórico de interações do recebível.',
        'receivable_interaction_created' => 'Interação de recebível registrada com sucesso.',
        'cmv' => 'Relatório de CMV obtido com sucesso.',
    ],

    'analytics' => [
        'sales_summary' => 'Resumo de vendas obtido com sucesso.',
        'top_products' => 'Top produtos obtidos com sucesso.',
        'sales_by_location' => 'Vendas por localização obtidas com sucesso.',
        'sales_history' => 'Histórico de vendas obtido com sucesso.',
        'top_clients' => 'Top clientes obtidos com sucesso.',
        'payment_delays' => 'Atrasos de pagamento obtidos com sucesso.',
        'overdue_orders' => 'Pedidos em atraso obtidos com sucesso.',
        'abc_analysis' => 'Análise ABC obtida com sucesso.',
        'margin_summary' => 'Resumo de margem obtido com sucesso.',
        'coupon_roi' => 'Comparação de ticket por cupom obtida com sucesso.',
        'revenue_concentration' => 'Concentração de faturamento obtida com sucesso.',
        'delivery_otif' => 'OTIF de entrega obtido com sucesso.',
        'churn_clients' => 'Clientes evadidos obtidos com sucesso.',
        'stalled_products' => 'Produtos encalhados obtidos com sucesso.',
        'stock_ruptures' => 'Rupturas de estoque obtidas com sucesso.',
        'sales_by_hour' => 'Vendas por hora obtidas com sucesso.',
    ],

    'route' => [
        'candidates_list' => 'Candidatos a parada retornados com sucesso.',
    ],

    'finance' => [
        'reconciliation_listed' => 'Conciliação financeira listada com sucesso.',
        'reconciliation_summary' => 'Resumo da conciliação financeira obtido com sucesso.',
    ],

    'payment_admin' => [
        'issues_listed' => 'Pendências de pagamento listadas com sucesso.',
        'issue_reprocessed' => 'Pendência reprocessada com sucesso.',
        'issue_not_found' => 'Pendência não encontrada para o tipo/referência informados, ou já não está mais elegível para reprocessamento.',
        'issue_not_reprocessable' => 'Este tipo de pendência exige revisão manual e não tem reprocessamento automático.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissões (Grupo × Funcionalidade × Ação)
    |--------------------------------------------------------------------------
    */
    'permission' => [
        'denied' => 'Você não tem permissão para executar esta ação.',
        'invalid_action' => 'Ação inválida.',
        'invalid_functionality' => 'Funcionalidade inválida.',
        'sync_success' => 'Permissões sincronizadas com sucesso.',
        'no_permissions' => 'Nenhuma permissão encontrada.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    */
    'push' => [
        'subscribed' => 'Inscrição para notificações registrada com sucesso.',
        'order_approved_title' => 'Pedido aprovado',
        'order_approved_body' => 'Seu pedido foi aprovado!',
        'order_rejected_title' => 'Pedido recusado',
        'order_rejected_body' => 'Seu pedido foi recusado.',
        'order_delivered_title' => 'Pedido entregue',
        'order_delivered_body' => 'Seu pedido foi entregue!',
        'order_out_for_delivery_title' => 'Pedido saiu para entrega',
        'order_out_for_delivery_body' => 'Seu pedido saiu para entrega!',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs / Auditoria
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'recorded' => 'Registro de auditoria criado.',
        'failed' => 'Falha ao registrar auditoria.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validação
    |--------------------------------------------------------------------------
    | Dica: no FormRequest, use:
    | public function messages(){ return __('messages.validation.messages'); }
    | public function attributes(){ return __('messages.validation.attributes'); }
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'failed' => 'Falha na validação.',
        'messages' => [
            // Básicos
            'required' => 'O campo :attribute é obrigatório.',
            'present' => 'O campo :attribute deve estar presente.',
            'nullable' => 'O campo :attribute pode ser nulo.',
            'string' => 'O campo :attribute deve ser um texto.',
            'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'array' => 'O campo :attribute deve ser um array.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'integer' => 'O campo :attribute deve ser um inteiro.',
            'email' => 'O campo :attribute deve ser um e-mail válido.',
            'uuid' => 'O campo :attribute deve ser um UUID válido.',
            'date' => 'O campo :attribute deve ser uma data válida.',

            // Tamanho
            'min' => 'O campo :attribute deve ter no mínimo :min caracteres.',
            'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'between' => 'O campo :attribute deve estar entre :min e :max.',
            'size' => 'O campo :attribute deve ter tamanho :size.',

            // Regras comuns
            'unique' => 'O campo :attribute já está em uso.',
            'exists' => 'O valor informado no campo :attribute é inválido.',
            'in' => 'O valor informado no campo :attribute é inválido.',
            'not_in' => 'O valor informado no campo :attribute é inválido.',
            'regex' => 'O formato do campo :attribute é inválido.',
            'confirmed' => 'A confirmação do campo :attribute não confere.',
            'same' => 'O campo :attribute e :other devem ser iguais.',
            'different' => 'O campo :attribute e :other devem ser diferentes.',

            // Senha (se usar)
            'password' => 'A senha informada é inválida.',
        ],

        // Nome amigável dos atributos (para :attribute)
        'attributes' => [
            // Auth
            'email' => 'e-mail',
            'password' => 'senha',
            'refresh_token' => 'refresh token',

            // Usuário
            'name' => 'nome',
            'is_active' => 'ativo',
            'group_uuids' => 'grupos',
            'group_uuids.*' => 'grupo',

            // Meus dados
            'avatar' => 'foto',
            'current_password' => 'senha atual',
            'new_password' => 'nova senha',
            'new_email' => 'novo e-mail',
            'token' => 'token',

            // Grupo
            'slug' => 'slug',
            'user_uuids' => 'usuários',
            'user_uuids.*' => 'usuário',
            'permissions' => 'permissões',
            'permissions.*.functionality_slug' => 'slug da funcionalidade',
            'permissions.*.actions' => 'ações',
            'permissions.*.actions.*' => 'ação',

            // Funcionalidade
            'description' => 'descrição',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paginação / Meta
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'invalid_per_page' => 'Parâmetro per_page inválido.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    */
    'throttle' => [
        'too_many_requests' => 'Muitas tentativas. Tente novamente em :seconds segundos.',
        'too_many_attempts' => 'Muitas tentativas. Tente novamente em :minutes minuto(s).',
    ],

    /*
    |--------------------------------------------------------------------------
    | Portal do cliente final
    |--------------------------------------------------------------------------
    */
    'portal' => [
        'otp_mail_subject' => 'Seu código de acesso PegaTicket',
        'otp_sent' => 'Se o e-mail informado tiver uma conta, um código de acesso foi enviado.',
        'otp_verified' => 'Login realizado com sucesso.',
        'invalid_code' => 'Código inválido.',
        'expired_code' => 'Este código expirou. Solicite um novo código.',
        'too_many_attempts' => 'Muitas tentativas erradas. Solicite um novo código.',
        'link_confirmed' => 'Loja vinculada com sucesso ao seu histórico.',
        'orders_shown' => 'Pedidos do cliente.',
        'me_shown' => 'Perfil do cliente.',
        'order_items_shown' => 'Itens do pedido para novo pedido.',
        'addresses_listed' => 'Seus endereços.',
        'address_updated' => 'Endereço atualizado com sucesso.',
        'coupon_redemptions_listed' => 'Seus cupons utilizados.',
        'cancellation_requested' => 'Cancelamento solicitado com sucesso. Aguarde a aprovação da loja.',
        'tickets_shown' => 'Seus ingressos.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulo do contador (roadmap 2C)
    |--------------------------------------------------------------------------
    */
    'accounting_auth' => [
        'registered' => 'Escritório cadastrado. Configure o TOTP e confirme o primeiro código para ativar o acesso.',
        'email_already_registered' => 'Já existe um escritório cadastrado com este e-mail.',
        'totp_enabled' => 'TOTP confirmado. Login liberado.',
        'totp_not_configured' => 'TOTP ainda não configurado para este escritório.',
        'totp_setup_required' => 'Conclua a configuração do TOTP antes de fazer login.',
        'invalid_totp' => 'Código TOTP inválido.',
        'invalid_credentials' => 'E-mail ou senha inválidos.',
        'logged_in' => 'Login realizado com sucesso.',
        'me' => 'Dados do escritório.',
    ],

    'accounting_access' => [
        'requested' => 'Solicitação de acesso enviada. Aguardando aprovação da empresa.',
        'my_links' => 'Seus vínculos com empresas.',
        'list' => 'Solicitações de acesso do contador.',
        'approved' => 'Acesso do contador aprovado.',
        'revoked' => 'Acesso do contador revogado.',
        'tenant_not_found' => 'Nenhuma empresa ativa encontrada com este CNPJ.',
        'already_approved' => 'O contador já tem acesso aprovado a esta empresa.',
        'already_pending' => 'Já existe uma solicitação pendente para esta empresa.',
        'not_approved' => 'Acesso não aprovado para esta empresa.',
    ],

    'accounting_report' => [
        'sales' => 'Relatório de vendas.',
        'cash_flow' => 'Livro-caixa.',
        'dre' => 'DRE simplificado.',
    ],

    'accounting_message' => [
        'list' => 'Mensagens da central de pendências.',
        'sent' => 'Mensagem enviada com sucesso.',
    ],

    'help_request' => [
        'list' => 'Chamados de suporte.',
        'created' => 'Chamado aberto com sucesso.',
    ],

    'station' => [
        'list' => 'Estações.',
        'created' => 'Estação criada com sucesso.',
        'updated' => 'Estação atualizada com sucesso.',
        'deleted' => 'Estação excluída com sucesso.',
        'tickets' => 'Fila da estação.',
        'name_exists' => 'Já existe uma estação com este nome.',
    ],

    'table' => [
        'list' => 'Mesas.',
        'created' => 'Mesa criada com sucesso.',
        'updated' => 'Mesa atualizada com sucesso.',
        'deleted' => 'Mesa excluída com sucesso.',
        'label_exists' => 'Já existe uma mesa com esta identificação.',
    ],

    'comanda' => [
        'list' => 'Comandas abertas.',
        'opened' => 'Comanda aberta com sucesso.',
        'offline_snapshot_ready' => 'Base offline operacional atualizada com sucesso.',
        'closed' => 'Comanda fechada com sucesso.',
        'item_added' => 'Item adicionado à comanda.',
        'item_prep_status_updated' => 'Status de preparo atualizado.',
        'not_open' => 'Esta comanda não está aberta.',
        'already_closed' => 'Esta comanda já foi fechada.',
        'already_cancelled' => 'Esta comanda foi cancelada.',
        'no_items_to_close' => 'A comanda não tem itens para fechar.',
        'item_already_sent' => 'Este item já foi enviado à estação.',
        'item_terminal_state' => 'Este item já está entregue ou cancelado.',
        'invalid_prep_transition' => 'Transição de preparo inválida.',
        'cancel_reason_required' => 'Informe o motivo do cancelamento do item.',
        'no_default_stock_location' => 'Nenhum local de estoque padrão configurado.',
        'payment_mismatch' => 'A soma das formas de pagamento não confere com o total da conta.',
    ],

    'api_key' => [
        'list' => 'Chaves de API.',
        'created' => 'Chave de API criada com sucesso. Copie agora: ela não será mostrada novamente.',
        'revoked' => 'Chave de API revogada com sucesso.',
        'missing' => 'Informe a chave de API no cabeçalho Authorization.',
        'invalid' => 'Chave de API inválida, revogada ou empresa inativa.',
    ],

    'webhook_subscription' => [
        'list' => 'Assinaturas de webhook.',
        'show' => 'Assinatura de webhook.',
        'created' => 'Assinatura de webhook criada com sucesso. Guarde o secret: ele não será mostrado novamente.',
        'updated' => 'Assinatura de webhook atualizada com sucesso.',
        'deleted' => 'Assinatura de webhook excluída com sucesso.',
        'deliveries' => 'Histórico de entregas do webhook.',
    ],

    'public_api' => [
        'orders_list' => 'Pedidos.',
        'orders_show' => 'Pedido.',
        'products_list' => 'Produtos.',
    ],
];
