# Checklist de execução — remoção do legado restante

Data de consolidação: 2026-07-31

Objetivo: transformar o mapeamento do legado restante em uma ordem de execução cirúrgica, por arquivos, para concluir a faxina antes de iniciar a fase nova do domínio de ingressos.

Status macro atual:

- Branding/rebrand principal: avançado
- Docs e testes principais: avançados
- Remoção funcional do legado: incompleta
- Construção do domínio novo (`InventoryHoldService`, lotes, emissão, check-in): ainda não iniciada

---

## Fase A — Remover `cashback` e `reactivation`

Risco: médio
Motivo: módulos legados com superfície ampla, mas mais isolados do que `stock`/`pdv`.

### Backend

- Remover/reescrever referências em [api/routes/api.php](/home/mtsdrf/workspace/pegaticket-saas/api/routes/api.php)
  - `/reports/analytics/cashback-liability`
- Remover schedules em [api/routes/console.php](/home/mtsdrf/workspace/pegaticket-saas/api/routes/console.php)
  - `cashback:process`
  - `reactivation:process`
- Remover services:
  - [api/app/Services/Storefront/CashbackService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Storefront/CashbackService.php)
  - [api/app/Services/Storefront/ReactivationRuleService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Storefront/ReactivationRuleService.php)
  - [api/app/Services/Storefront/ReactivationDispatchService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Storefront/ReactivationDispatchService.php)
- Remover models:
  - [api/app/Models/Storefront/CashbackEarning.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Storefront/CashbackEarning.php)
  - [api/app/Models/Storefront/CashbackRedemption.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Storefront/CashbackRedemption.php)
  - [api/app/Models/Storefront/ReactivationRule.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Storefront/ReactivationRule.php)
  - [api/app/Models/Storefront/ReactivationDispatch.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Storefront/ReactivationDispatch.php)
- Remover resources:
  - [api/app/Http/Resources/Storefront/ReactivationRuleResource.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Resources/Storefront/ReactivationRuleResource.php)
- Remover events/listeners:
  - [api/app/Events/Storefront/CashbackCredited.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Events/Storefront/CashbackCredited.php)
  - [api/app/Events/Storefront/ReactivationRuleUpdated.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Events/Storefront/ReactivationRuleUpdated.php)
  - [api/app/Events/Storefront/ReactivationDispatched.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Events/Storefront/ReactivationDispatched.php)
  - [api/app/Listeners/Storefront/SendPushOnCashbackCredited.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Storefront/SendPushOnCashbackCredited.php)
  - [api/app/Listeners/Storefront/SendPushOnReactivationDispatched.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Storefront/SendPushOnReactivationDispatched.php)
  - [api/app/Listeners/Storefront/AuditCashbackCredited.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Storefront/AuditCashbackCredited.php)
  - [api/app/Listeners/Storefront/AuditReactivationRuleUpdated.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Storefront/AuditReactivationRuleUpdated.php)
  - [api/app/Listeners/Storefront/AuditReactivationDispatched.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Storefront/AuditReactivationDispatched.php)
  - [api/app/Listeners/Order/CreditCashbackOnOrderPaid.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Order/CreditCashbackOnOrderPaid.php)
  - [api/app/Listeners/Order/ReverseCashbackOnOrderUnpaid.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Order/ReverseCashbackOnOrderUnpaid.php)
- Remover repositórios:
  - [api/app/Repositories/Contracts/ReactivationRuleRepositoryInterface.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Repositories/Contracts/ReactivationRuleRepositoryInterface.php)
  - [api/app/Repositories/Eloquent/ReactivationRuleRepository.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Repositories/Eloquent/ReactivationRuleRepository.php)
- Remover comando/schedule:
  - [api/app/Console/Commands/ProcessCashbackLifecycleCommand.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Console/Commands/ProcessCashbackLifecycleCommand.php)
  - [api/app/Console/Commands/ProcessReactivationRulesCommand.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Console/Commands/ProcessReactivationRulesCommand.php)
