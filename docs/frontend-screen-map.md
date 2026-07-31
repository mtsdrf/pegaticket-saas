# Mapa Definitivo de Telas do Frontend

Baseado nas rotas reais de [web/src/routes/AppRoutes.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/routes/AppRoutes.tsx) e nos blocos dinâmicos de [web/src/pages/Settings/blocks/registry.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Settings/blocks/registry.tsx).

## Resumo

- Total de rotas de tela mapeadas: `92`
- Fonte de verdade das rotas: `web/src/routes/AppRoutes.tsx`
- Fonte de verdade dos blocos de configurações: `web/src/pages/Settings/blocks/registry.tsx`
- Fallback global: rota inexistente redireciona para `/`

## Guardas de acesso

- `Pública`: sem autenticação
- `PortalProtectedRoute`: exige autenticação do portal do cliente
- `AccountingProtectedRoute`: exige autenticação do contador
- `ProtectedRoute`: exige autenticação interna do sistema
- `PermissionRoute`: além de autenticação interna, exige permissão específica

## 1. Públicas

| Rota | Tela | Componente | Guarda |
|---|---|---|---|
| `/login` | Login | `LoginPage` | Pública |
| `/cadastro` | Cadastro | `SignupPage` | Pública |
| `/termos` | Termos de uso | `LegalDocumentPage` | Pública |
| `/privacidade` | Política de privacidade | `LegalDocumentPage` | Pública |
| `/preparo/:orderUuid` | Tela pública de preparo | `PrepOrderPage` | Pública |
| `/convite/:token` | Aceitar convite | `AcceptInvitePage` | Pública |
| `/confirmar-email/:token` | Confirmar e-mail | `ConfirmEmailPage` | Pública |
| `/esqueci-senha` | Esqueci minha senha | `ForgotPasswordPage` | Pública |
| `/redefinir-senha/:token` | Redefinir senha | `ResetPasswordPage` | Pública |

## 2. Portal do Cliente

| Rota | Tela | Componente | Guarda |
|---|---|---|---|
| `/rastreio/:uuid` | Rastreio do pedido | `OrderTrackingPage` | Pública dentro de `PortalLayout` |
| `/portal/entrar` | Login do portal | `PortalLoginPage` | Pública dentro de `PortalLayout` |
| `/portal/pedidos` | Meus pedidos | `PortalOrdersPage` | `PortalProtectedRoute` |
| `/portal/favoritos` | Favoritos | `PortalFavoritesPage` | `PortalProtectedRoute` |
| `/portal/enderecos` | Meus endereços | `PortalAddressesPage` | `PortalProtectedRoute` |
| `/portal/vouchers` | Meus vouchers | `PortalVouchersPage` | `PortalProtectedRoute` |
| `/portal/cashback` | Cashback | `PortalCashbackPage` | `PortalProtectedRoute` |
| `/portal/perfil` | Meu perfil | `PortalProfilePage` | `PortalProtectedRoute` |

## 3. Contador

| Rota | Tela | Componente | Guarda |
|---|---|---|---|
| `/contador/cadastro` | Cadastro de contador | `AccountingRegisterPage` | Pública dentro de `AccountingLayout` |
| `/contador/cadastro/confirmar-totp` | Confirmar TOTP | `AccountingConfirmTotpPage` | Pública dentro de `AccountingLayout` |
| `/contador/entrar` | Login do contador | `AccountingLoginPage` | Pública dentro de `AccountingLayout` |
| `/contador/empresas` | Empresas vinculadas | `AccountingCompaniesPage` | `AccountingProtectedRoute` |
| `/contador/solicitar-acesso` | Solicitar acesso | `AccountingRequestAccessPage` | `AccountingProtectedRoute` |
| `/contador/empresas/:tenantUuid` | Resumo fiscal da empresa | `AccountingCompanyReportsPage` | `AccountingProtectedRoute` |
| `/contador/empresas/:tenantUuid/produtos-fiscais` | Produtos fiscais | `AccountingCompanyProductsPage` | `AccountingProtectedRoute` |
| `/contador/empresas/:tenantUuid/clientes-fiscais` | Clientes fiscais | `AccountingCompanyClientsPage` | `AccountingProtectedRoute` |
| `/contador/empresas/:tenantUuid/regras-tributarias` | Regras tributárias | `AccountingCompanyTaxRulesPage` | `AccountingProtectedRoute` |
| `/contador/empresas/:tenantUuid/pendencias` | Pendências fiscais | `AccountingCompanyMessagesPage` | `AccountingProtectedRoute` |

## 4. Loja Pública

