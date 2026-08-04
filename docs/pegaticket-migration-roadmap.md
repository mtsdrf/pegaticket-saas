# Roadmap de migração Maskats → PegaTicket

Status: documento **histórico de migração**, não mais a descrição principal do estado atual do produto. Última contextualização ampla em **3 de agosto de 2026**.
Base: inventário real do código em `api/app/Http/Controllers`, `web/src/pages`, `database/seeders/FunctionalitiesSeeder.php`/`InitialPlansSeeder.php`, `routes/api.php`, `.claude/memory/architecture-decisions.md`, cruzado com `especificacao-plataforma-ingressos.md` (raiz).

> Para o estado atual do produto, usar primeiro:
>
> - [docs/product-current-map.md](/home/mtsdrf/workspace/pegaticket-saas/docs/product-current-map.md)
> - [docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md](/home/mtsdrf/workspace/pegaticket-saas/docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md)
>
> Este arquivo continua útil como trilha de decisões da migração, mas vários trechos abaixo não representam mais o estado corrente do código.

---

## 0. Confirmação de modelo de negócio (2026-07-30, reconfirmado 2026-07-31)

PegaTicket **mantém a estrutura de SaaS multi-tenant do Maskats**: cada tenant é um clube/organizador, com plano próprio, permissões próprias, módulos habilitados por contratação, operação isolada. Não é uma reescrita da camada de acesso — é a mesma fundação, só trocando o domínio de negócio (mercadoria/entrega → eventos/ingressos). `Plan`/`Functionality`/`plan_functionalities`/gate de plano e `Subscription` permanecem como estão na arquitetura.

**Decisão nova de 2026-07-31**: reaproveitar a arquitetura do Maskats é aceitável, mas **manter nomes de entidade do domínio antigo não é** — `Product`, `Client`, `ProductCategory` etc. no código de uma plataforma de ingressos é a "gambiarra" que o usuário explicitamente pediu pra eliminar. Toda entidade reaproveitada precisa ser renomeada pro vocabulário do domínio de ingressos antes de a migração ser considerada concluída (ver seção 4A).

## 1. O que NÃO muda (fundação comum, fora do escopo da spec — nomes genéricos de infraestrutura, não de domínio)

- Multi-tenancy (`tenant`/`tenant_id()`), auth JWT, `Group`/`GroupPermission`/`Functionality`+`Action`, `perm:` middleware.
- `BaseModel` (UUID + soft delete + auditoria automática de `created_by`/`updated_by`/`deleted_by`).
- Padrão Request→Controller→Service→Repository→Resource, DTOs, Events+Listeners de auditoria (`AuditLog`).
- `Legal/LegalDocumentController` + `ReleaseNoteController` (LGPD, termos versionados).
- `Location/*` (Estado/Cidade/Bairro/Endereço) — genérico, útil pra endereço de clube/local de evento.
- `Support/SupportTicketController` — **atenção**: "Ticket" aqui é chamado de suporte (help desk), não ingresso — risco real de colisão de nome com o domínio novo, ver seção 4A.
- `Workflow/WorkflowTransitionLogController` (log de transição de status genérico).
- `Onboarding/OnboardingController` (adaptar conteúdo do checklist, manter mecanismo).
- `Plan`/`Functionality`/gate de plano — mecanismo mantido, conteúdo dos planos redesenhado pra ingressos.

## 2. Mapeamento módulo a módulo (referência histórica da decisão original — ver seção 3 pro status real de execução)

Legenda: **MANTER** · **ADAPTAR** · **REMOVER** · **NOVO**.

### 2.1 Pagamento e integrações financeiras
| Módulo | Ação | Observação |
|---|---|---|
| `Payment/MercadoPagoPaymentProvider` + contrato de provider | ADAPTAR | Trocar/adicionar `PagBankPaymentProvider` no mesmo contrato. Manter `ManualPaymentProvider`. |
| `payment_idempotency_keys` + `IdempotencyRepository` | MANTER | Resolve requisitos críticos #7/#8/#24 da spec. |
| `Webhook/*` (dispatch de pagamento) | MANTER | Seção 5.11 da spec quase pronta. |
| `Payment/PaymentIssueController` + `PaymentReconciliationService` | MANTER | |
| `Subscription/SubscriptionController` + `SubscriptionStateMachine` | MANTER | Clube→PegaTicket = assinatura (Mercado Pago, mantido); comprador→clube = venda de ingresso (PagBank, novo). Duas correntes financeiras distintas. |
| `Finance/ReconciliationController` | ADAPTAR | "Venda de mercadoria" → "venda de ingresso". |
| `ApiKeyController`/`WebhookSubscriptionController` (3º) | REMOVER | Fora do MVP. |

