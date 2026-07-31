# Roadmap de migração para o contexto PegaTicket

Status: mapeamento e decisões de negócio confirmados em 2026-07-30 — nenhuma remoção foi executada ainda no código.
Base: inventário real do código em `api/app/Http/Controllers`, `web/src/pages`, `database/seeders/FunctionalitiesSeeder.php`, `routes/api.php`, cruzado com `especificacao-plataforma-ingressos.md` (raiz) e `.claude/memory/product-roadmap.md`.

---

## 0. Confirmação de modelo de negócio (2026-07-30)

PegaTicket **mantém a estrutura de SaaS multi-tenant herdada da fase anterior do produto**: cada tenant é um clube/organizador, com plano próprio, permissões próprias, módulos habilitados por contratação, operação isolada. Não é uma reescrita da camada de acesso — é a mesma fundação, só trocando o domínio de negócio (mercadoria/entrega → eventos/ingressos). Isso confirma que `Plan`/`Functionality`/`plan_functionalities`/gate de plano (seção 1) e `Subscription` (seção 2.1) permanecem como estão na arquitetura, só o **conteúdo** dos planos (o que cada tier libera) muda pra refletir features de ingresso em vez de PDV/cashback/delivery.

## 1. O que NÃO muda (fundação comum, fora do escopo da spec)

Confirmado como reaproveitável 1:1, sem remoção nem reescrita:

- Multi-tenancy (`tenant`/`tenant_id()`), auth JWT, `Group`/`GroupPermission`/`Functionality`+`Action`, `perm:` middleware.
- `BaseModel` (UUID + soft delete + auditoria automática de `created_by`/`updated_by`/`deleted_by`).
- Padrão Request→Controller→Service→Repository→Resource, DTOs, Events+Listeners de auditoria (`AuditLog`).
- `Legal/LegalDocumentController` + `ReleaseNoteController` (LGPD, termos versionados).
- `Location/*` (Estado/Cidade/Bairro/Endereço) — genérico, útil pra endereço de clube/local de evento.
- `Support/SupportTicketController`.
- `Workflow/WorkflowTransitionLogController` (log de transição de status genérico).
- `Onboarding/OnboardingController` (adaptar conteúdo do checklist, manter mecanismo).
- `Plan`/`Functionality`/gate de plano (Prata/Ouro/Diamante) — mecanismo mantido, **conteúdo dos planos precisa ser redesenhado do zero** pra ingressos (não faz sentido portar "cashback"/"PDV" como diferenciador de plano de clube).

## 2. Mapeamento módulo a módulo

Legenda: **MANTER** (usar como está) · **ADAPTAR** (aproveitar arquitetura/padrão, reescrever conteúdo de domínio) · **REMOVER** (não existe equivalente na spec de ingressos) · **NOVO** (não existe hoje, construir do zero).

### 2.1 Pagamento e integrações financeiras

| Módulo | Ação | Observação |
|---|---|---|
| `Payment/MercadoPagoPaymentProvider` + contrato de provider | **ADAPTAR** | Trocar/adicionar `PagBankPaymentProvider` no mesmo contrato. Manter `ManualPaymentProvider` (venda interna). |
| `payment_idempotency_keys` + `IdempotencyRepository` + `ReconcilePaymentIdempotencyCommand` | **MANTER** | Resolve direto os requisitos críticos #7/#8/#24 da spec. |
| `Webhook/*` (`WebhookDispatchService`, `webhook_events`, `webhook_deliveries`) | **MANTER** | É a seção 5.11 da spec quase pronta (validação, histórico, reprocessamento). |
| `Payment/PaymentIssueController` + `PaymentReconciliationService` | **MANTER** | Conciliação/pendências — reaproveita direto. |
| `Subscription/SubscriptionController` + `SubscriptionStateMachine` | **MANTER** | Decidido 2026-07-30: PegaTicket segue SaaS multi-tenant — cada clube assina um plano da plataforma. Duas correntes financeiras distintas e independentes: (1) clube→PegaTicket = assinatura via `Subscription`/Mercado Pago (mecanismo atual, mantido); (2) comprador→clube = venda de ingresso via PagBank do próprio clube (`PagBankPaymentProvider`, novo). Não confundir as duas no desenho do checkout. |
| `Finance/ReconciliationController` | **ADAPTAR** | Trocar granularidade de "pedido de mercadoria" pra "pedido de ingresso". |
| `ApiKeyController` / `Webhook/WebhookSubscriptionController` (API pública p/ 3º) | **REMOVER** | Decidido 2026-07-30: fora do MVP, remove agora. Se algum clube precisar integrar sistema próprio no futuro, reconstrói já pensado pro domínio de eventos/ingressos. |

