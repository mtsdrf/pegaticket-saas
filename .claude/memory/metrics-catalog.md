---
name: metrics-catalog
description: Catálogo técnico de métricas/indicadores de vendas, clientes e financeiro — nome oficial, fórmula, fonte de dado e tratamento de cancelamento/cortesia/reembolso já implementado.
metadata:
  type: project
---

Documento de referência técnica interna (Fase A0 do
`docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md`, seção 5.2).
NÃO é tela de produto — é a fonte única de verdade para quem for implementar
ou revisar qualquer indicador de vendas/clientes/financeiro, para evitar a
divergência de definição que gerou o bug de RFM corrigido nesta mesma fase
(ver `architecture-decisions.md` e `RfmCalculator`). Cobre o que já existe em
código hoje; a seção 3 lista o que a spec pede e ainda não existe, sem
inventar fórmula para o que falta.

## Regra transversal de cancelamento/cortesia/reembolso (o que já está implementado)

- **Cancelamento** (`sales.cancelled_at` preenchido): excluído de TODA
  agregação de vendas/receita — `ReportService`/`AnalyticsService` filtram
  `whereNull('cancelled_at')` em toda query-base (`salesQuery()`). Decisão
  registrada em `architecture-decisions.md`. Sem exceção conhecida.
- **Soft delete** (`sales.deleted_at` preenchido): mesma regra, sempre
  excluído (`whereNull('deleted_at')`).
- **Cortesia** (`GuestListService::redeem()`): é uma `Sale` real, criada com
  `unit_price = 0`, `origin = 'staff'`, nascendo paga. Ela **passa pelos
  mesmos filtros** de `salesQuery()` (não é cancelada nem soft-deletada) e
  portanto **conta como pedido/venda** em contagens (`total_sales`,
  `order_count`, frequência de RFM etc.), mas contribui **R$ 0,00** para
  qualquer soma de `total_amount`/receita — não há filtro que a remova, o
  efeito vem só do preço zerado. Isso significa: ticket médio de um tenant
  com muita cortesia é artificialmente puxado para baixo (nº de pedidos
  infla, receita não). Nenhum relatório hoje separa "vendas pagas" de
  "cortesias" como dimensão própria — é um item **AUSENTE** identificado no
  roadmap (KPI 3, seção 4.1).