### 2.2 Cupons e promoções
| Módulo | Ação |
|---|---|
| `Storefront/Coupon` + `CouponRedemption` | MANTER — cobre seção 5.17 quase 1:1. |
| `Storefront/ProductPromotionController` | ADAPTAR — promoção por produto → por tipo de ingresso/lote. |
| `Storefront/ReactivationRuleController` | REMOVER — CRM de varejo. |

### 2.3 Carrinho, reserva e checkout
| Módulo | Ação |
|---|---|
| `Storefront/CartEventController` | ADAPTAR — hoje só tracking; spec pede `inventory_holds` real, é **NOVO**. |
| `Balcao/TableReservationService`/`TableWaitlistService` | ADAPTAR (extrair padrão) — esqueleto do `InventoryHoldService`. Resto do Balcão some. |
| `Storefront/StorefrontCheckoutService/DTO/Controller` | ADAPTAR — reaproveitar forma, reescrever conteúdo pra seção 5.10. |
| `StoreAddress`/`StoreBusinessHours`/`StoreDeliveryFee` | REMOVER — entrega física, sem equivalente. |
| `StorefrontTableReservationController` | REMOVER/RENOMEAR — reserva de mesa de restaurante, não confundir com hold de assento novo. |
| `StorefrontManifestController` (PWA) | MANTER. |

### 2.4 Catálogo (Produto → Evento/Ingresso)
| Módulo | Ação |
|---|---|
| `Product/ProductController` + `ProductImageController` | ADAPTAR — vira base de `Event`/`TicketType`. |
| `Product/ProductCategoryController` | ADAPTAR — vira `EventCategory`. |
| `ProductCategoryPrice` (preço por categoria B2B) | REMOVER. |
| `ProductTypeController` | REMOVER. |
| `ProductImportController` | REMOVER. |
| `Stock/*` | REMOVER — estoque físico não se aplica a ingresso. |

### 2.5 Módulos 100% fora de escopo
`Accounting/*`, `Fiscal/*` ("primeira versão não emitirá nota fiscal"), `Marketplace/*` (iFood), `Route/RouteCandidateController`, `Pdv/*`, `Balcao/{Comanda,Station,Table}Controller`+KDS, `Client/*`+`ClientIdeal/*`, `ReceivableInteractionController`, `PortalCashbackController`, `PortalAddressController`, `SocialMedia/*`, `Order/SaleFiscalDocumentController`+`SaleFiscalPreviewController`+`OrderPrepViewController`.

### 2.6 Portal do comprador final (`FinalCustomer`)
`FinalCustomer`/`FinalCustomerOtp`/`FinalCustomerTenantLink` MANTÉM — é o "comprador" da spec. `PortalAuthController`/`PortalLinkController`/`PushSubscriptionController` MANTÉM. `PortalCouponController`/`PortalFavoriteController` ADAPTA. `PortalOrdersPage`/`OrderTrackingPage` ADAPTA → "Meus ingressos".

### 2.7 Relatórios
`AnalyticsController`/`AnalyticsPage` MANTÉM o motor, ADAPTA os agregados (ocupação, funil, check-in). `ReportController` MANTÉM.

### 2.8 O que é 100% NOVO
Editor visual de mapa (setores/mesas/assentos/camarotes), motor de lotes com virada automática, `InventoryHoldService` real, emissão de ingresso com QR Code + `TicketIssuanceService` + `CheckinService`, PWA de portaria, `ExternalRefundService`, estacionamento como item com estoque independente.

---

## 3. Status real de execução por fase (reauditado 2026-07-31)

