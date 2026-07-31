# Mapeamento Global de Telas - Padronização Visual

Data de referência: 29 de julho de 2026

## Objetivo

Mapear todas as telas reais do frontend antes da propagação final do padrão visual mobile/desktop, para evitar ajustes soltos e garantir cobertura integral.

## Fonte do mapeamento

- Rotas reais: `web/src/routes/AppRoutes.tsx`
- Páginas: `web/src/pages/**`
- Shells e componentes estruturais: `web/src/components/**`

## Padrões-base já existentes

- `CrudFormShell`: formulários CRUD
- `CrudListPage`: listagens CRUD
- `PageHeader`: cabeçalho interno de páginas operacionais
- `PortalShell`: área autenticada do cliente final
- `AccountingShell` e `AccountingAuthLayout`: área do contador
- `SettingsHubLayout` e `SettingsBlockPage`: hub de configurações
- `AuthPageShell`: páginas públicas de autenticação e recuperação

## Inventário global por domínio

### 1. Públicas e autenticação

- `/login` -> `LoginPage`
- `/cadastro` -> `SignupPage`
- `/termos` -> `LegalDocumentPage`
- `/privacidade` -> `LegalDocumentPage`
- `/convite/:token` -> `AcceptInvitePage`
- `/confirmar-email/:token` -> `ConfirmEmailPage`
- `/esqueci-senha` -> `ForgotPasswordPage`
- `/redefinir-senha/:token` -> `ResetPasswordPage`

### 2. Portal do cliente

- `/portal/entrar` -> `PortalLoginPage`
- `/portal/pedidos` -> `PortalOrdersPage`
- `/portal/favoritos` -> `PortalFavoritesPage`
- `/portal/enderecos` -> `PortalAddressesPage`
- `/portal/vouchers` -> `PortalVouchersPage`
- `/portal/perfil` -> `PortalProfilePage`
- `/rastreio/:uuid` -> `OrderTrackingPage`

### 3. Loja pública e reservas

- `/loja/:slug` -> `StorefrontCatalogPage`
- `/loja/:slug/perfil` -> `StorefrontProfilePage`
- `/loja/:slug/reservas` -> `StorefrontReservationPage`
- `/loja/:slug/carrinho` -> `StorefrontCartPage`
- `/loja/:slug/checkout` -> `StorefrontCheckoutPage`
- `/reservas/:slug` -> `StorefrontReservationPage`
- `/preparo/:orderUuid` -> `PrepOrderPage`

### 4. Contador

- `/contador/cadastro` -> `AccountingRegisterPage`
- `/contador/cadastro/confirmar-totp` -> `AccountingConfirmTotpPage`
- `/contador/entrar` -> `AccountingLoginPage`
- `/contador/empresas` -> `AccountingCompaniesPage`
- `/contador/solicitar-acesso` -> `AccountingRequestAccessPage`
- `/contador/empresas/:tenantUuid` -> `AccountingCompanyReportsPage`
- `/contador/empresas/:tenantUuid/produtos-fiscais` -> `AccountingCompanyProductsPage`
- `/contador/empresas/:tenantUuid/clientes-fiscais` -> `AccountingCompanyClientsPage`
- `/contador/empresas/:tenantUuid/regras-tributarias` -> `AccountingCompanyTaxRulesPage`
- `/contador/empresas/:tenantUuid/pendencias` -> `AccountingCompanyMessagesPage`

### 5. Núcleo operacional autenticado

- `/` -> `DashboardPage`
- `/minha-conta` -> `MyAccountPage`
- `/treinamentos` -> `TrainingCenterPage`
- `/suporte` -> `SupportTicketsPage`
- `/redes-sociais` -> `SocialMediaPage`
- `/analises` -> `AnalyticsPage`
- `/rotas` -> `RoutePlannerPage`
- `/financeiro/conciliacao` -> `ReconciliationPage`

### 6. Clientes

- `/clientes` -> `ClientListPage`
- `/clientes/novo` -> `ClientFormPage`
- `/clientes/:uuid/editar` -> `ClientFormPage`
- `/clientes/:uuid/pedidos` -> `ClientOrdersPage`
- `/clientes/categorias` -> `ClientCategoryListPage`
- `/clientes/categorias/nova` -> `ClientCategoryFormPage`
- `/clientes/categorias/:uuid/editar` -> `ClientCategoryFormPage`
- `/clientes/dias-ideais` -> `DiaIdealListPage`
- `/clientes/dias-ideais/novo` -> `DiaIdealFormPage`
- `/clientes/dias-ideais/:uuid/editar` -> `DiaIdealFormPage`
- `/clientes/periodos-ideais` -> `PeriodoIdealListPage`
- `/clientes/periodos-ideais/novo` -> `PeriodoIdealFormPage`
- `/clientes/periodos-ideais/:uuid/editar` -> `PeriodoIdealFormPage`

