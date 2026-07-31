# Mapa Mestre de Funcionalidades do Maskats

Data: 2026-07-28  
Base de verdade: `api/routes/api.php`, `web/src/routes/AppRoutes.tsx`, `docs/frontend-screen-map.md`, `api/tests`, `web/e2e`, `.claude/memory/project-summary.md`

## Objetivo

Consolidar o inventário funcional real do Maskats por módulo, perfil, telas, APIs e evidência de cobertura, para servir como fonte única de verdade para QA, rollout, homologação e futuras expansões de automação.

## Resumo executivo

- backend com `409` rotas mapeadas
- frontend com `92` rotas/telas mapeadas
- suíte backend atual: `1129 passed`
- suíte E2E web atual: `51 passed`, `1 skipped`
- cobertura atual forte no backend e intermediária no frontend
- fonte de verdade operacional do produto continua sendo multiempresa com gating por plano, permissão e papel de proprietário

## Perfis do sistema

### Públicos

- visitante da loja
- visitante da reserva pública
- convidado de aceite de convite
- usuário em fluxo de confirmação de e-mail
- usuário em recuperação de senha

### Cliente final

- cliente final autenticado no portal

### Operação interna da empresa

- operador administrativo
- atendente/televendas
- operador de PDV
- operador de balcão/comanda
- cozinha/KDS
- financeiro
- logística/rotas
- marketing/CRM
- fiscal interno
- proprietário da empresa

### Perfis externos

- contador
- administrador global

## Módulos e funcionalidades

## 1. Identidade, autenticação e conta

### Funcionalidades

- login interno via JWT
- refresh de sessão
- logout
- cadastro self-service da empresa com criação de proprietário
- aceite de convite de usuário da empresa
- confirmação de troca de e-mail
- recuperação e redefinição de senha
- minha conta
- avatar do usuário
- lockout por tentativas
- política de senha

### Perfis

- público
- usuário interno
- proprietário

### Superfícies

- `/login`, `/cadastro`, `/convite/:token`, `/confirmar-email/:token`, `/esqueci-senha`, `/redefinir-senha/:token`, `/minha-conta`
- `/api/v1/auth/*`

### Evidência automatizada atual

- backend: `AuthTest`, `AcceptTenantUserInviteTest`, `LoginLockoutTest`, `PasswordPolicyTest`, `PasswordResetTest`, `ProfileTest`, `LegalDocumentAndSignupTermsTest`
- frontend E2E: `auth.spec.ts`, `access-and-signup.spec.ts`

## 2. Multiempresa, contexto ativo e acesso

### Funcionalidades

- seleção obrigatória da empresa ativa
- alternância de empresa
- perfil de acesso por empresa
- owner bypass em telas estratégicas
- ocultação de navegação sem permissão
- gating por permissão
- gating por plano
- overrides de funcionalidade por empresa

### Perfis

- usuário interno
- proprietário
- administrador global

### Superfícies

- shell autenticado
- `/api/v1/auth/access-profile`
- `/api/v1/auth/my-tenants`
- rotas com `PermissionRoute`

### Evidência automatizada atual

- backend: família `Permissions/*`, `TenantFeatureOverrideTest`, `PlanGatePermissionsTest`
- frontend E2E: `app-shell.spec.ts`, `navigation.spec.ts`, `access-and-signup.spec.ts`

## 3. Administração global

### Funcionalidades

- CRUD de usuários admin
- CRUD de grupos
- CRUD de funcionalidades
- CRUD de planos
- CRUD de empresas
- CRUD de perfis da empresa
- CRUD de usuários da empresa
- convite de usuário da empresa
- auditoria
- pendências de pagamento

### Perfis

- administrador global

### Superfícies

- `/admin/*`
- `/api/v1/users`, `/groups`, `/functionalities`, `/plans`, `/tenants`, `/tenant-roles`, `/tenant-users`, `/audit-logs`, `/payment-issues`

### Evidência automatizada atual

- backend: `UserPermissionsTest`, `GroupPermissionsTest`, `FunctionalityPermissionsTest`, `PlanPermissionsTest`, `TenantPermissionsTest`, `TenantRole*`, `TenantUserPermissionsTest`, `AuditLogPermissionsTest`
- frontend E2E: `admin.spec.ts`