| Fase | Status | Observação |
|---|---|---|
| **Fase 1** — SocialMedia | ✅ Concluída | |
| **Fase 2** — Fiscal/Accounting/Marketplace/Route/ApiKey | ⚠️ Parcial, com regressão | Accounting/Marketplace/Route/ApiKey removidos. **Fiscal foi recriado em 2026-07-31** (migrations novas `2026_07_31_*`) por trabalho de outra sessão/momento que não conhecia a decisão de remoção — precisa remover de novo. |
| **Fase 3** — PDV/Balcão | ⚠️ Parcial | Controllers/rotas já saíram. `app/Models/Balcao`, `app/Services/Balcao`, `app/Models/Pdv`, `app/Services/Pdv` ficaram **órfãos no disco** (sem controller/rota apontando pra eles) — dead code a limpar, não decisão nova. |
| **Fase 4** — CRM B2B / estoque físico | ⚠️ Parcial | `ClientController`/categorias/dias-ideais removidos. **`Client` model continua vivo de propósito** — usado por `Order`/`Product`/`StorefrontCheckoutService`/`Onboarding` como comprador; não é lixo, é candidato a virar a entidade de comprador do domínio novo (ver seção 4A). `Stock/*` controllers saíram, `app/Models/Stock`+`app/Services/Stock` ficaram órfãos. |
| **Fase 5** — Storefront (poda de loja física) | ✅ Concluída | `StoreAddress`/`BusinessHours`/`DeliveryFee`/`ReactivationRule`/`StorefrontTableReservation`/`PortalAddress`/`PortalCashback` removidos. |
| **Fase 6** — Seeders/rotas/planos | ⚠️ Parcial | `FunctionalitiesSeeder`/`InitialPlansSeeder` ainda citam slugs órfãos (`routes`, `api-access`, `accounting-access`, `tax-rules`, `stock`, `stock_locations` sem controller behind). **Achado não previsto**: o plano de 3 tiers (Prata/Ouro/Diamante) foi simplificado pra um único plano `pegaticket` com tudo liberado — decisão razoável pra fase pré-lançamento, mas não estava neste roadmap; precisa ser formalizada aqui ou revertida conscientemente. |
| **Fase 7** — Rebrand visual | ⚠️ Só cosmético | `CLAUDE.md` e skills (`pegaticket-visual-identity.md` etc.) passaram por find-and-replace de nome, mas o **conteúdo** ainda descreve o SaaS de comércio genérico — zero menção a evento/ingresso/assento. Não reflete a spec real. `brand-guidelines.md`/`design-system.md` ainda não revisados. |
| **Fase 7B (NOVA)** — Renomeação de entidades pro domínio | ❌ Não iniciada | Ver seção 4A — venda explícito do usuário em 2026-07-31, adicionado como fase formal. |
| **Seção 2.8** — Construção do domínio novo | ❌ Não iniciada | Nenhum código de Event/Session/Venue/Seat/TicketType/Hold/Ticket/Checkin existe ainda. |

### Trabalho recente fora do roadmap (não é regressão, mas não avança a migração)
~6h de features de catálogo/comércio em 2026-07-31 (observação por item no venda, promoção percentual, selos de card `new`/`best_selling`/`low_stock`, grupo de opção "ingrediente removível") — domínio 100% comércio/delivery de comida. Tecnicamente inofensivo pra base (`Product`/`Storefront` continuam sendo a base de `TicketType`/loja no plano), mas não é trabalho de migração. Mantido por ora; será re-avaliado quando `Product` virar `TicketType` na Fase 7B/construção.

---

## 4. Ordem de execução recomendada a partir de agora

1. **Fiscal — remover de novo** (mesmo escopo da Fase 2 original).
2. **Limpar dead code órfão**: `app/Models/Balcao`, `app/Services/Balcao`, `app/Models/Pdv`, `app/Services/Pdv`, `app/Models/Stock`, `app/Services/Stock` (+ repositories/migrations associadas, sem controller/rota).
3. **Arrumar seeders**: remover slugs órfãos de `FunctionalitiesSeeder`/`InitialPlansSeeder` (`routes`, `api-access`, `accounting-access`, `tax-rules`, `stock`, `stock_locations`); formalizar (ou reverter) a decisão de plano único.
4. **Fase 7B — Renomeação de entidades** (ver seção 4A) — feita **junto com** o início da construção do domínio novo (seção 2.8), não antes: renomear `Product`→algo só faz sentido depois de decidir o desenho final de `TicketType`/`Event`/add-ons.
5. **Fase 7 de verdade**: reescrever `brand-guidelines.md`/`design-system.md` pro domínio de ingressos.
6. **Construção do domínio novo** (seção 2.8) — projeto próprio, precisa de planejamento dedicado (schema, sprints da seção 18 da spec).