### 7. Cadastros geográficos

- `/estados` -> `EstadoListPage`
- `/estados/novo` -> `EstadoFormPage`
- `/estados/:uuid/editar` -> `EstadoFormPage`
- `/cidades` -> `CidadeListPage`
- `/cidades/novo` -> `CidadeFormPage`
- `/cidades/:uuid/editar` -> `CidadeFormPage`
- `/bairros` -> `BairroListPage`
- `/bairros/novo` -> `BairroFormPage`
- `/bairros/:uuid/editar` -> `BairroFormPage`
- `/enderecos` -> `EnderecoListPage`
- `/enderecos/novo` -> `EnderecoFormPage`
- `/enderecos/:uuid/editar` -> `EnderecoFormPage`

### 8. Produtos e catálogo interno

- `/produtos` -> `ProductListPage`
- `/produtos/novo` -> `ProductFormPage`
- `/produtos/:uuid/editar` -> `ProductFormPage`
- `/produtos/categorias` -> `ProductCategoryListPage`
- `/produtos/categorias/nova` -> `ProductCategoryFormPage`
- `/produtos/categorias/:uuid/editar` -> `ProductCategoryFormPage`
- `/produtos/tipos` -> `ProductTypeListPage`
- `/produtos/tipos/novo` -> `ProductTypeFormPage`
- `/produtos/tipos/:uuid/editar` -> `ProductTypeFormPage`

### 9. Estoque

- `/estoque/locais` -> `StockLocationListPage`
- `/estoque/locais/novo` -> `StockLocationFormPage`
- `/estoque/locais/:uuid/editar` -> `StockLocationFormPage`
- `/estoque/saldos` -> `StockBalanceListPage`
- `/estoque/movimentos` -> `StockMovementListPage`
- `/estoque/movimentos/nova` -> `StockMovementFormPage`

### 10. Pedidos

- `/pedidos` -> `OrderListPage`
- `/pedidos-manuais` -> `OrderListPage`
- `/pedidos/novo` -> `OrderFormPage`
- `/pedidos-loja` -> `StorefrontOrderManagementPage`
- `/pedidos-ifood` -> `MarketplaceOrdersPage`

### 11. PDV e balcão

- `/pdv` -> `PdvSalePage`
- `/pdv/fechar` -> `PdvCloseSessionPage`
- `/pdv/recibo` -> `PdvReceiptPrintView`
- `/balcao/mesas` -> `BalcaoTablesPage`
- `/balcao/comandas/:uuid` -> `BalcaoComandaPage`
- `/balcao/kds` -> `BalcaoKdsPage`
- `/balcao/recibo` -> `BalcaoReceiptView`

### 12. Relatórios

- `/relatorios/canais` -> `ChannelReportPage`
- `/relatorios/pedidos` -> `OrderReportListPage`
- `/relatorios/clientes` -> `ClientReportListPage`
- `/relatorios/recebiveis` -> `ReceivableReportListPage`

### 13. Administração global

- `/admin/usuarios` -> `UserListPage`
- `/admin/usuarios/novo` -> `UserFormPage`
- `/admin/usuarios/:uuid/editar` -> `UserFormPage`
- `/admin/grupos` -> `GroupListPage`
- `/admin/grupos/novo` -> `GroupFormPage`
- `/admin/grupos/:uuid/editar` -> `GroupFormPage`
- `/admin/funcionalidades` -> `FunctionalityListPage`
- `/admin/funcionalidades/nova` -> `FunctionalityFormPage`
- `/admin/funcionalidades/:uuid/editar` -> `FunctionalityFormPage`
- `/admin/planos` -> `PlanListPage`
- `/admin/planos/novo` -> `PlanFormPage`
- `/admin/planos/:uuid/editar` -> `PlanFormPage`
- `/admin/tenants` -> `TenantListPage`
- `/admin/tenants/novo` -> `TenantFormPage`
- `/admin/tenants/:uuid/editar` -> `TenantFormPage`
- `/admin/tenant-roles` -> `TenantRoleListPage`
- `/admin/tenant-roles/novo` -> `TenantRoleFormPage`
- `/admin/tenant-roles/:uuid/editar` -> `TenantRoleFormPage`
- `/admin/tenant-users` -> `TenantUserListPage`
- `/admin/tenant-users/novo` -> `TenantUserFormPage`
- `/admin/tenant-users/convidar` -> `TenantUserInviteFormPage`
- `/admin/tenant-users/:uuid/editar` -> `TenantUserFormPage`
- `/admin/auditoria` -> `AuditLogListPage`
- `/admin/pagamentos-pendencias` -> `PaymentIssuesListPage`