## 4. Assinatura, planos e cobrança SaaS

### Funcionalidades

- leitura da assinatura atual
- contratação inicial
- seleção de plano
- seleção de período de cobrança
- aceite de termos da contratação
- pedido de token de cartão para plano pago
- troca de plano
- troca de cartão
- cancelamento imediato ou ao fim do ciclo
- renovação de cancelamento agendado
- arrependimento
- histórico de assinaturas
- histórico de faturas
- geração de cobrança Pix de fatura
- histórico de reembolsos
- governança owner-only

### Perfis

- proprietário da empresa
- administrador global

### Superfícies

- `/configuracoes/assinatura`
- `/empresa` redirect
- `/api/v1/subscription*`

### Evidência automatizada atual

- backend: `SubscriptionAccessGateTest`, `SubscriptionEndpointTest`, `SubscriptionStateMachineTest`, `InvoiceGenerationTest`, `MercadoPagoPreapprovalTest`, `MercadoPagoWebhookTest`, `PaymentWebhookTest`, `SubscriptionCycleWebhookTest`, `PaymentIdempotencyTest`
- frontend E2E: `subscription.spec.ts`, trechos de `app-shell.spec.ts`

## 5. Jurídico, LGPD e documentos legais

### Funcionalidades

- documentos públicos de termos e privacidade
- aceite versionado no cadastro
- exportação de dados da empresa
- solicitações de privacidade
- gestão operacional mínima de atendimento LGPD
- notas de release

### Perfis

- público
- proprietário
- operador autorizado
- administrador global

### Superfícies

- `/termos`, `/privacidade`, `/configuracoes/dados-privacidade`
- `/api/v1/legal-documents/*`, `/tenant-profile/export`, `/tenant-profile/privacy-requests`, `/release-notes`

### Evidência automatizada atual

- backend: `LegalDocumentAndSignupTermsTest`, `PrivacyRequestTest`, `TenantDataExportTest`, `ReleaseNoteTest`
- frontend E2E: `settings-privacy.spec.ts`

## 6. Onboarding e treinamento

### Funcionalidades

- checklist de implantação por empresa/usuário
- dismiss persistido do checklist
- checklist consciente de módulos/plano
- central de treinamento

### Perfis

- usuário interno
- proprietário

### Superfícies

- `/treinamentos`
- `/api/v1/onboarding/checklist`

### Evidência automatizada atual

- backend: `OnboardingChecklistTest`
- frontend E2E: `training.spec.ts`

## 7. Cadastros de localização e endereço

### Funcionalidades

- CRUD de estados
- CRUD de cidades
- CRUD de bairros
- CRUD de endereços
- geocodificação
- reverse geocode

### Perfis

- usuário interno com permissão
- proprietário

### Superfícies

- `/estados`, `/cidades`, `/bairros`, `/enderecos`
- `/api/v1/estados`, `/cidades`, `/bairros`, `/enderecos`, `/location/*`

### Evidência automatizada atual

- backend: `EstadoPermissionsTest`, `CidadePermissionsTest`, `BairroPermissionsTest`, `EnderecoPermissionsTest`, `EnderecoServiceGeocodeTest`, `GeocodeEnderecoJobTest`, `ReverseGeocodeTest`
- frontend: sem E2E dedicado ainda

## 8. Clientes e CRM

### Funcionalidades

- CRUD de clientes
- pedido por cliente
- categorias de cliente
- dias ideais
- períodos ideais
- exportação PDF
- fidelização por categorias

### Perfis

- equipe comercial
- financeiro
- contador com acesso

### Superfícies

- `/clientes*`
- `/api/v1/clients`, `/client-categories`, `/dias-ideais`, `/periodos-ideais`

### Evidência automatizada atual

- backend: `ClientPermissionsTest`, `ClientCategoryPermissionsTest`, `DiaIdealPermissionsTest`, `PeriodoIdealPermissionsTest`, `ClientPdfTest`
- frontend: sem E2E dedicado ainda

## 9. Produtos, catálogo e precificação