## 4A. Renomeação de entidades para o domínio PegaTicket (venda do usuário, 2026-07-31)

**Princípio**: nenhuma entidade de domínio reaproveitada do Maskats deve manter nome do domínio antigo. Reaproveitar arquitetura/padrão é aceitável; reaproveitar nome não é. Isso vale para Models, tabelas, DTOs, Requests, Resources, Services, Repositories, rotas, e os equivalentes no frontend (types, services, nomes de página/componente).

**Isso não é um find-and-replace mecânico** — cada renomeação abaixo é uma decisão de modelagem de domínio, não só de rótulo, porque a cardinalidade/semântica pode mudar (ex.: `Product` hoje cobre item de comércio genérico; no domínio de ingresso pode precisar virar mais de uma entidade — `TicketType` vs. `EventProduct`/add-on). Por isso a Fase 7B roda junto com a construção do domínio novo (item 4 acima), não isolada.

### Candidatos a renomear (mapeamento inicial, a confirmar por entidade antes de executar)

| Nome atual (Maskats) | Candidato PegaTicket | Observação / decisão pendente |
|---|---|---|
| `Product` | `TicketType` (ingresso) **ou** split em `TicketType` + `EventProduct` (adicional/estacionamento) | Depende de decidir se add-ons e ingressos continuam na mesma tabela ou viram entidades separadas — decisão de modelagem, não só nome. |
| `ProductCategory` | `EventCategory` | Já estava planejado assim na seção 2.4. |
| `ProductImage` | `TicketTypeImage`/`EventImage` | Acompanha a decisão de `Product`. |
| `Client` (model ainda vivo, usado como comprador em `Order`) | Absorver em `FinalCustomer` **ou** renomear pra `Buyer`/`Comprador` se precisar continuar como entidade separada | Hoje há redundância conceitual entre `Client` (comprador interno de `Order`) e `FinalCustomer` (comprador da loja pública) — precisa decisão de qual é a fonte única de verdade do comprador de ingresso antes de renomear. |
| `Order` | Provavelmente mantém — "Venda" é termo legítimo também no domínio de ingressos (spec usa "Venda" explicitamente, seção 5.12). | Confirmar se mantém ou se especializa em algo como `TicketOrder`. |
| `SaleItem` | Mantém ou vira polimórfico (`TicketSaleItem` cobrindo ingresso/assento/estacionamento/adicional) | Depende do desenho do checkout novo (seção 5.10 da spec). |
| `Storefront*` (Controller/Service/pages) | Provavelmente mantém — "loja pública" é conceito válido também pra loja de eventos do clube. | A confirmar se o usuário quer renomear mesmo assim por consistência de marca. |
| `SupportTicketController`/`SupportTicket` model | **Risco de colisão de nome** com o novo domínio de "Ticket" (ingresso) | Precisa de nome inequívoco antes de o domínio de ingresso existir — sugestão: `SupportRequest`/`HelpTicket` para o suporte, deixando "Ticket" livre pro domínio de ingresso. Marcar como prioridade alta na Fase 7B por ser fonte de ambiguidade real no código (`Ticket` já usado, mas pra outra coisa). |
| `Balcao`/`TableReservation`/`TableWaitlist` (o que sobreviver como esqueleto do hold) | `SeatHold`/`InventoryHold` (spec seção 5.7/5.9) | Já estava planejado como ADAPTAR — a renomeação acontece nesse momento, não antes. |
| `CartEvent` | Absorvido pelo novo `Cart`/`InventoryHold` (seção 5.9 da spec: `carts`, `cart_items`, `inventory_holds`) | |