### 14. Configurações

- `/configuracoes` -> `SettingsIndexPage`
- `/configuracoes/empresa` -> `CompanyBlock`
- `/configuracoes/pedidos` -> `OperationsBlock`
- `/configuracoes/horario-endereco` -> `ScheduleAddressBlock`
- `/configuracoes/pagamento` -> `PaymentBlock`
- `/configuracoes/dados-privacidade` -> `DataPrivacyBlock`
- `/configuracoes/loja-online` -> `StoreBusinessSettingsPage`
- `/configuracoes/assinatura` -> `SubscriptionPage`
- `/configuracoes/integracoes` -> `IntegrationsPage`
- `/configuracoes/contadores` -> `AccountingAccessPage`
- `/configuracoes/regras-tributarias` -> `TaxRuleListPage`
- `/configuracoes/regras-tributarias/nova` -> `TaxRuleFormPage`
- `/configuracoes/regras-tributarias/:uuid/editar` -> `TaxRuleFormPage`
- `/configuracoes/perfis-fiscais` -> `FiscalOperationProfileListPage`
- `/configuracoes/perfis-fiscais/novo` -> `FiscalOperationProfileFormPage`
- `/configuracoes/perfis-fiscais/:uuid/editar` -> `FiscalOperationProfileFormPage`
- `/empresa` -> redirect para `/configuracoes/assinatura`

## Classificação por padrão estrutural

### Grupo A - Telas já fortemente padronizadas por shell reutilizável

- CRUD com `CrudFormShell`
- CRUD com `CrudListPage`
- Portal com `PortalShell`
- Contador com `AccountingShell` e `AccountingAuthLayout`
- Configurações com `SettingsHubLayout` e `SettingsBlockPage`

### Grupo B - Telas operacionais com `PageHeader`, mas com risco de variação interna

- `DashboardPage`
- `AnalyticsPage`
- `RoutePlannerPage`
- `IntegrationsPage`
- `SubscriptionPage`
- `StoreBusinessSettingsPage`
- `AccountingAccessPage`
- `SocialMediaPage`
- `SupportTicketsPage`
- `TrainingCenterPage`
- `MyAccountPage`
- `ChannelReportPage`

### Grupo C - Telas complexas com layout próprio e maior risco mobile

- `OrderListPage`
- `StorefrontOrderManagementPage`
- `MarketplaceOrdersPage`
- `PdvSalePage`
- `PdvCloseSessionPage`
- `BalcaoTablesPage`
- `BalcaoComandaPage`
- `BalcaoKdsPage`
- `StorefrontCheckoutPage`
- `StorefrontCatalogPage`
- `StorefrontProfilePage`
- `PortalOrdersPage`
- `PortalAddressesPage`
- `AccountingCompanyLayout`
- `AccountingCompanyProductsPage`
- `AccountingCompanyClientsPage`
- `AccountingCompanyTaxRulesPage`

### Grupo D - Páginas públicas que precisam consistência de bloco, CTA e tipografia

- `LoginPage`
- `SignupPage`
- `ForgotPasswordPage`
- `ResetPasswordPage`
- `AcceptInvitePage`
- `ConfirmEmailPage`
- `PortalLoginPage`
- `LegalDocumentPage`

## Cobertura operacional do mapeamento

- Rotas mapeadas em produção: sim
- Subárvores públicas: sim
- Subárvores autenticadas: sim
- Shells reutilizáveis: sim
- Blocos de configurações: sim
- Fluxos especiais sem rota própria, mas críticos ao layout:
  - diálogos de pedido
  - diálogos de workflow
  - upload de imagem
  - componentes de grid
  - cards do dashboard

## Prioridade de execução visual após o mapeamento

### Lote 1 - maior impacto

- Pedidos, pedidos loja, iFood
- PDV e balcão
- Loja pública e checkout
- Dashboard, assinatura e loja online

### Lote 2 - operação complementar

- Analytics, relatórios, rotas
- Minha conta, suporte, social media, treinamentos
- Contador

### Lote 3 - acabamento final

- públicas/autenticação
- portal do cliente
- diálogos e componentes transversais

## Status do mapeamento

- Inventário global concluído
- Classificação estrutural concluída
- Propagação final do padrão visual (`web/src/styles/layoutStandards.ts`) concluída em 2026-07-30 — ver detalhes em `[[design-system]]` → "Propagação final dos tokens de layoutStandards"