| Rota | Tela | Componente | Guarda |
|---|---|---|---|
| `/loja/:slug` | Catálogo da loja | `StorefrontCatalogPage` | Pública |
| `/loja/:slug/perfil` | Perfil do cliente da loja | `StorefrontProfilePage` | Pública |
| `/loja/:slug/carrinho` | Carrinho | `StorefrontCartPage` | Pública |
| `/loja/:slug/checkout` | Checkout | `StorefrontCheckoutPage` | Pública |

## 5. Internas Sem Permissão Específica

| Rota | Tela | Componente | Guarda |
|---|---|---|---|
| `/` | Dashboard | `DashboardPage` | `ProtectedRoute` |
| `/minha-conta` | Minha conta | `MyAccountPage` | `ProtectedRoute` |
| `/treinamentos` | Central de treinamento | `TrainingCenterPage` | `ProtectedRoute` |

## 6. Clientes

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/clientes` | Lista de clientes | `clients:read` | `ClientListPage` |
| `/clientes/novo` | Novo cliente | `clients:create` | `ClientFormPage` |
| `/clientes/:uuid/editar` | Editar cliente | `clients:update` | `ClientFormPage` |
| `/clientes/:uuid/pedidos` | Pedidos do cliente | `clients:read` | `ClientOrdersPage` |
| `/clientes/categorias` | Categorias de cliente | `client-categories:read` | `ClientCategoryListPage` |
| `/clientes/categorias/nova` | Nova categoria de cliente | `client-categories:create` | `ClientCategoryFormPage` |
| `/clientes/categorias/:uuid/editar` | Editar categoria de cliente | `client-categories:update` | `ClientCategoryFormPage` |
| `/clientes/dias-ideais` | Dias ideais | `dias-ideais:read` | `DiaIdealListPage` |
| `/clientes/dias-ideais/novo` | Novo dia ideal | `dias-ideais:create` | `DiaIdealFormPage` |
| `/clientes/dias-ideais/:uuid/editar` | Editar dia ideal | `dias-ideais:update` | `DiaIdealFormPage` |
| `/clientes/periodos-ideais` | Períodos ideais | `periodos-ideais:read` | `PeriodoIdealListPage` |
| `/clientes/periodos-ideais/novo` | Novo período ideal | `periodos-ideais:create` | `PeriodoIdealFormPage` |
| `/clientes/periodos-ideais/:uuid/editar` | Editar período ideal | `periodos-ideais:update` | `PeriodoIdealFormPage` |

## 7. Endereço

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/estados` | Estados | `estados:read` | `EstadoListPage` |
| `/estados/novo` | Novo estado | `estados:create` | `EstadoFormPage` |
| `/estados/:uuid/editar` | Editar estado | `estados:update` | `EstadoFormPage` |
| `/cidades` | Cidades | `cidades:read` | `CidadeListPage` |
| `/cidades/novo` | Nova cidade | `cidades:create` | `CidadeFormPage` |
| `/cidades/:uuid/editar` | Editar cidade | `cidades:update` | `CidadeFormPage` |
| `/bairros` | Bairros | `bairros:read` | `BairroListPage` |
| `/bairros/novo` | Novo bairro | `bairros:create` | `BairroFormPage` |
| `/bairros/:uuid/editar` | Editar bairro | `bairros:update` | `BairroFormPage` |
| `/enderecos` | Endereços | `enderecos:read` | `EnderecoListPage` |
| `/enderecos/novo` | Novo endereço | `enderecos:create` | `EnderecoFormPage` |
| `/enderecos/:uuid/editar` | Editar endereço | `enderecos:update` | `EnderecoFormPage` |

## 8. Produtos

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/produtos` | Lista de produtos | `products:read` | `ProductListPage` |
| `/produtos/novo` | Novo produto | `products:create` | `ProductFormPage` |
| `/produtos/:uuid/editar` | Editar produto | `products:update` | `ProductFormPage` |
| `/produtos/categorias` | Categorias de produto | `product-categories:read` | `ProductCategoryListPage` |
| `/produtos/categorias/nova` | Nova categoria de produto | `product-categories:create` | `ProductCategoryFormPage` |
| `/produtos/categorias/:uuid/editar` | Editar categoria de produto | `product-categories:update` | `ProductCategoryFormPage` |
| `/produtos/tipos` | Tipos de produto | `product-types:read` | `ProductTypeListPage` |
| `/produtos/tipos/novo` | Novo tipo de produto | `product-types:create` | `ProductTypeFormPage` |
| `/produtos/tipos/:uuid/editar` | Editar tipo de produto | `product-types:update` | `ProductTypeFormPage` |

## 9. Estoque

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/estoque/locais` | Locais de estoque | `stock-locations:read` | `StockLocationListPage` |
| `/estoque/locais/novo` | Novo local de estoque | `stock-locations:create` | `StockLocationFormPage` |
| `/estoque/locais/:uuid/editar` | Editar local de estoque | `stock-locations:update` | `StockLocationFormPage` |
| `/estoque/saldos` | Saldos de estoque | `stock:read` | `StockBalanceListPage` |
| `/estoque/movimentos` | Movimentações de estoque | `stock:read` | `StockMovementListPage` |
| `/estoque/movimentos/nova` | Nova movimentação de estoque | `stock:create` | `StockMovementFormPage` |