### Como executar (quando chegar a vez, item 4 da seção 4 acima)
1. Por entidade: confirmar o nome final com o usuário antes de tocar código (não assumir 1:1).
2. Renomear em conjunto: Model, tabela (migration nova, não `ALTER TABLE RENAME` direto sem avaliar dado existente — banco está zerado então é seguro), DTOs, Requests, Resources, Services, Repositories (interface+Eloquent), rotas em `routes/api.php`, Events/Listeners de auditoria, testes.
3. Espelhar no frontend: types, services, nomes de página/componente, rotas React, labels em português na UI (isso já deveria estar em português nas telas, então o rename de UI-facing label pode já estar ok — confirmar).
4. Rodar `composer test`/`npm run build` a cada entidade renomeada, não só no final — renomeação em lote sem checkpoint intermediário é onde bug se esconde.

---

## 5. Riscos e decisões pendentes

- ~~Banco de produção com dado real?~~ Confirmado 2026-07-30: base zerada, sem risco de dado real.
- ~~Plano único vs. multi-tier~~ **Decidido 2026-07-31: mantém plano único `pegaticket`** por ora (fase pré-lançamento, sem tenants reais pra diferenciar tier). Registrado, não reverter sem novo venda explícito.
- ~~`Client` vs. `FinalCustomer`~~ **Decidido 2026-07-31: `FinalCustomer` absorve tudo.** `Client` é descontinuado; `Order` e tudo que hoje referencia `Client` passa a referenciar `FinalCustomer` diretamente.
- ~~`Product`→`TicketType`~~ **Decidido 2026-07-31: split em `TicketType` (ingresso, com setor/mesa/assento/lote) + `EventProduct` (adicional/estacionamento, sem lugar marcado).**
- ~~Colisão de nome "Ticket" com suporte~~ **Decidido 2026-07-31: `SupportTicketController`/`SupportTicket` → `HelpRequestController`/`HelpRequest`.** "Ticket" fica livre pro domínio de ingresso.
- Trabalho recente de comércio (promoção %, badges, notes por item) — mantido por ora, reavaliado durante o split `Product`→`TicketType`/`EventProduct`.

## 7. Mapeamento geral — estado real em 2026-08-01 (pausa a venda do usuário)

Auditado direto no disco (sem git). Ordem de leitura: o que está sólido → o que está quebrado agora → o que falta.

### ✅ Sólido (testado, ambos os lados, build/test verdes na última verificação)
- Limpeza completa: Fiscal, Accounting, Marketplace, Route, ApiKey/Webhook 3º, SocialMedia, Balcão/PDV/Stock órfão — tudo fora.
- Renomeações concluídas: `SupportTicket→HelpRequest`, `Client→FinalCustomer` (+ endpoint de busca pro staff), `Product/ProductCategory→TicketType/EventCategory`, `EventProduct` novo.
- Domínio de catálogo (`Event`, `EventCategory`, `TicketType`, `EventProduct`) com CRUD completo nos dois lados, storefront público simplificado listando eventos.
- `EventSession`, `TicketBatch` (lote com validação de limite), `Venue`/`VenueMapVersion`/`Seat` (dados — **sem editor visual drag-and-drop**, isso é tarefa de frontend própria, não iniciada).
- `InventoryHold`/`InventoryHoldItem` (reserva) + `StorefrontHoldController`/`StorefrontHoldService` — criados, ainda não verificados nesta auditoria (suite de teste não roda pra confirmar).

### 🔴 Quebrado agora (não é "pendente", é regressão ativa)
- **Frontend**: `npm run build` falha com 6 erros de TypeScript, todos no mesmo eixo do rename `Order→Sale`:
  - `StorefrontSaleActionDialogProps` não tem mais `saleUuid` (prop renomeada, uso não atualizado em `StorefrontSaleManagementPage.tsx:692`).
  - `../../services/orderTrackingService` e `../../types/order` não existem mais (`StorefrontCheckoutPage.tsx`, `reportDetailService.ts`) — foram renomeados/removidos sem atualizar quem importava.
  - `portalSaleService.createOrderPixCharge` não existe, virou `createSalePixCharge` (`StorefrontCheckoutPage.tsx:123`).
  - Ainda não consertado nesta rodada (2026-08-02) — escopo desta rodada foi só backend.