### Funcionalidades

- CRUD de produtos
- imagem de produto
- categorias de produto
- tipos de produto
- preços por categoria de cliente
- importação CSV
- PDF de produtos
- disponibilidade
- grupos/opções de produto

### Perfis

- operação
- comercial
- contador com acesso fiscal a produto

### Superfícies

- `/produtos*`
- `/api/v1/products`, `/product-categories`, `/product-types`, `/product-category-prices`, `/products/import`

### Evidência automatizada atual

- backend: `ProductPermissionsTest`, `ProductCategoryPermissionsTest`, `ProductTypePermissionsTest`, `ProductCategoryPriceTest`, `ProductImportTest`, `ProductPdfTest`, `ToggleAvailabilityTest`
- frontend: sem E2E dedicado ainda

## 10. Estoque

### Funcionalidades

- locais de estoque
- saldos
- movimentações
- local padrão
- integração com pedidos e PDV

### Perfis

- operação
- estoque

### Superfícies

- `/estoque/*`
- `/api/v1/stock-locations`, `/stock/movements`, `/stock/balances`

### Evidência automatizada atual

- backend: `StockLocationPermissionsTest`, `StockMovementTest`, `DefaultStockLocationTest`
- frontend: sem E2E dedicado ainda

## 11. Pedidos internos

### Funcionalidades

- criação de pedido manual
- edição de itens
- pagamento total/parcial
- entrega
- desfazer entrega
- desfazer pagamento
- cancelamento
- parcelas
- reallocation de parcelas
- código sequencial por empresa
- desconto com limite
- filtros por etapa operacional
- timeline operacional
- informações fiscais por pedido

### Perfis

- operação
- financeiro
- proprietário

### Superfícies

- `/pedidos`, `/pedidos/novo`, `/pedidos-manuais`
- `/api/v1/orders*`

### Evidência automatizada atual

- backend: `OrderTest`, `OrderInstallmentTest`, `OrderDiscountLimitTest`, `OrderOperationStageFilterTest`, `OrderPaymentTest`, `OrderApprovalQueueTest`, `OrderCancellationApprovalTest`, `WorkflowTransitionLogTest`, `OrderFiscalDocumentEndpointTest`, `OrderFiscalPreviewEndpointTest`
- frontend E2E: `orders-manual.spec.ts`

## 12. Pedidos da loja pública

### Funcionalidades

- fila operacional por etapas
- board Kanban operacional
- aprovar
- recusar com motivo
- enviar para expedição
- marcar entregue
- voltar etapas
- concluir financeiro
- gestão de cancelamento solicitado
- detalhe
- timeline
- informação fiscal
- preparo por link/QR

### Perfis

- operação da loja
- financeiro
- cozinha

### Superfícies

- `/pedidos-loja`
- `/preparo/:orderUuid`
- `/api/v1/storefront-orders*`

### Evidência automatizada atual

- backend: `StorefrontOrderManagementTest`, `OrderPrepLinkTest`, `OrderPushNotificationTest`
- frontend E2E: `storefront-orders.spec.ts`

## 13. Loja pública e checkout

### Funcionalidades

- catálogo público
- perfil da loja
- carrinho
- checkout
- taxa de entrega por bairro
- cupons
- promoções
- cashback
- geolocalização/CEP
- regras pickup/wholesale
- catálogo desligável por empresa

### Perfis

- visitante da loja
- cliente final

### Superfícies

- `/loja/:slug`, `/loja/:slug/perfil`, `/loja/:slug/carrinho`, `/loja/:slug/checkout`
- `/api/v1/loja/{slug}*`

### Evidência automatizada atual

- backend: `StorefrontCatalogTest`, `StorefrontCheckoutTest`, `StorefrontCheckoutCouponTest`, `StorefrontCheckoutPromotionTest`, `StorefrontCheckoutGuardsTest`, `StorefrontCheckoutPickupTest`, `StorefrontCheckoutWholesaleTest`, `CouponTest`, `CashbackTest`, `ProductPromotionTest`, `StorefrontLocationCepTest`, `CartEventTest`
- frontend E2E: `storefront-cart.spec.ts`, `storefront-checkout.spec.ts`