- Remover campos/config de tenant settings após decidir a migração:
  - [api/app/Models/Tenant/TenantSettings.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Tenant/TenantSettings.php)
  - [api/app/Services/Tenant/TenantSettingsService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Tenant/TenantSettingsService.php)
  - [api/app/DTOs/TenantSettings/UpdateTenantSettingsDTO.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/DTOs/TenantSettings/UpdateTenantSettingsDTO.php)
  - [api/app/Http/Requests/TenantSettings/UpdateTenantSettingsRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/TenantSettings/UpdateTenantSettingsRequest.php)
  - [api/app/Http/Resources/TenantSettings/TenantSettingsResource.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Resources/TenantSettings/TenantSettingsResource.php)
- Remover analytics residual:
  - [api/app/Services/Report/AnalyticsService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Report/AnalyticsService.php)
  - [api/app/Http/Controllers/Report/AnalyticsController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/Report/AnalyticsController.php)

### Frontend

- Remover services:
  - [web/src/services/cashbackService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/cashbackService.ts)
  - [web/src/services/reactivationRuleService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/reactivationRuleService.ts)
- Remover tipos:
  - [web/src/types/cashback.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/cashback.ts)
  - [web/src/types/reactivationRule.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/reactivationRule.ts)
- Limpar checkout/config:
  - [web/src/pages/Storefront/StorefrontCheckoutPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Storefront/StorefrontCheckoutPage.tsx)
  - [web/src/pages/Settings/blocks/PaymentBlock.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Settings/blocks/PaymentBlock.tsx)
  - [web/src/pages/Settings/blocks/OperationsBlock.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Settings/blocks/OperationsBlock.tsx)
  - [web/src/types/tenantSettings.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/tenantSettings.ts)
  - [web/src/types/storefront.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/storefront.ts)
  - [web/src/types/admin.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/admin.ts)

### Testes e seeders

- Remover/reescrever:
  - [api/tests/Feature/Storefront/CashbackTest.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Feature/Storefront/CashbackTest.php)
  - trechos de [api/tests/Feature/Reports/AnalyticsTest.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Feature/Reports/AnalyticsTest.php) ligados a cashback
  - [api/tests/Feature/Storefront/Concerns/CreatesStorefrontFixtures.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Feature/Storefront/Concerns/CreatesStorefrontFixtures.php)
- Limpar seeders demo:
  - [api/database/seeders/DemoPlansPresentationSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/DemoPlansPresentationSeeder.php)
  - [api/database/seeders/StoreCatalogDemoSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/StoreCatalogDemoSeeder.php)
  - [api/database/seeders/FunctionalitiesSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/FunctionalitiesSeeder.php)

---

## Fase B — Remover `client_categories`, `product_types`, `dias_ideais`, `periodos_ideais`

Risco: médio
Motivo: bastante espalhado, mas menos crítico do que `stock`.

### Backend

- Services:
  - [api/app/Services/Client/ClientCategoryService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Client/ClientCategoryService.php)
  - [api/app/Services/Client/DiaIdealService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Client/DiaIdealService.php)
  - [api/app/Services/Client/PeriodoIdealService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Client/PeriodoIdealService.php)
  - [api/app/Services/Product/ProductTypeService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Product/ProductTypeService.php)
  - [api/app/Services/Product/ProductCategoryPriceService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Product/ProductCategoryPriceService.php)
- Models:
  - [api/app/Models/Client/ClientCategory.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Client/ClientCategory.php)
  - [api/app/Models/Client/DiaIdeal.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Client/DiaIdeal.php)
  - [api/app/Models/Client/PeriodoIdeal.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Client/PeriodoIdeal.php)
  - [api/app/Models/Product/ProductType.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Product/ProductType.php)
  - [api/app/Models/Product/ProductCategoryPrice.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Product/ProductCategoryPrice.php)
- Controllers:
  - [api/app/Http/Controllers/Product/ProductTypeController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/Product/ProductTypeController.php)
  - [api/app/Http/Controllers/Product/ProductCategoryPriceController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/Product/ProductCategoryPriceController.php)
- Requests/resources/events/listeners/DTOs de `Client/*` e `ProductType*`/`ProductCategoryPrice*`
- Ajustar [api/app/Services/Product/ProductService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Product/ProductService.php) para não depender de `product_type_id` nem preço por categoria
- Ajustar validações em:
  - [api/app/Http/Requests/Product/StoreProductRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/Product/StoreProductRequest.php)
  - [api/app/Http/Requests/Product/UpdateProductRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/Product/UpdateProductRequest.php)
  - [api/app/Http/Requests/Product/SyncProductCategoryPricesRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/Product/SyncProductCategoryPricesRequest.php)
  - [api/app/Http/Requests/Client/SyncClientCategoriesRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/Client/SyncClientCategoriesRequest.php)