### ✅ Consertado em 2026-08-02 (backend)
- **Regressão do rename `Order→Sale`**: causa raiz eram três classes com namespace/nome de classe divergente do caminho do arquivo (PSR-4 quebrado) — `CreatesSaleFixtures` (ainda declarada como `Tests\Feature\Orders\Concerns\CreatesOrderFixtures`), 10 arquivos de teste em `tests/Feature/Sales/*Test.php` ainda com `namespace Tests\Feature\Orders`, e `tests/Unit/Services/Sale/SaleInstallmentDueDateCalculatorTest.php` cujo conteúdo real era a classe `ParcelaVencimentoCalculatorTest` (arquivo renomeado sem renomear a classe). Também achado durante a correção: `Sale::items()/installments()/rating()` e todo `belongsTo(Sale::class)` (SaleItem, SaleInstallment, SaleRating, SalePrepLink, CouponRedemption, CashbackEarning, CashbackRedemption) dependiam da FK default por convenção Eloquent (`sale_id`), que nunca existiu — a coluna real sempre foi `sale_id` (tabela `sales`/`sale_items` não foi renomeada, só o Model). Toda relation precisou de FK explícita. Rota pública `/rastreio/{sale:uuid}` também não batia com o parâmetro `Sale $order` do controller (implicit binding por nome), causando resposta 200 com todos os campos `null`. `php artisan test`: **570 passed** (era 0, suite não rodava).
- **Domínio `Stock` removido por completo** (armazém físico sem equivalente em venda de ingresso — controle de quantidade agora é só `TicketType.quantity_available`/`TicketBatch.quantity_sold`): `Models/Stock`, `Services/Stock`, `Repositories/{Contracts,Eloquent}/Stock*`, `DTOs/Stock`, `Events/Stock`, `Listeners/Stock` + `Listeners/Tenant/CreateDefaultStockLocation`, exceptions `InsufficientStockException`/`InvalidStockMovementException`. `SaleService` inteiro decoplado de `StockService` (reserva/saída/estorno removidos de create/updateItems/deliver/undeliver/cancel/reject — métodos `convertReservationsToExit`/`revertReservationsFromExit`/`findReserveMovement`/`resolveDefaultStockLocation` excluídos). Colunas `sales.stock_location_id`/`sales.stock_reserved`/`tenant_settings.block_order_without_stock` dropadas (migration `2026_08_02_100000`). Relatório CMV (`ReportService::cmv`, `/reports/cmv`) e analytics `stalled-products`/`stock-ruptures` removidos por completo — dependiam 100% de `stock_movements`/`stock_balances`. Functionality `stock` removida do `FunctionalitiesSeeder`/`InitialPlansSeeder`.
- **`StoreBusinessHour` removido por completo** (horário de loja física sem equivalente em evento, que tem `starts_at`/`ends_at` próprio): Model/Service/Repository/Request/DTO/Resource/Event/Listener + exceptions `InvalidStoreBusinessHoursException`/`StoreClosedException`. Guard "loja fechada" removido de `StorefrontCheckoutService::checkout()` (Guard 1, `isOpenNow()`). `StorefrontTenantResource` perdeu o campo `business_hours`. `OnboardingService` perdeu o step `storefront_configured` (não tinha mais dado pra calcular). Tabela `store_business_hours` dropada na mesma migration acima.
- **`Location` (Estado/Cidade/Bairro/Endereço)**: já estava 100% removido do código (`app/Models/Location` etc. eram diretórios vazios, deletados agora); achado nesta rodada: `DatabaseSeeder` ainda chamava `EstadosSeeder::class`, um seeder que não existe mais no disco — quebrava `php artisan db:seed`/testes que chamam `->seed()`. Removido da lista.
- Achados fora do escopo original mas bloqueantes, corrigidos: `database/seeders/{DemoTenantSeeder,DemoPlansPresentationSeeder,StoreCatalogDemoSeeder}.php` eram seeders órfãos (não chamados por `DatabaseSeeder` nem por nenhum teste) já quebrados desde o split `Product→TicketType/EventProduct` (referenciavam `App\Models\Product\Product`, inexistente) — deletados.