### 2.2 Cupons e promoções

| Módulo | Ação | Observação |
|---|---|---|
| `Storefront/Coupon` + `CouponRedemption` + exceptions | **MANTER** | Cobre a seção 5.17 quase 1:1 (código, %/valor fixo, limite total/por CPF, validade, restrição por meio de pagamento). |
| `Storefront/ProductPromotionController` | **ADAPTAR** | Promoção por produto → promoção por tipo de ingresso/lote. |
| `Storefront/ReactivationRuleController` | **REMOVER** | Reativação de cliente inativo é CRM de varejo, fora da spec. |

### 2.3 Carrinho, reserva e checkout

| Módulo | Ação | Observação |
|---|---|---|
| `Storefront/CartEventController` (`cart_events`) | **ADAPTAR** | Hoje é só tracking de abandono. Spec pede reserva de estoque real com expiração (`inventory_holds`) — é um domínio **NOVO**, `CartEvent` só serve de referência de shape. |
| `Balcao/TableReservationService` + `TableWaitlistService` | **ADAPTAR (extrair padrão)** | É o precedente mais próximo de "hold" com estado/expiração/cancelamento — usar como esqueleto do `InventoryHoldService`/reserva de assento (requisitos críticos #3/#4). O restante do módulo Balcão (comanda/KDS) some. |
| `Storefront/StorefrontCheckoutService/DTO/Controller` | **ADAPTAR** | Reaproveitar a forma (DTO, guards, testes por cenário), reescrever conteúdo pras etapas da seção 5.10 (mesa/assento/estacionamento/participantes). |
| `Storefront/StoreAddressController`, `StoreBusinessHoursController`, `StoreDeliveryFeeController` | **REMOVER** | Entrega por bairro/horário de funcionamento de loja física — não existe em evento. |
| `Storefront/StorefrontTableReservationController` | **REMOVER/RENOMEAR** | É reserva de mesa de restaurante (Balcão exposta no storefront), não confundir com o hold de assento novo — melhor remover e nomear o novo domínio sem herdar esse nome. |
| `Storefront/StorefrontManifestController` (PWA) | **MANTER** | PWA da loja pública é útil pra loja de ingressos também. |

### 2.4 Catálogo (Produto → Evento/Ingresso)

| Módulo | Ação | Observação |
|---|---|---|
| `Product/ProductController` + `ProductImageController` | **ADAPTAR** | Vira base de `Event`/`TicketType`. Padrão de imagem (capa, galeria) reaproveita. |
| `Product/ProductCategoryController` | **ADAPTAR** | Vira `EventCategory` (seção 5.2 da spec é quase o CRUD de categoria atual). |
| `Product/ProductCategoryPriceController` (preço por categoria de cliente) | **REMOVER** | Precificação B2B por categoria de cliente/atacado não existe em ingresso. |
| `Product/ProductTypeController` | **REMOVER** | Tipo de produto de varejo, sem equivalente. |
| `Product/ProductImportController` (import CSV em lote) | **REMOVER** | Import de catálogo de produto físico — reavaliar se fizer sentido versão futura pra eventos em lote. |
| `Stock/StockLocationController` + `StockMovementController` | **REMOVER** | Estoque físico por local. Estoque de ingresso é contagem simples (lote/tipo), não movimentação de armazém. |

### 2.5 Módulos 100% fora de escopo — remoção completa

| Módulo | Motivo |
|---|---|
| `Accounting/*` (portal do contador inteiro) | Não existe na spec de ingressos. |
| `Fiscal/*` (`FiscalOperationProfileController`, `TaxRuleController`, `FiscalReadinessController`) | Spec explícita: "primeira versão não emitirá nota fiscal" (módulo já estava pausado antes da virada de contexto). |
| `Marketplace/*` (iFood) | Delivery de comida, sem equivalente. |
| `Route/RouteCandidateController` (roteirização Leaflet/OSRM) | Logística de entrega, sem equivalente. |
| `Pdv/*` (CashSession, OperatorPin, PdvSale, offline snapshot) | Caixa de PDV físico de varejo. "Venda interna"/"cortesia" da spec (5.18) é um domínio bem mais simples, não precisa reaproveitar o PDV inteiro. |
| `Balcao/ComandaController`, `StationController`, `TableController`, KDS (`BalcaoKdsPage`) | Comanda/cozinha de restaurante — só o `TableReservation`/`TableWaitlist` (2.3 acima) tem valor de padrão. |
| `Client/*` (`ClientController`, `ClientCategoryController`), `ClientIdeal/*` (`DiaIdealController`/`PeriodoIdealController`) | CRM B2B de rota de entrega — comprador de ingresso já é `FinalCustomer`. |
| `Report/ReceivableInteractionController` (cobrança B2B) | Recebíveis/promessa de pagamento de venda a prazo B2B. |
| `Portal/PortalCashbackController`, `Settings/blocks/CashbackBlock` | Decidido 2026-07-30: remover. Não está em nenhuma fase da spec (MVP, Fase 2 ou Fase 3) — cashback é recompra de produto físico, não compra pontual de ingresso. |
| `Portal/PortalAddressController` | Endereço de entrega — sem equivalente (ingresso não é entregue fisicamente por padrão). |
| `SocialMedia/*` (frontend) | Já era funcionalidade fantasma antes da migração de domínio — zero controller/rota real, é só tela morta. Remoção é limpeza pura, sem perda funcional. |
| `Order/OrderFiscalDocumentController`, `OrderFiscalPreviewController`, `OrderPrepViewController` | Nota fiscal (ver Fiscal acima) e preparo de cozinha (KDS). |

### 2.6 Portal do comprador final (`FinalCustomer`)

| Módulo | Ação |
|---|---|
| `FinalCustomer`/`FinalCustomerOtp`/`FinalCustomerTenantLink` | **MANTER** — é exatamente o "comprador" da spec, com login OTP e vínculo por tenant. |
| `Portal/PortalAuthController`, `PortalLinkController`, `PushSubscriptionController` | **MANTER** |
| `Portal/PortalCouponController`, `PortalFavoriteController` | **ADAPTAR** — favorito de produto → favorito de evento/clube. |
| `Portal/PortalOrdersPage`, `Tracking/OrderTrackingPage` | **ADAPTAR** — vira "Meus pedidos"/"Meus ingressos" (seção 9.2 da spec). |

### 2.7 Relatórios

| Módulo | Ação |
|---|---|
| `Report/AnalyticsController` + `AnalyticsPage` (motor de agregação) | **MANTER motor, ADAPTAR agregados** — trocar métricas de venda/produto por ocupação, funil, check-in, ticket médio de evento. |
| `Report/ReportController` (dashboard/exportação) | **MANTER** |

### 2.8 O que é 100% NOVO (sem equivalente hoje)

- Editor visual de mapa (setores/mesas/assentos/camarotes, drag-and-drop, versionamento de mapa).
- Motor de lotes com virada automática (data/quantidade/esgotamento).
- `InventoryHoldService` real (reserva de assento com expiração no servidor, lock por transação).
- Emissão de ingresso com QR Code + `TicketIssuanceService` + `CheckinService`.
- App/PWA de portaria (leitor de QR Code, busca manual, resultado de validação).
- Estorno externo (`ExternalRefundService`) como fluxo formal (hoje existe reembolso do Mercado Pago, mas não "registrar estorno feito fora do sistema").
- Estacionamento como item de pedido com estoque independente.

---

## 3. Roadmap de remoção (ordem sugerida)

A ordem importa porque `plan_functionalities`/seeders/rotas referenciam as functionalities dos módulos removidos — remover controller sem limpar o resto deixa lixo (permissão fantasma, rota morta, seed quebrado).

### Fase 0 — Preparação (sem apagar nada ainda)
1. ~~Confirmar decisões de negócio~~ **Concluído 2026-07-30** — todas as decisões da seção 2 e 5 resolvidas (ver histórico acima).
2. Criar branch de trabalho só pra remoção (`git checkout -b chore/remove-legacy-domain`), sem misturar com feature nova. **Próximo passo — ainda não executado.**

### Fase 1 — Frontend morto/isolado (menor risco, zero dependência de backend)
1. `web/src/pages/SocialMedia/*` (feature fantasma, zero controller real).
2. `web/src/pages/Training/TrainingCenterPage.tsx` — decidido 2026-07-30: **manter**, adaptar conteúdo depois (tratado como centro de ajuda/onboarding genérico, não específico de varejo/PDV — confirmar ao abrir o arquivo antes da Fase 7).

### Fase 2 — Domínios de retaguarda física (Fiscal, Accounting, Marketplace, Route)
Sem dependência de checkout/pagamento, mais fácil de isolar.
1. Remover controllers/services/models de `Fiscal/*`, `Accounting/*`, `Marketplace/*`, `Route/RouteCandidateController`.
2. Remover páginas correspondentes em `web/src/pages/{Fiscal,Accounting,Marketplace,Route}`.
3. Remover migrations correspondentes **só se o banco de dev puder ser recriado do zero** — em produção/staging existente, avaliar rollback controlado em vez de dropar tabela direto.
4. Remover functionalities `tax-rules`, `accounting-access`, `routes` do seeder e dos planos.
5. Remover rotas correspondentes em `routes/api.php`.

### Fase 3 — PDV/Balcão (extrair o que serve antes de apagar)
1. Extrair/documentar o padrão de `TableReservationService`/`TableWaitlistService` (referência pro `InventoryHoldService` novo) **antes** de remover o módulo.
2. Remover `Pdv/*`, `Balcao/ComandaController`, `StationController`, `TableController`, KDS.
3. Remover páginas `Pdv/*`, `Balcao/BalcaoKds*`, `Balcao/BalcaoComanda*`, `Balcao/CancelItemDialog`, `CloseComandaModal`.
4. Remover functionalities `pdv`, `balcao`.

### Fase 4 — CRM B2B e estoque físico
1. Remover `Client/*`, `ClientIdeal/*` (Dia/Período ideal), `ProductCategoryPrice` (atacado), `ProductType`, `Stock/*`.
2. Remover páginas correspondentes.
3. Remover functionalities `clients`, `client_categories`, `dias_ideais`, `periodos_ideais`, `stock`, `stock_locations`, `product_types`.

### Fase 5 — Storefront: podar o que é de loja física
1. Remover `StoreAddressController`, `StoreBusinessHoursController`, `StoreDeliveryFeeController`, `ReactivationRuleController`, `StorefrontTableReservationController`, `PortalAddressController`, `PortalCashbackController`.
2. Manter `Coupon`, `CartEvent` (como base), `ProductPromotion`, `StorefrontManifest`.
3. Ajustar `Settings/blocks/{CashbackBlock,ScheduleAddressBlock}` conforme decisão.

### Fase 6 — Faxina de rotas, seeders e planos
1. Recriar `FunctionalitiesSeeder`/`InitialPlansSeeder` do zero pro domínio de ingressos (não editar incrementalmente — o conjunto de planos/gates muda de natureza).
2. Rodar `grep` por referências órfãs (imports mortos, rotas apontando pra controller removido, permission slugs sem controller).
3. `composer test` e `npm run build` como critério de "faxina completa" antes de começar a construir o domínio novo.

### Fase 7 — Consolidação do rebrand para o contexto PegaTicket
Só depois da faxina de domínio (fase 1–6), pra não misturar "apagar módulo" com "trocar nome" no mesmo diff:
1. `CLAUDE.md`, `README.md`, `web/public/logo.png`, `brand-guidelines.md`, `design-system.md`.
2. Consolidar as skills visuais no namespace `pegaticket-*`, removendo aliases e nomes herdados do contexto anterior.
3. Paleta/tema (`--pt-*` tokens) — decidido 2026-07-30: consolidar `--pt-*` em CSS/tema, sem tokens de branding anterior sobrevivendo no código.

## 4. O que fica pra depois da faxina (não é remoção, é construção)

Ver seção 2.8 — só começa depois que a Fase 6 estiver com `test`/`build` verdes, pra não construir em cima de uma base ainda com lixo do domínio antigo.

## 5. Riscos a validar antes de começar a apagar

- ~~Banco de produção real com tenants ativos vindos da fase anterior do produto?~~ **Confirmado 2026-07-30: base zerada, sem nenhuma tabela populada.** Sem risco de dado real — remoção de tabela/migration pode ser feita direto (`dropIfExists`, deletar/recriar migrations), sem necessidade de plano de backup/rollback.
- Módulos com dependência cruzada não óbvia (ex.: `Order` referenciado por `Report/AnalyticsController`, por `Workflow`, por `Pdv` — remover Pdv sem checar `Order` pode quebrar teste que assume presença dos dois).
- Seeders de permissão são idempotentes (delete+reinsert por plano) — rodar depois de cada fase, não só no final, pra pegar erro cedo.