- **Reembolso/estorno** (`SaleRefund`, via `SaleRefundService::create()`):
  registra o estorno (já processado externamente no PagBank) e invalida os
  tickets afetados (`status = 'estornado'`), mas **NÃO seta
  `sales.cancelled_at`** nem reduz `sales.total_amount`. O ajuste financeiro
  (dedução do organizador/exposição da plataforma) acontece só na camada de
  `Receivable`/`Settlement`/`SettlementAdjustment`
  (`SaleRefundFinancialAdjustmentService`), fora de `ReportService`/
  `AnalyticsService`. Consequência prática: **hoje, receita/ticket
  médio/RFM/ABC continuam contando o valor total da venda mesmo após
  reembolso parcial ou total** — só o relatório financeiro
  (`FinanceOperationsService`) reflete o ajuste, e mesmo assim como
  dedução de repasse, não como "receita líquida de reembolso". É gap
  confirmado no roadmap (KPI 10, seção 4.1: "PARCIAL... não há indicador de
  '% sobre receita' agregado especificamente para o Home").
- Exceção pontual: `FinalCustomerTenantLinkRepository::crmSummaryForTenant()`
  (CRM) só soma `sales.is_paid = true` (além de não-cancelado/não-deletado)
  — `total_spent`/`purchase_count` do CRM ignoram pedido não pago, diferente
  de `AnalyticsService::topClients()`/`ReportService::rfmClients()`, que
  contam qualquer venda ativa independente de `is_paid`. Isso é uma
  divergência real de base de cálculo entre CRM e RFM que este catálogo só
  documenta — corrigi-la está fora do escopo da Fase A0 (não foi apontada
  como o bug a corrigir) e deve ser avaliada em fase futura.

## 1. Vendas e receita (`ReportService`, `AnalyticsService`)

| Métrica | Fórmula | Fonte |
|---|---|---|
| Receita bruta (`total_sales_amount`) | `SUM(sales.total_amount)` sobre `salesQuery()` (tenant, não cancelado, não deletado, período opcional por `created_at`) | `sales.total_amount` |
| Ticket médio (`average_ticket`) | `total_sales_amount / total_sales` (0 se sem pedido) | idem |
| Valor recebido (`amount_received`) | `SUM(total_amount)` de vendas não-parceladas com `is_paid=true` + `SUM(amount)` de `sale_installments` com `is_paid=true` (join com `sales` ativo) | `sales`, `sale_installments` |
| A receber (`amount_receivable`) | `total_sales_amount - amount_received` | derivado |
| Crescimento vs. período anterior (`sales_growth_percentage`) | `(total_atual - total_anterior) / total_anterior * 100`; período anterior = mesma duração imediatamente anterior a `date_from` | `ReportService::salesComparison()` |
| Vendas por mês / sazonalidade ano×mês | `COUNT`/`SUM` agrupado por `created_at` (mês em PHP no `ReportService`, `DATE_FORMAT`/`strftime` por engine no `AnalyticsService::salesHistory`) | `sales.created_at` |
| Receita por canal (`byChannel`) | `SUM(total_amount)` agrupado por `sales.origin`, mesma base de `salesQuery()` | `sales.origin` |
| `salesSummary` (Analytics, dia/mês) | Contagem + receita + ticket médio por bucket, com bucket comparativo do período imediatamente anterior de mesma duração | `sales.created_at`, `sales.total_amount` |
| Top ticket types | `SUM(sale_items.line_total)` e `SUM(sale_items.quantity)` por `ticket_type_id`, via `sale_items` (snapshot da venda, não preço atual do produto) | `sale_items`, `ticket_types` |
| Curva ABC (ticket types/clientes) | Ordena por receita desc, participação = `receita_item / receita_total * 100`, acumula; classe A ≤80%, B ≤95% (exclusive), C = resto | `sale_items`/`sales` agregado |
| Margem bruta (`marginSummary`) | `gross_margin = revenue_with_known_cost - SUM(quantity * ticket_types.last_purchase_cost)`; `coverage_percentage = revenue_with_known_cost / total_revenue * 100` — só conta item cujo `ticket_types.last_purchase_cost` não é nulo | `sale_items`, `ticket_types.last_purchase_cost` |
| ROI de cupom (`couponRoi`) | Ticket médio de vendas com `coupon_id` vs. sem, `ticket_lift_percentage = (avg_com - avg_sem) / avg_sem * 100`. **Documentado como correlação, não causalidade** (autosseleção de cliente) | `sales.coupon_id`, `sales.discount_amount` |
| Concentração de receita (`revenueConcentration`) | `top10_revenue / total_revenue * 100`, top 10 clientes por `SUM(total_amount)` no período | `sales` |
| Mapa de calor dia×hora (`salesByHour`) | `COUNT`/`SUM` agrupado por dia-da-semana (`DAYOFWEEK`, 1=domingo) × hora (0-23) | `sales.created_at` |
| Vendas por dimensão (`AnalyticsService::salesByDimension`, Fase A1) | Endpoint único `dimension=ticket_type\|client\|origin` que substitui/unifica (aditivo, endpoints antigos continuam) `topTicketTypes`/`topClients`/`byChannel` — mesma fórmula de cada um, só shape de retorno padronizado (`key`/`label`/`order_count`/`quantity_sold`/`revenue`) | `sale_items`/`sales` conforme dimensão |
| Receita líquida básica (`net_revenue_amount`, KPI 2, Fase A1) | `SUM(receivables.net_amount)` das vendas do tenant com `created_at` no período (join `sales`↔`receivables.sale_id`). SÓ realizado — `Receivable` só existe para venda paga (`ReceivableGenerationService`), não há projeção do que ainda vai vender | `receivables.net_amount`, `sales.created_at` |
| Ocupação comercial (`occupancy_percentage`/`tickets_issued`/`commercial_capacity`, KPI 4, Fase A1) | `tickets_issued / commercial_capacity * 100`. `tickets_issued` = `COUNT(tickets)` com `status='ativo'` cujo `ticket_type` tem `quantity_available` não nulo; `commercial_capacity` = `SUM(ticket_types.quantity_available)` (só tipos com capacidade cadastrada). Snapshot atual do tenant, **NÃO filtrado por período** (capacidade é atributo estrutural, não evento datado) — ticket type sem capacidade cadastrada (venda ilimitada) fica fora dos dois lados da conta | `tickets.status`, `ticket_types.quantity_available` |

## 2. Clientes, CRM e RFM

| Métrica | Fórmula | Fonte |
|---|---|---|
| RFM (segmento único) | **Ver `RfmCalculator`** (`api/app/Services/Report/RfmCalculator.php`) — score relativo por tercil (1..3) sobre recência (invertida), frequência e valor monetário do conjunto de clientes ativo no período; regra de rótulo: R=1 → `inativo` se F=1 senão `em_risco`; R≥2 → `vip` se F=3 e M=3, `recorrente` se F≥2, senão `em_risco`. Usado por `ReportService::rfmClients()` (Home) e `AnalyticsService::topClients()` (Análises) — **fórmula única desde a Fase A0**, antes divergiam (ver `architecture-decisions.md`) | `sales` + `final_customers`, agregado por cliente |
| Top clientes por valor | `SUM(total_amount)` desc por `final_customer_id`, com `order_count`/`last_order_at` | `sales` |
| Atraso médio de pagamento (`paymentDelays`/`latePaymentClients`) | Média de `DATEDIFF(paid_at, created_at)` por cliente, só vendas com `paid_at` preenchido | `sales.created_at`, `sales.paid_at` |
| Churn (`churnClients`) | Cliente com **2+ vendas ativas** cuja última venda é anterior a `now() - 60 dias` (`CHURN_INACTIVITY_DAYS`); receita mensal em risco = `SUM(total_amount)` dos pedidos nos 90 dias antes da última compra, dividido por 3 | `sales` |
| CRM (`crmSummaryForTenant`) | `total_spent = SUM(total_amount)` e `purchase_count = COUNT(*)` só de vendas **pagas** (`is_paid=true`), não cancelada/deletada, por `final_customer_tenant_link`; `last_purchase_at = MAX(paid_at)` | `final_customer_tenant_links`, `sales` |

## 3. Pagamentos, inadimplência e cobrança

| Métrica | Fórmula | Fonte |
|---|---|---|
| Pedidos vencidos (`overdueOrders`) | União de (a) parcela vencida não paga (`due_date < hoje`, `is_paid=false`) agregada por venda, valor = soma das parcelas vencidas, dias = maior atraso; (b) venda não parcelada com `due_date` vencido e `is_paid=false`, valor = `total_amount` | `sale_installments`, `sales` |
| Aging de recebíveis / previsão por mês | Bucket por faixa de dias de atraso / por mês de vencimento, `SUM` de valor em aberto | `sale_installments`/`sales` |
| `overdue_percentage` (resumo filtrado) | `COUNT` de vendas `is_paid=false AND is_installment=false AND due_date < hoje` sobre o total filtrado — **definição simplificada**, não cobre parcela atrasada de venda parcelada (essa lógica vive só em `overdueOrdersCount()`) | `sales` |

## 4. Operação e check-in (`OperationSnapshotService`, `AnalyticsService::checkinInsights`)

| Métrica | Fórmula | Fonte |
|---|---|---|
| Check-ins hoje (granted/warning/blocked) | `granted` = resultado em `CHECKIN_GRANTED_RESULTS` (`valido`, `reentrada_autorizada`); `warning` = `CHECKIN_WARNING_RESULTS` (já utilizado, limite/intervalo de reentrada excedido); `blocked` = `total - granted - warning` | `ticket_checkins.result` |
| Taxa de presença (`attendance_rate`) | `unique_granted_tickets / issued_count * 100`, por sessão ou por tipo de ingresso (`tickets` ativos como denominador) | `ticket_checkins`, `tickets` |
| Taxa de erro de checkout | `(checkout_terminal - checkout_completed) / checkout_terminal * 100`, sobre `InventoryHold` das últimas 6h (`STATUS_CONVERTED` = sucesso; `STATUS_EXPIRED`/`STATUS_ABANDONED` = erro/desistência) — **proxy**, não telemetria dedicada | `inventory_holds` |
| Fila virtual (waiting/admitted) | `COUNT` por `status` em `VirtualQueueEntry` | `virtual_queue_entries` |

## 5. Financeiro e repasses (`FinanceOperationsService`)

| Métrica | Fórmula | Fonte |
|---|---|---|
| Em custódia / liberado / futuro / disponível agora | `SUM(net_amount)` de `Receivable` filtrado por `status` (`awaiting_release`/`release_requested` = custódia; `released`; `available_at > now()` = futuro; `available_at <= now()` = disponível) | `receivables.net_amount` |
| Reserva de risco retida/liberável | `SUM(reserve_amount)` com `reserve_status='held'`; liberável = mesmo filtro + `reserve_release_at <= now()` | `receivables.reserve_amount` |
| Ajustes em aberto | `SUM(amount)` de `SettlementAdjustment` com status `pending_recovery`/`pending_review` | `settlement_adjustments` |

## 5.1 Pagamentos (`AnalyticsService::paymentsSummary`, Fase A1)

Relatório básico de aprovação/recusa por período, sobre `sales.status`
(`pending_approval`/`confirmed`/`rejected`). **Sem roteamento entre
gateways** — decisão de produto já fechada (só PagBank existe). `rejected`
NÃO seta `sales.cancelled_at` (`SaleService::reject()`), então a venda
recusada passa pelo mesmo `salesQuery()` (`whereNull cancelled_at`) usado
no resto do módulo.

| Métrica | Fórmula | Fonte |
|---|---|---|
| `approval_rate_percentage` | `confirmed.count / (confirmed.count + rejected.count) * 100` — só sobre vendas DECIDIDAS, `pending_approval` fica fora do denominador | `sales.status` |
| `rejection_rate_percentage` | `rejected.count / (confirmed.count + rejected.count) * 100` | `sales.status` |

## 5.2 Alertas básicos (`AlertService::activeAlerts`, Fase A1)

Só os dois tipos aprovados nesta fase — NÃO é o catálogo completo de
alertas configuráveis da spec (sem model, sem regra por tenant, tudo
calculado on-the-fly a cada chamada de `GET /reports/alerts`).

| Alerta | Regra | Limiar (hardcoded, não configurável ainda) |
|---|---|---|
| `low_stock` | Por `ticket_type` ativo com `quantity_available` cadastrado: `remaining_percentage = (quantity_available - tickets_ativos_emitidos) / quantity_available * 100` | Dispara se `remaining_percentage <= 15%`; `severity='critical'` se `remaining = 0` |
| `payment_rejection_rate` | Taxa de recusa (mesma fórmula do `paymentsSummary`) na janela dos últimos 7 dias, com amostra mínima de 5 vendas decididas (evita alarme com 1 recusa em 1 venda) | Dispara se `rejection_rate >= 20%`; `severity='critical'` se `>= 40%` |
| `payment_pending_queue` | `COUNT(sales.status = 'pending_approval')` atual do tenant — proxy de "fila crescendo", sem histórico de tendência ainda | Dispara se `>= 15`; `severity='critical'` se `>= 30` |

## 6. Métricas da spec ainda não implementadas (gap — não inventar fórmula aqui)

Consolidado do gap analysis (roadmap seção 4, `docs/roadmap/2026-08-05-...md`).
Sem fórmula própria neste catálogo até serem implementadas — só o registro de
que a spec pede e o estado atual, para não reimplementar do zero sem
contexto quando a fase correspondente chegar:

- **Receita líquida projetada** (KPI 2) — PARCIAL desde a Fase A1: `net_revenue_amount` cobre o realizado (ver seção 1); projeção do que ainda vai vender continua AUSENTE.
- **Ocupação comercial** (KPI 4) — RESOLVIDO na Fase A1 (`occupancy_percentage`, ver seção 1) — snapshot atual, sem filtro de período (ver nota na fórmula).
- **Ingressos pago/reservado/cortesia separados** (KPI 3) — PARCIAL; cortesia hoje só se distingue olhando `sale_items.unit_price = 0`, nenhum endpoint expõe essa quebra.
- **Funil de conversão** (KPI 6) — AUSENTE; sem tracking de página/checkout iniciado além do proxy de `InventoryHold`.
- **Previsão de venda final** (KPI 8) — AUSENTE.
- **8 segmentos nomeados de RFM da spec** (campeões/leais/potenciais leais/novos/promissores/precisam de atenção/em risco/inativos, seção 33) — hoje `RfmCalculator` produz 4 rótulos (`vip`/`recorrente`/`em_risco`/`inativo`); expandir para os 8 é feature nova, fora do escopo de correção de bug da Fase A0.
- **Coortes de retenção, LTV previsto, afinidade entre eventos** (seção 8/Telas 3-5) — AUSENTE.
- **Rentabilidade por evento com custo cadastrado** (custo fixo/variável/marketing) — AUSENTE; só existe custo de produto (`last_purchase_cost`) via `marginSummary`.
- **Relatório de reembolsos/chargebacks dedicado** (Pareto, % sobre receita) — AUSENTE, apesar do dado-base existir (`SaleRefund`, `SettlementAdjustment`).
- **Antifraude agregado** (fila de revisão, taxa de falso positivo, Pareto de regras) — AUSENTE; `RiskEngineService` decide e persiste mas não tem endpoint de relatório.