### ❌ Não iniciado
- `Ticket` (emissão + QR Code) + `CheckinService`/PWA de portaria.
- Reescrita do checkout do comprador final pro fluxo de ingresso (seleção de assento no mapa, participantes, confirmação com hold ativo).
- Editor visual de mapa (drag-and-drop de mesas/assentos) — só o backend de dados existe.
- Integração PagBank real (hoje só existe `MercadoPagoPaymentProvider`, que é a assinatura clube→PegaTicket, não o pagamento comprador→clube).
- Estorno externo formal (`ExternalRefundService`).
- Fase 7 "de verdade" (brand-guidelines.md/design-system.md ainda descrevem produto genérico, não ingressos).

## 8. Próximos passos recomendados (em ordem) — histórico, ver seção 9 pro status final

1. ~~Consertar a regressão do rename `Order→Sale` (backend)~~ **Feito 2026-08-02.**
2. ~~Consertar o build do frontend~~ **Feito.**
3. ~~Decidir sobre `Order→Sale`~~ Mantido — sem reversão pedida pelo usuário.
4. ~~Limpeza de `Stock`/`StoreBusinessHour` no frontend~~ **Feito.**
5. ~~`Ticket`+QR+Checkin → checkout de comprador → PagBank~~ **Feito, ver seção 9.**

## 9. Fechamento da rodada de reestruturação (2026-08-01/02)

### ✅ Concluído e verificado
- Domínio completo de bilheteria: `Event`/`EventCategory`/`EventSession`/`TicketType`/`TicketBatch`/`EventProduct`/`Venue`/`Seat`, com CRUD nos dois lados.
- `InventoryHold` com duração configurável por tenant (`hold_duration_minutes`), sem cron — expiração é só ausência de contagem em disponibilidade.
- `Ticket` (emissão por evento de domínio `SalePaid`/`SaleCancelled`, idempotente) + QR (`qr_token` via `Str::random(40)`, `code` de 8 chars sem ambiguidade) + `TicketCheckin` (manual, sem leitor de câmera).
- Checkout de comprador final reescrito: hold ativo, contagem regressiva com escalonamento 5min/1min, tratamento de hold expirado com nova reserva, captura de participantes (nome/documento opcional por item).
- Frontend: portaria (`CheckinPage`), "meus ingressos" com QR no rastreio de venda, `admin.ts` corrigido (resíduo `sales`/`api-access` inexistente no backend).
- `location_lat`/`location_lng` em `Event` (endereço é texto único + coordenadas pro mapa, conforme decidido).
- **PagBank**: contrato `PaymentProviderInterface` já existia; adicionado binding contextual em `AppServiceProvider` isolando a corrente comprador→clube (`SALE_PAYMENT_PROVIDER`, default herda de `PAYMENT_PROVIDER`) da corrente clube→PegaTicket (assinatura, Mercado Pago, intocada). `PagBankPaymentProvider` implementado com DTOs de request/response e pontos de configuração (`config/services.php: pagbank.*`) — **a chamada HTTP real ao PagBank é stub** (`// TODO PAGBANK REAL:`), retorna `pending` sincronicamente, sem endpoint/payload inventado. Troca de provider é só config, não requer código.
- **Verificação final (2026-08-02, tudo verde)**: `php artisan test` → 590 passed / 1991 assertions. `npm run build` → sem erros. `npm run lint` (oxlint) → sem erros.

### ❌ Não feito (não fingir concluído)
- **Editor visual de mapa** (drag-and-drop de mesas/assentos): só o modelo de dados (`Venue`/`VenueMapVersion`/`Seat`) existe, sem UI de edição.
- **QR por câmera** no check-in: só busca manual/token colado — nenhuma lib de leitura de câmera foi instalada (decisão consciente, fora de escopo sem alinhamento).
- **PagBank real**: sem credenciais fornecidas, integração HTTP é stub (ver acima). Precisa de token sandbox/prod do PagBank pra sair do estágio placeholder.
- **Estorno externo formal** (`ExternalRefundService`, spec 5.14): não iniciado.
- **Fase 7 (rebrand de conteúdo)**: `brand-guidelines.md`/`design-system.md` ainda descrevem o produto genérico antigo, não bilheteria — nunca executado de fato.
- Simplificação conhecida (não é bug, é limitação assumida pelo agente de frontend): hold é criado só ao entrar no checkout, não no momento da seleção de quantidade na página do evento.

