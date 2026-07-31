---
name: product-roadmap
description: Estado real do produto Maskats por frente (não plano futuro) — reescrito 2026-07-24 a partir de auditoria verificada em código, substituindo o roadmap de 2026-07-12 que ficou gravemente desatualizado.
metadata:
  type: project
---

# Maskats — Estado real do produto (reescrito 2026-07-24)

O documento anterior (levantado 2026-07-12) descrevia um plano de "CRM+analytics para distribuidoras" e ficou obsoleto rápido: 141 dos 163 commits do repositório aconteceram DEPOIS dele. O produto real hoje é uma **plataforma multi-produto**: CRM/pedidos B2B + e-commerce B2C (loja pública) + PDV/restaurante (Balcão) + gateway de pagamento/assinatura real + fundação fiscal (não ativada) + plataforma de integração (API pública + marketplace iFood). Este arquivo descreve o que EXISTE, verificado em código (migrations/controllers/rotas/telas), não um plano — decisão registrada em [[architecture-decisions]] de sempre verificar contra o código antes de confiar num relato anterior (inclusive este).

## Frentes concluídas e ativas em produção

- **Planos e monetização** — `plans`/`plan_functionalities`/`tenant_feature_overrides`, gate no middleware `perm:`, `PLAN_UPGRADE_REQUIRED`. **Pendência real aberta 2026-07-24**: auditoria em andamento pra confirmar se as features novas (delivery/cashback/cupons/PDV/marketplace) estão de fato mapeadas nos planos pagos ou vazando de graça — ver seção "Auditoria de gate de plano" abaixo quando concluída.
- **Self-service signup e onboarding** — cadastro público, aceite LGPD versionado (`LegalAcceptance`/`LegalDocument`), trial, checklist pós-cadastro (`OnboardingController`), upgrade/downgrade guiado de plano.
- **Analytics** (`AnalyticsController`, 17+ endpoints) — vendas, ticket médio, localização, sazonalidade, ranking RFM de clientes, atraso de pagamento/entrega, curva ABC, margem, passivo de cashback, ROI de cupom, concentração de receita, OTIF de entrega, churn, produtos parados/em ruptura, vendas por hora. Absorve métricas de todas as frentes abaixo assim que elas nasceram.
- **Recebíveis/financeiro** — aging, projeção de recebimento, interações/promessas de cobrança, conciliação financeira, exportação PDF (CSV só em 2 telas via ag-Grid, não universal).
- **Precificação por categoria de cliente + atacado** — `product_category_prices` (tabela por categoria) + `wholesale_pricing` (produto). Override manual por item do pedido sempre tem prioridade.
- **Logística/rotas** — geocodificação via Nominatim/OSM (`GeocodeEnderecoJob` + backfill em lote), `RouteCandidateService` + `RoutePlannerPage.tsx` (Leaflet + OSRM público), otimização calculada no frontend, sem persistir itinerário (decisão consciente).
- **Auditoria** — `AuditLog` + tela filtrável.
- **Pagamento/assinatura real (Mercado Pago)** — Orders API + Preapproval, checkout 100% embutido (sem redirecionamento, decisão 2026-07-24), idempotência persistida (`payment_idempotency_keys`), webhook validado (HMAC), reconciliação agendada, painel admin de pendências + reprocessamento manual, reembolsos com histórico pro proprietário. Ver [[architecture-decisions]] pra decisões detalhadas de segurança/idempotência.
- **Fiscal — fundação D0, NÃO ativada** — schema completo (`fiscal_documents`, `tax_rules`, `fiscal_operation_profiles`, `fiscal_document_attempts`), `FiscalProviderRegistry` mapeia 4 slugs (`focus_nfe`/`plugnotas`/`nfeio`/`sped_nfe`) todos apontando pro `DraftOnlyFiscalProvider` (nenhum emite nota real). `FiscalReadinessCheckService` pronto. Roadmap técnico completo (v2, com 10 bloqueadores antes de qualquer emissão real — Reforma Tributária IBS/CBS, CNPJ alfanumérico, etc.) existe fora do repo (scratchpad de sessão, não commitado — pedir pro usuário se precisar de novo). **Pausado por decisão do usuário em 2026-07-24** até haver contrato com um provedor.
- **Delivery / loja pública (storefront)** — catálogo, checkout público, horário de funcionamento, taxa de entrega por bairro (bloqueia bairro sem taxa cadastrada), pedido mínimo, retirada na loja (`allow_store_pickup`/`fulfillment_type`, 2026-07-24), cupom (inclusive restrito por meio de pagamento), promoção por produto, carrinho abandonado (`CartEventController`), PWA (`StorefrontManifestController`).
- **Cashback** — programa configurável (%, teto, carência, validade), extrato no Portal, passivo medido em analytics.
- **Portal do cliente final** — identidade global por `final_customers` (e-mail+OTP — **desvio do roadmap original, que previa telefone+WhatsApp/SMS**; confirmar se é decisão consciente), tracking público por token, histórico, favoritos, reorder, avaliação, push real.
- **PDV e Balcão (restaurante/varejo físico)** — sessão de caixa (`cash_registers`/`cash_sessions`/`cash_movements`), PIN de operador, snapshot offline; Balcão: mesas, reservas (`table_reservations`/`table_waitlists`, 2026-07-27), fila de espera, comandas, KDS com workflow de status por item. Frente que cresceu sem estar em nenhum plano estratégico anterior — provável resposta a demanda de cliente real (tenant piloto que vende perecíveis).
- **Marketplace (iFood)** — `MarketplaceIntegrationController`, sincronização de catálogo/merchants/horários, importação de pedido externo, webhook dedicado.
- **API pública para terceiros** — `ApiKeyController`/`WebhookSubscriptionController`, rotas `public/*`, auditoria de entrega de webhook.
- **LGPD operacional** — `PrivacyRequestController`, exportação completa de dados do tenant (`TenantDataExportController`).
- **Reativação de clientes** — `reactivation_rules`/`reactivation_dispatches`, correlacionado com churn em analytics.
- **Portal do contador** — login/TOTP próprio, guard dedicado, aprovação de acesso por tenant, relatórios (DRE, fluxo de caixa), edição de campos fiscais de produto/cliente.
- **Suporte** — `SupportTicketController` + tela própria.
- **Hub central de Configurações** — reorganização de UI concluída 2026-07-24 (índice + blocos + drill-down), ver [[architecture-decisions]].