## 14. Portal do cliente final

### Funcionalidades

- login por OTP
- lista de pedidos
- rastreio
- favoritos
- endereços
- vouchers
- cashback
- perfil
- cancelamento solicitado
- cobrança Pix do próprio pedido
- avaliação do pedido
- reorder

### Perfis

- cliente final

### Superfícies

- `/portal/*`, `/rastreio/:uuid`
- `/api/v1/portal/*`

### Evidência automatizada atual

- backend: `PortalAuthTest`, `PortalOrdersTest`, `PortalFavoriteTest`, `PortalAddressAndVouchersTest`, `PortalCashback` via serviços do storefront, `PortalOrderCancellationRequestTest`, `PortalOrderPaymentChargeTest`, `PortalOrderRatingTest`, `PortalReorderTest`, `PortalIdentityBoundaryTest`, `PortalLinkTest`
- frontend E2E: `portal.spec.ts`

## 15. PDV

### Funcionalidades

- abertura/fechamento de caixa
- venda PDV
- split de pagamento
- venda sem cliente
- idempotência por venda cliente
- snapshot offline
- bloqueios de operação offline sensível
- PIN de operador
- recibo

### Perfis

- operador de PDV
- supervisor de caixa

### Superfícies

- `/pdv`, `/pdv/fechar`, `/pdv/recibo`
- `/api/v1/pdv*`

### Evidência automatizada atual

- backend: `PdvTest`, `OperatorPinTest`
- frontend E2E: `pdv.spec.ts`

## 16. Balcão, comandas, mesas, reservas e fila de espera

### Funcionalidades

- mesas
- comandas
- KDS
- reserva interna
- fila de espera
- reserva pública
- snapshot offline
- conflito offline multi-dispositivo
- fechamento guiado
- recibo

### Perfis

- atendente
- salão
- cozinha
- proprietário

### Superfícies

- `/balcao/mesas`, `/balcao/comandas/:uuid`, `/balcao/kds`, `/balcao/recibo`, `/reservas/:slug`
- `/api/v1/balcao*`, `/api/v1/reservas/{slug}`

### Evidência automatizada atual

- backend: `BalcaoTest`
- frontend E2E: `balcao.spec.ts` (mesas/comandas/KDS + conflito offline multi-dispositivo)

## 17. Rotas e entregas

### Funcionalidades

- candidatos de rota
- planejamento de rotas
- coordenação com filas de expedição e cobrança

### Perfis

- logística
- financeiro de cobrança externa

### Superfícies

- `/rotas`
- `/api/v1/routes/candidates`

### Evidência automatizada atual

- backend: `RouteCandidateTest`
- frontend E2E: `routes.spec.ts`

## 18. Analytics, relatórios e financeiro

### Funcionalidades

- dashboard
- analytics
- relatório por canal
- relatório de pedidos
- relatório de clientes
- recebíveis
- conciliação
- interações de cobrança
- KPIs de operação

### Perfis

- proprietário
- financeiro
- gestão
- contador com acesso

### Superfícies

- `/`, `/analises`, `/relatorios/*`, `/financeiro/conciliacao`
- `/api/v1/reports*`, `/api/v1/reconciliation*`

### Evidência automatizada atual

- backend: `AnalyticsTest`, `ReportTest`, `ReportByChannelTest`, `ReportCmvTest`, `ReconciliationTest`
- frontend E2E: `analytics.spec.ts`

## 19. Fiscal

### Funcionalidades

- regras tributárias
- perfis fiscais de operação
- readiness fiscal
- documento fiscal por pedido
- preview fiscal
- campos fiscais opcionais/obrigatórios por contexto

### Perfis

- fiscal interno
- contador
- proprietário

### Superfícies

- `/configuracoes/regras-tributarias*`, `/configuracoes/perfis-fiscais*`
- `/api/v1/tax-rules*`, `/api/v1/fiscal-operation-profiles*`, `/api/v1/fiscal-readiness*`, `/api/v1/orders/{uuid}/fiscal*`

### Evidência automatizada atual