## 10. Pedidos e Operação

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/pedidos` | Lista de pedidos | `orders:read` | `OrderListPage` |
| `/pedidos/novo` | Novo pedido | `orders:create` | `OrderFormPage` |
| `/pedidos-loja` | Pedidos da loja online | `storefront-orders:read` | `StorefrontOrderManagementPage` |
| `/pedidos-ifood` | Pedidos iFood | `api-access:read` | `MarketplaceOrdersPage` |

## 11. PDV

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/pdv` | Venda no PDV | `pdv:read` | `PdvSalePage` |
| `/pdv/fechar` | Fechamento de caixa | `pdv:read` | `PdvCloseSessionPage` |
| `/pdv/recibo` | Recibo do PDV | `pdv:read` | `PdvReceiptPrintView` |

Observação: a subárvore inteira passa antes por `PdvCashSessionGatePage`.

## 12. Balcão

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/balcao/mesas` | Mesas | `balcao:read` | `BalcaoTablesPage` |
| `/balcao/comandas/:uuid` | Comanda | `balcao:read` | `BalcaoComandaPage` |
| `/balcao/kds` | KDS / Cozinha / Bar | `balcao:read` | `BalcaoKdsPage` |
| `/balcao/recibo` | Recibo do balcão | `balcao:read` | `BalcaoReceiptView` |

## 13. Rotas e Entregas

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/rotas` | Montar rota | `routes:read` | `RoutePlannerPage` |

## 14. Analytics e Relatórios

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/analises` | Análises | `reports:read` | `AnalyticsPage` |
| `/relatorios/canais` | Resultado por canal | `reports:read` | `ChannelReportPage` |
| `/relatorios/pedidos` | Relatório de pedidos | `reports:read` | `OrderReportListPage` |
| `/relatorios/clientes` | Base de clientes | `reports:read` | `ClientReportListPage` |
| `/relatorios/recebiveis` | Recebíveis | `reports:read` | `ReceivableReportListPage` |
| `/financeiro/conciliacao` | Conciliação financeira | `finance:read` | `ReconciliationPage` |

## 15. Administração

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/admin/usuarios` | Usuários admin | `admin-users:read` | `UserListPage` |
| `/admin/usuarios/novo` | Novo usuário admin | `admin-users:create` | `UserFormPage` |
| `/admin/usuarios/:uuid/editar` | Editar usuário admin | `admin-users:update` | `UserFormPage` |
| `/admin/grupos` | Grupos | `admin-groups:read` | `GroupListPage` |
| `/admin/grupos/novo` | Novo grupo | `admin-groups:create` | `GroupFormPage` |
| `/admin/grupos/:uuid/editar` | Editar grupo | `admin-groups:update` | `GroupFormPage` |
| `/admin/funcionalidades` | Funcionalidades | `admin-functionalities:read` | `FunctionalityListPage` |
| `/admin/funcionalidades/nova` | Nova funcionalidade | `admin-functionalities:create` | `FunctionalityFormPage` |
| `/admin/funcionalidades/:uuid/editar` | Editar funcionalidade | `admin-functionalities:update` | `FunctionalityFormPage` |
| `/admin/planos` | Planos | `admin-plans:read` | `PlanListPage` |
| `/admin/planos/novo` | Novo plano | `admin-plans:create` | `PlanFormPage` |
| `/admin/planos/:uuid/editar` | Editar plano | `admin-plans:update` | `PlanFormPage` |
| `/admin/tenants` | Empresas | `admin-tenants:read` | `TenantListPage` |
| `/admin/tenants/novo` | Nova empresa | `admin-tenants:create` | `TenantFormPage` |
| `/admin/tenants/:uuid/editar` | Editar empresa | `admin-tenants:update` | `TenantFormPage` |
| `/admin/tenant-roles` | Perfis da empresa | `tenant-roles:read` | `TenantRoleListPage` |
| `/admin/tenant-roles/novo` | Novo perfil da empresa | `tenant-roles:create` | `TenantRoleFormPage` |
| `/admin/tenant-roles/:uuid/editar` | Editar perfil da empresa | `tenant-roles:update` | `TenantRoleFormPage` |
| `/admin/tenant-users` | Usuários da empresa | `tenant-users:read` | `TenantUserListPage` |
| `/admin/tenant-users/novo` | Novo usuário da empresa | `tenant-users:create` | `TenantUserFormPage` |
| `/admin/tenant-users/convidar` | Convidar usuário da empresa | `tenant-users:create` | `TenantUserInviteFormPage` |
| `/admin/tenant-users/:uuid/editar` | Editar usuário da empresa | `tenant-users:update` | `TenantUserFormPage` |
| `/admin/auditoria` | Auditoria | `admin-audit-logs:read` | `AuditLogListPage` |
| `/admin/pagamentos-pendencias` | Pendências de pagamento | `admin-payment-issues:read` | `PaymentIssuesListPage` |