### Frontend

- Services:
  - [web/src/services/productTypeService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/productTypeService.ts)
  - [web/src/services/clientService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/clientService.ts) se ainda carregar categorias/preferências antigas
- Tipos:
  - [web/src/types/productType.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/productType.ts)
  - partes de [web/src/types/admin.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/admin.ts)
  - partes de [web/src/types/client.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/client.ts)
- Páginas:
  - [web/src/pages/Product/ProductFormPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Product/ProductFormPage.tsx)

### Testes e seeders

- [api/tests/Unit/Repositories/ProductTypeRepositoryTest.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Unit/Repositories/ProductTypeRepositoryTest.php)
- [api/tests/Unit/Repositories/ClientCategoryRepositoryTest.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Unit/Repositories/ClientCategoryRepositoryTest.php)
- [api/tests/Feature/Product/ProductCategoryPriceTest.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Feature/Product/ProductCategoryPriceTest.php)
- [api/database/seeders/FunctionalitiesSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/FunctionalitiesSeeder.php)
- [api/database/seeders/InitialPlansSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/InitialPlansSeeder.php)

---

## Fase C — Extrair padrão útil de `Balcao` e remover o restante

Risco: alto
Motivo: aqui mora o padrão-base para o futuro `InventoryHoldService`.

### Manter como referência antes da remoção

- [api/app/Services/Balcao/TableReservationService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableReservationService.php)
- [api/app/Services/Balcao/TableWaitlistService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableWaitlistService.php)
- [api/app/Services/Balcao/TableAvailabilityService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableAvailabilityService.php)
- [api/app/Models/Balcao/TableReservation.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Balcao/TableReservation.php)
- [api/app/Models/Balcao/TableWaitlist.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Balcao/TableWaitlist.php)

### Remover depois da extração

- Services:
  - [api/app/Services/Balcao/ComandaService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/ComandaService.php)
  - [api/app/Services/Balcao/ComandaItemService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/ComandaItemService.php)
  - [api/app/Services/Balcao/StationService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/StationService.php)
  - [api/app/Services/Balcao/TableService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/TableService.php)
  - [api/app/Services/Balcao/BalcaoOfflineSnapshotService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/BalcaoOfflineSnapshotService.php)
- Models/resources/requests/events/listeners/DTOs correspondentes de `Comanda`, `Station`, `Table`
- Funcionalidade `balcao` em seeders/plans

---

## Fase D — Remover `pdv`

Risco: alto
Motivo: ainda aparece como origem operacional e compartilha comportamento com pedidos.

### Backend

- Services:
  - [api/app/Services/Pdv/CashSessionService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Pdv/CashSessionService.php)
  - [api/app/Services/Pdv/PdvSaleService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Pdv/PdvSaleService.php)
  - [api/app/Services/Pdv/PdvOfflineSnapshotService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Pdv/PdvOfflineSnapshotService.php)
  - [api/app/Services/Pdv/UserPinService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Pdv/UserPinService.php)
- Models/resources/requests/events/listeners/DTOs/exceptions de `Pdv/*`
- Revisar referências a `origin='pdv'` em:
  - [api/app/Services/Report/ReportService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Report/ReportService.php)
  - [api/app/Services/Order/OrderPaymentService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Order/OrderPaymentService.php)
  - [api/app/Services/Order/OrderService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Order/OrderService.php)

### Frontend

- Limpar vestígios em:
  - [web/src/types/admin.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/admin.ts)
  - [web/src/types/order.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/order.ts)
  - [web/src/types/report.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/report.ts)
  - [web/src/pages/Order/OrderListPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/OrderListPage.tsx)
  - [web/src/pages/Report/ChannelReportPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Report/ChannelReportPage.tsx)

---

## Fase E — Reescrever o acoplamento `stock`

Risco: muito alto
Motivo: é o bloco que realmente segura a saída do domínio antigo.

### Núcleo