## Gaps reais confirmados (baixo esforço, não feitos)

- **2.1 Reposição automática por média móvel** — hoje só existe aproximação via analytics (`stockRuptures`/`stalledProducts`), sem ponto de reposição calculado nem alerta de estoque mínimo configurável disparando sozinho.
- **2.5 Metas de venda mensais** — não iniciado, nenhum model/campo encontrado.
- **CSV universal em grids** — mecanismo existe (ag-Grid), mas só ligado em 2 telas.

## Decisão descartada (mantida)

Lookup cross-tenant de cliente por telefone / registro global de pessoa física do CRM B2B — **não implementar** (risco LGPD, decisão de 2026-07-12 ainda válida). Diferente disso: `final_customers` (cliente da loja pública) já é uma identidade global, mas de domínio separado, sem cópia de dado sensível entre tenants (vínculo via `FinalCustomerTenantLink`) — não é uma violação da decisão, mas as duas coisas merecem ficar claramente distintas em qualquer decisão futura.

## Auditoria de gate de plano (concluída 2026-07-24)

Fonte: seeders (`FunctionalitiesSeeder`/`InitialPlansSeeder`) cruzados com rotas reais — banco de produção não acessível nesta sessão, sinalizar se planos foram editados manualmente pela UI sem re-rodar o seeder por cima (os seeders são idempotentes, delete+reinsert por plano).

**Corretamente gateadas (Ouro+/Diamante)**: cashback, analytics, reactivation, routes, stock/stock_locations (Ouro+); balcao, tax-rules, accounting-access (só Diamante). Nenhum furo real encontrado nessas.

**Liberadas em TODOS os planos, incluindo o mais barato (Prata) — possível receita perdida, decisão de negócio pendente**:
- **`api-access`** — cobre não só chave de API/webhooks de saída, mas **toda a integração com iFood Marketplace** (credenciais, sync de cardápio/pedidos, ações) foi propositalmente colocada nessa mesma functionality "pra não abrir outra antes da frente estabilizar". Maior achado de impacto: iFood + API pública estão de graça desde o plano mais barato.
- **`storefront`** — loja online pública inteira, cupons e promoções de produto rodam em cima dela, sem diferenciação nenhuma de plano.
- **`finance`** (conciliação) e **`support`** (central de chamados) — sem diferenciação, provavelmente aceitável mas não confirmado com negócio.

**Achados sem ação necessária**: `social_media` é Functionality "fantasma" (está em todo plano mas zero rota/controller implementado — feature de redes sociais nunca saiu do papel, [[feature_redes_sociais_plan]]); `subscription`/CRUD base/`dashboard`/`reports` liberados em todo plano por design correto (mecanismo de cobrança e núcleo operacional, não diferenciador).

**Furo técnico pontual (não é decisão de negócio, é bug de consistência)**: `PUT /operator-pin`/`POST /operator-session` do PDV usam só `tenant`, sem `perm:pdv` — tenant sem PDV no plano consegue mexer em PIN/sessão de operador mesmo sem conseguir abrir venda/caixa (essas sim gateadas). Baixo risco, mas inconsistente.

**Decisão do usuário e aplicada (2026-07-24)**: `api-access` (chave de API/webhooks + iFood Marketplace) e `storefront` (loja online, cupons, promoções) removidos do plano Prata — passam a exigir Ouro+ (mesmo tier de cashback/analytics/PDV). Sem override de compatibilidade — decisão explícita foi "aplicar geral, sem exceção", tenant Prata que já usava perde acesso imediato. `finance`/`support` ficam como estão (decisão explícita de não mexer). Aplicado via `php artisan db:seed --class=InitialPlansSeeder --force` contra o banco real (confirmado `DB_HOST` antes, per regra de [[feedback_destructive_db_commands]]) — `tenantPlanAllowsFunctionality()` não é cacheado (consulta direta), efeito imediato sem precisar invalidar cache.

## Próximos passos recomendados (auditoria de arquitetura, 2026-07-24)

1. Auditar `plan_functionalities` (em andamento).
2. Decidir provedor fiscal + regime tributário do MVP quando o usuário tiver contrato fechado (pausado por decisão explícita).
3. Itens de baixo esforço/baixo risco (reposição automática, metas de venda) — sem urgência.