## 16. Configurações

### 16.1 Hub

| Rota | Tela | Guarda | Componente |
|---|---|---|---|
| `/configuracoes` | Índice de configurações | `ProtectedRoute` | `SettingsIndexPage` |

### 16.2 Blocos Dinâmicos do Hub

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/configuracoes/empresa` | Empresa | `tenant-profile:read` | `CompanyBlock` |
| `/configuracoes/pedidos` | Pedidos e Operação | `tenant-settings:read` | `OperationsBlock` |
| `/configuracoes/horario-endereco` | Horário e Endereço | `storefront:update` | `ScheduleAddressBlock` |
| `/configuracoes/pagamento` | Pagamento | `tenant-settings:read` | `PaymentBlock` |
| `/configuracoes/cashback` | Cashback e Fidelidade | `tenant-settings:read` | `CashbackBlock` |
| `/configuracoes/retencao` | Retenção e Marketing | `reactivation:read` | `RetentionBlock` |
| `/configuracoes/dados-privacidade` | Dados e Privacidade | `tenant-profile:export` | `DataPrivacyBlock` |

### 16.3 Telas de Configuração Fora do Hub

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/configuracoes/loja-online` | Loja online | `storefront:update` | `StoreBusinessSettingsPage` |
| `/configuracoes/assinatura` | Assinatura da empresa | `subscription:read` | `SubscriptionPage` |
| `/configuracoes/integracoes` | Integrações | `api-access:read` | `IntegrationsPage` |
| `/configuracoes/regras-tributarias` | Regras tributárias | `tax-rules:read` | `TaxRuleListPage` |
| `/configuracoes/regras-tributarias/nova` | Nova regra tributária | `tax-rules:create` | `TaxRuleFormPage` |
| `/configuracoes/regras-tributarias/:uuid/editar` | Editar regra tributária | `tax-rules:update` | `TaxRuleFormPage` |
| `/configuracoes/perfis-fiscais` | Perfis fiscais | `tax-rules:read` | `FiscalOperationProfileListPage` |
| `/configuracoes/perfis-fiscais/novo` | Novo perfil fiscal | `tax-rules:create` | `FiscalOperationProfileFormPage` |
| `/configuracoes/perfis-fiscais/:uuid/editar` | Editar perfil fiscal | `tax-rules:update` | `FiscalOperationProfileFormPage` |
| `/configuracoes/contadores` | Contabilidade | `accounting-access:read` | `AccountingAccessPage` |

### 16.4 Redirect de Compatibilidade

| Rota | Comportamento |
|---|---|
| `/empresa` | redireciona para `/configuracoes/assinatura` |

## 17. Outros Módulos Internos

| Rota | Tela | Permissão | Componente |
|---|---|---|---|
| `/redes-sociais` | Redes sociais | `social-media:read` | `SocialMediaPage` |
| `/suporte` | Central de chamados | `support-tickets:read` | `SupportTicketsPage` |

## 18. Redirect Final

| Rota | Comportamento |
|---|---|
| `*` | redireciona para `/` |

## 19. Arquivos de Tela Não Roteáveis Diretamente

Estes arquivos existem em `web/src/pages`, mas não são rotas independentes:

- `Analytics/tabs/*`
- `Balcao/CancelItemDialog.tsx`
- `Balcao/CloseComandaModal.tsx`
- `Pdv/PdvSessionContext.tsx`
- `Portal/PortalShell.tsx`
- `Settings/SettingsBlockPage.tsx`
- `Settings/SettingsHubLayout.tsx`
- `Settings/blocks/*`
- `SocialMedia/steps/*`
- `SocialMedia/dataForms/*`

Eles compõem telas principais, mas não viram URL final por conta própria.

## 20. Observações Finais

- O documento mapeia telas por rota real, não apenas por arquivo em `pages/`.
- A contagem de `92` considera apenas telas/rotas finais acessíveis ou redirecionamentos explícitos.
- Se novas telas forem criadas, este documento deve ser atualizado junto com `AppRoutes.tsx` ou `registry.tsx`.