- [api/app/Services/Order/OrderService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Order/OrderService.php)
- [api/app/Services/Storefront/StorefrontCheckoutService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Storefront/StorefrontCheckoutService.php)
- [api/app/Models/Product/Product.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Product/Product.php)
- [api/app/Http/Controllers/Product/ProductController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/Product/ProductController.php)
- [api/app/Http/Resources/Product/ProductResource.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Resources/Product/ProductResource.php)
- [api/app/Http/Requests/Order/StoreOrderRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/Order/StoreOrderRequest.php)
- [api/app/Http/Requests/Order/UpdateOrderItemsRequest.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Requests/Order/UpdateOrderItemsRequest.php)
- [api/app/DTOs/Order/CreateOrderDTO.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/DTOs/Order/CreateOrderDTO.php)
- [api/app/DTOs/Order/UpdateOrderItemsDTO.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/DTOs/Order/UpdateOrderItemsDTO.php)

### Depois do desacoplamento

- Remover árvore inteira:
  - `api/app/{Services,Models,Http/Requests,Http/Resources,Events,Listeners,DTOs}/Stock/*`
- Remover listener:
  - [api/app/Listeners/Tenant/CreateDefaultStockLocation.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Tenant/CreateDefaultStockLocation.php)
- Revisar commands de migração legada:
  - [api/app/Console/Commands/MigrateLegacyEstablishmentCommand.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Console/Commands/MigrateLegacyEstablishmentCommand.php)
  - [api/app/Console/Commands/Migration/ImportLegacyJsQueijosCommand.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Console/Commands/Migration/ImportLegacyJsQueijosCommand.php)
  - [api/app/Console/Commands/Migration/MigrateJsQueijosEDocesCommand.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Console/Commands/Migration/MigrateJsQueijosEDocesCommand.php)

### Frontend

- Services:
  - [web/src/services/stockService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/stockService.ts)
  - [web/src/services/stockLocationService.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/services/stockLocationService.ts)
- Tipos:
  - [web/src/types/stock.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/stock.ts)
  - [web/src/types/stockLocation.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/stockLocation.ts)
  - partes de [web/src/types/product.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/product.ts)
  - partes de [web/src/types/order.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/order.ts)
- Páginas/componentes:
  - [web/src/pages/Order/OrderFormPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/OrderFormPage.tsx)
  - [web/src/components/order/OrderDetailDialog.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/components/order/OrderDetailDialog.tsx)
  - [web/src/pages/Product/ProductFormPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Product/ProductFormPage.tsx)
  - [web/src/components/storefront/ProductCardBadges.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/components/storefront/ProductCardBadges.tsx)

---

## Fase F — Redesenhar catálogo/plano para ingressos

Risco: médio
Motivo: não é remoção técnica pura; é alinhamento final antes da fase nova.

- Recriar [api/database/seeders/FunctionalitiesSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/FunctionalitiesSeeder.php) para o domínio de ingressos
- Recriar [api/database/seeders/InitialPlansSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/InitialPlansSeeder.php)
- Ajustar [api/database/seeders/ActionsSeeder.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/seeders/ActionsSeeder.php) ao conjunto final de ações realmente suportadas
- Limpar [web/src/types/admin.ts](/home/mtsdrf/workspace/pegaticket-saas/web/src/types/admin.ts) para o catálogo final

---

## Fase G — Iniciar o domínio novo

Só começa depois de A–F estarem consistentes e com build/testes verdes.

- `PagBankPaymentProvider`
- motor de lotes
- `InventoryHoldService`
- editor visual de mapa / seat map
- `TicketIssuanceService`
- `CheckinService`
- QR code / portaria / PWA
- estorno externo formal
- estacionamento como item

---

## Critério real de “faxina concluída”

Todos os itens abaixo precisam ser verdadeiros ao mesmo tempo:

- não existir mais `cashback`, `reactivation`, `pdv` e `balcao` como módulos ativos
- `stock` não ser mais infraestrutura obrigatória do pedido
- `FunctionalitiesSeeder` e `InitialPlansSeeder` refletirem o produto de ingressos
- `web` não depender mais de `stockService`, `cashbackService`, `reactivationRuleService`, `productTypeService`
- build e testes passarem sem testes/rotas legadas órfãs

Enquanto qualquer um desses pontos continuar verdadeiro, o projeto ainda estará em faxina e não na fase nova de produto.