## 6. Execução autônoma (2026-07-31, a partir daqui)

Usuário autorizou execução completa e contínua ("inicie toda reestruturação e só pare quando tudo estiver finalizado"). Ordem de execução real, registrada conforme progride — ver commits/relatórios de agente pra detalhe por etapa:

1. Limpeza (Fiscal de novo, dead code órfão de Balcão/Pdv/Stock, seeders).
2. Renomeação de entidades (FinalCustomer absorve Client, SupportTicket→HelpRequest, Product split TicketType+EventProduct, ProductCategory→EventCategory).
3. Construção do domínio novo (Event/Session/Venue/Seat/Batch/Hold/Ticket/Checkin) — escopo desta rodada: fundação backend (models/migrations/services/controllers básicos) + CRUD administrativo mínimo no frontend. Funcionalidades de UX avançada (editor visual de mapa drag-and-drop, PWA de portaria, checkout completo do comprador, integração PagBank real) **não cabem numa única rodada contínua** — serão sinalizadas como pendência explícita ao final, não fabricadas como "concluídas".

### 3B. Frontend (`web/`) alinhado ao catálogo Event/EventCategory/TicketType/EventProduct (2026-07-31)

Escopo completado nesta rodada — backend já tinha migrado `Product`/`ProductCategory` pra `Event`/`EventCategory`/`TicketType`/`EventProduct`, frontend estava defasado (ainda apontava pra `/products`/`/product-categories`).

- **Renomeado**: `types/productCategory.ts`→`eventCategory.ts`, `services/productCategoryService.ts`→`eventCategoryService.ts`, `pages/ProductCategory/*`→`pages/EventCategory/*`; `types/product.ts`→`ticketType.ts`, `services/productService.ts`→`ticketTypeService.ts`, `pages/Product/*`→`pages/TicketType/*` (rotas `/tipos-de-ingresso`).
- **Novo**: `types/event.ts`+`services/eventService.ts`+`pages/Event/*` (rotas `/eventos`), `types/eventProduct.ts`+`services/eventProductService.ts`+`pages/EventProduct/*` (rotas `/adicionais`).
- **Removido** (sem equivalente no backend novo): `ProductType` (type/service), `ProductOptionGroup`/`ProductOption` (`ProductOptionsConfiguratorDialog`), import CSV de produto (`productImportService`/`ProductImportDialog`), `ProductPromotion` (frontend só — sem tela própria, rota de backend ficou órfã, não tocada).
- **Venda/Order**: `SaleItem`/`OrderCreateItemPayload`/`OrderUpdateItemDraft` migrados de `product_uuid` único pra `ticket_type_uuid`/`event_product_uuid` (exatamente um por item, mesma regra do backend). `OrderFormPage`/`SaleDetailDialog` perderam a UI de opcionais (sem equivalente); autocomplete de item agora busca `TicketType`+`EventProduct` combinados.
- **Loja pública**: catálogo (`StorefrontCatalogPage`) migrado de listagem de produto pra listagem de `Event` (card com imagem/data/local, sem ordenação por preço/mais vendidos/promoção — conceitos que não existem mais no domínio). Nova `StorefrontEventDetailPage` (`/loja/:slug/eventos/:eventSlug`) lista `ticket_types`/`event_products` do evento com controle de quantidade. Carrinho (`StorefrontCartContext`) e checkout migrados pro shape `ticket_type_uuid`/`event_product_uuid`, sem opcionais/promoção/atacado (removidos do backend). Favoritos (`PortalFavoritesPage`) migrados de produto pra evento.
- **Pendência sinalizada, não implementada nesta rodada**: tela de detalhe de evento é funcional mas simples (sem galeria de fotos, sem mapa de assento/mesa — aguarda o domínio Venue/Seat da spec); reorder do Portal (`PortalResaleItem`) usa campo `ticket_type_uuid` também para `EventProduct` porque o backend (`PortalCustomerService::getSaleItemsForReorder`) não distingue os dois nesse endpoint especificamente.
- Verificado: `npm run build` (tsc -b + vite build) e `npm run lint` (oxlint) limpos.