- backend: `TaxRuleEndpointTest`, `FiscalOperationProfileEndpointTest`, `FiscalReadinessTest`, `FiscalDocumentTest`, `OrderFiscalDocumentEndpointTest`, `OrderFiscalPreviewEndpointTest`, `FiscalFieldsNullableTest`, `TaxRuleMatcherTest`
- frontend E2E: `fiscal.spec.ts`

## 20. Contabilidade externa

### Funcionalidades

- cadastro do contador
- TOTP
- login do contador
- solicitação de acesso
- aprovação/revogação
- relatórios da empresa
- mensagens
- produtos fiscais
- clientes fiscais
- regras tributárias

### Perfis

- contador
- proprietário da empresa

### Superfícies

- `/contador/*`, `/configuracoes/contadores`
- `/api/v1/accounting*`

### Evidência automatizada atual

- backend: `AccountingAuthTest`, `AccountingAccessFlowTest`, `AccountingMessageTest`, `AccountingReportTest`
- frontend E2E: `accounting.spec.ts`

## 21. Integrações, API keys, marketplace e webhooks

### Funcionalidades

- API keys
- webhooks de saída
- entregas de webhook
- integrações marketplace
- pedidos iFood
- catálogo marketplace
- reprocessamento
- SLA marketplace
- webhook público marketplace

### Perfis

- proprietário
- integrador
- operação marketplace

### Superfícies

- `/configuracoes/integracoes`, `/pedidos-ifood`
- `/api/v1/api-keys*`, `/webhook-subscriptions*`, `/marketplace*`

### Evidência automatizada atual

- backend: `ApiKeyTest`, `WebhookSubscriptionTest`, `WebhookDispatchTest`, `MarketplaceIntegrationTest`
- frontend: `marketplace-orders.spec.ts`, além da cobertura relacionada em `storefront-orders.spec.ts`

## 22. Suporte e observabilidade

### Funcionalidades

- chamados de suporte
- healthcheck público
- CSP
- CORS
- comandos de reconciliação e saúde

### Perfis

- suporte
- administrador global
- monitoramento externo

### Superfícies

- `/suporte`
- `/api/v1/health`
- comandos Artisan de reconciliação e sync

### Evidência automatizada atual

- backend: `SupportTicketTest`, `HealthCheckTest`, `CorsTest`, `CspHeaderTest`, `GeocodeEnderecosBackfillTest`, `MercadoPagoHealthCheckCommandTest`, `ReconcileMercadoPago*CommandTest`, `SyncTenantPermissionsTest`
- frontend: `postdeploy-smoke.spec.ts`

## Rastreabilidade atual de testes por camada

### Backend automatizado forte

- autenticação e segurança
- permissões
- multiempresa
- assinatura
- pedidos
- loja pública
- portal
- PDV
- balcão
- fiscal
- relatórios
- marketplace
- webhooks

### Frontend E2E já coberto

- autenticação
- shell/autorização
- cadastro self-service
- troca de empresa ativa
- resiliência de sessão em falha transitória de perfil de acesso
- responsividade mobile básica
- clientes
- estoque
- pedidos manuais
- criação frontend de pedido manual
- PDV
- PDV offline controlado
- Balcão
- pedidos da loja
- drag and drop operacional da loja
- cancelamento solicitado com decisão operacional
- administração global
- assinatura inicial
- assinatura cancelada/suspensa
- checkout público da loja
- portal do cliente final com login OTP e lista de pedidos
- smoke pós-deploy

### Lacunas E2E prioritárias restantes

- checkout da loja pública
- PDV
- balcão
- portal do cliente final
- integrações marketplace no frontend
- relatórios/recebíveis
- configurações críticas além de assinatura

## Critérios de completude deste mapa

Este documento considera “funcionalidade do sistema” tudo que tenha ao menos uma destas evidências:

- rota pública ou autenticada exposta no backend
- tela/rota navegável no frontend
- service/front contract ativo
- suíte automatizada ou documentação arquitetural viva vinculada ao fluxo

Ele deve ser atualizado sempre que houver:

- nova rota
- nova tela
- novo perfil
- nova permissão
- novo módulo opcional por plano
- novo cenário operacional importante
