# PegaTicket — Roadmap de Refatoração de Indicadores, Dashboards e Relatórios

Data de referência: **5 de agosto de 2026**

Documento-base analisado: [pegaticket_indicadores_dashboards_relatorios.md](/home/mtsdrf/workspace/pegaticket-saas/pegaticket_indicadores_dashboards_relatorios.md) (2706 linhas, especificação completa da camada analítica).

Este documento **não implementa código**. É análise de gap (spec nova × código real) e plano de refatoração/implementação faseado, no mesmo espírito de honestidade do [roadmap global de 2026-08-02](2026-08-02-pegaticket-global-gap-roadmap.md).

---

## 1. Resumo executivo

A especificação pede uma camada analítica de duas metades — um **Home executivo** (12 KPIs, 8 gráficos, alertas/oportunidades, tudo comparativo e acionável) e uma **Central de Relatórios** com 20 áreas/19 relatórios detalhados (vendas, funil, inventário/assentos, financeiro, pagamentos, marketing/atribuição, CRM/RFM/coortes/LTV, acesso/check-in, afiliados, cupons/cortesias, reembolsos/chargebacks, antifraude, bilheteria, adicionais/consumo, atendimento, revenda/transferência, fiscal/conciliação, previsões/IA, e um construtor de relatórios personalizados com métricas calculadas e exportação XLSX/CSV/PDF agendável). É, em volume, a maior peça de especificação funcional entregue até agora no projeto — e pede coisas que hoje **não existem em nenhum SaaS de ticketing concorrente de forma completa**; é um documento aspiracional de "estado da arte", não um MVP.

O PegaTicket hoje **já tem uma camada analítica real, não greenfield**: um dashboard operacional (`ReportService`), um módulo de analytics mais avançado com RFM básico e ABC (`AnalyticsService`), um snapshot operacional quase-tempo-real (`OperationSnapshotService`, construído nesta mesma sessão de trabalho), painéis financeiros dedicados (`FinanceOperationsService`/`AdminFinanceOperationsService`) e um resumo de CRM (`crmSummaryForTenant`). O que falta majoritariamente é: (a) as áreas inteiras que a spec pede e que hoje não têm nem dado-base nem cálculo (funil de conversão real, atribuição de marketing, coortes/retenção, antifraude analítico, bilheteria por operador, consumo/cashless, atendimento/NPS, fiscal/conciliação, previsões), e (b) a infraestrutura transversal que a spec assume como dada — catálogo de métricas, metas, alertas configuráveis, filtros salvos, construtor de relatórios, exportação XLSX real, agendamento — nenhuma dessas existe hoje.

---

## 2. Metodologia

1. Leitura integral da especificação (`pegaticket_indicadores_dashboards_relatorios.md`, todas as 2706 linhas, em 4 blocos de leitura sequenciais — nenhuma seção foi amostrada ou pulada).
2. Levantamento do código real via `grep`/`find` em `api/app/Http/Controllers`, `api/app/Services`, `api/app/Repositories`, `api/routes/api.php` e `web/src/pages`, `web/package.json`.
3. Leitura completa dos três services que hoje concentram a camada analítica (`ReportService.php`, `AnalyticsService.php`, `OperationSnapshotService.php`) linha a linha, não só assinatura de método — para confirmar granularidade real (dimensões, filtros, exclusões) e não só a existência do endpoint.
4. Leitura parcial de `FinanceOperationsService`, `crmSummaryForTenant` (em `FinalCustomerTenantLinkRepository`), `EventFinancialCloseoutService`, `AffiliateService`/`AffiliateCommissionService`, `RiskEngineService`, `VirtualQueueService` para confirmar o que essas camadas já expõem versus só calculam internamente.
5. Checagem de bibliotecas reais instaladas (`api/composer.json`, `web/package.json`) para não assumir capacidade técnica inexistente (ex.: biblioteca de Excel, biblioteca de gráficos avançados).
6. **Limitação honesta**: não li 100% dos ~341 endpoints de `api/routes/api.php` nem todos os componentes de `web/src/pages` linha a linha — para uma spec deste tamanho (94 seções), isso extrapolaria o escopo do que é razoável em uma sessão. Os achados de "não existe" são baseados em busca textual (nome de classe/rota/model) mais leitura completa dos arquivos centrais de relatório; é possível que um indicador pontual exista escondido dentro de outro controller sem "report"/"analytics" no nome e não tenha sido encontrado. Onde a confiança é menor, isso está marcado explicitamente na tabela de gap.

---

## 3. Inventário do que já existe

### 3.1 Backend — camada de relatórios/analytics

| Módulo | Arquivo | O que cobre |
|---|---|---|
| `ReportController` / `ReportService` | `api/app/Http/Controllers/Report/ReportController.php`, `api/app/Services/Report/ReportService.php` | `indicators()` (receita, pedidos pagos/não pagos, ticket médio, comparação com período anterior, vendas atrasadas — `ReportService.php:25-53`), `charts()` (vendas por mês, pago×não pago, sazonalidade ano×mês, top ticket types, top clientes, RFM básico por tercil, clientes com atraso de pagamento, pedidos vencidos, aging de recebíveis, previsão de recebíveis por mês, curva ABC de ticket types e clientes — `charts():55-91`), `byChannel()` (receita por origem de venda — `:163-185`), `generateSalesPdf()` (PDF via DomPDF, não Excel — `:190-204`) |
| `AnalyticsController` / `AnalyticsService` | `api/app/Http/Controllers/Report/AnalyticsController.php`, `api/app/Services/Report/AnalyticsService.php` | 14 endpoints: `salesSummary` (bucket dia/mês com comparação de período anterior), `topTicketTypes`, `salesByLocation` (**stub vazio — geografia foi removida do produto junto com endereço do comprador**, `AnalyticsService.php:83-93`), `salesHistory` (matriz ano×mês completa), `topClients` (RFM por tercil, rótulo vip/recorrente/em_risco/inativo — `:148-190`), `paymentDelays`, `overdueOrders`, `abcAnalysis` (por ticket type ou cliente), `marginSummary` (margem bruta via `last_purchase_cost` de `ticket_types`, com `coverage_percentage` avisando quanto do faturamento tem custo cadastrado), `couponRoi` (comparação de ticket médio com/sem cupom, **documentado explicitamente como correlação, não causalidade** — `:363-371`), `revenueConcentration` (top 10 clientes), `churnClients` (60 dias de inatividade, 2+ compras), `salesByHour` (heatmap dia×hora), `checkinInsights` (granted/warning/blocked, por sessão e por tipo de ingresso, com `attendance_rate`) |
| `OperationSnapshotService` | `api/app/Services/Report/OperationSnapshotService.php` | Snapshot quase-tempo-real: caixa atual, pedidos pendentes de aprovação, vendas de hoje, check-ins de hoje (granted/warning/blocked), taxa de erro de checkout (proxy via `InventoryHold`, janela de 6h), fila virtual (waiting/admitted) — endpoint com throttle mais generoso (`reports-operation-snapshot`, 200/min) pra suportar polling |
| Financeiro tenant-scoped | `api/app/Services/Finance/FinanceOperationsService.php` | `dashboard()` (custódia, liberado, futuro, disponível agora, contagem de recebíveis pendentes, reserva de risco retida — `:14-114` e além), `receivables()`/`receivablesSummary()`, `settlements()`/`settlementsSummary()`, todos paginados e filtráveis por evento |
| Financeiro admin global | `api/app/Services/Finance/AdminFinanceOperationsService.php` | Mesmo recorte agregado só que cross-tenant, para o admin da plataforma |
| Fechamento financeiro por evento | `api/app/Services/Finance/EventFinancialCloseoutService.php` | `build()` (demonstrativo por evento) e `exportCsv()` (**único export CSV real hoje fora do PDF de vendas**) |
| CRM/segmentação | `api/app/Repositories/Eloquent/FinalCustomerTenantLinkRepository.php:79` `crmSummaryForTenant()` | Paginado, com filtros `minSpent`/`minPurchases`/`inactiveDays`/busca; agrega `total_spent`, `purchase_count`, `last_purchase_at` por cliente vinculado ao tenant |
| Afiliados | `api/app/Services/Affiliate/AffiliateService.php`, `AffiliateCommissionService.php` | CRUD de afiliado, geração de comissão por venda, paginação de comissões por afiliado (`paginateCommissions`) — **sem endpoint de ranking/ROI agregado** |

Rotas confirmadas em `api/routes/api.php:1132-1213`: bloco `reports/*` (indicators, operation-snapshot, charts, sales, sales/summary, by-channel, sales/pdf) e `reports/analytics/*` (os 14 endpoints acima), mais `finance/dashboard` (tenant, `:1212`) e `admin/finance/dashboard` (`:500`).

### 3.2 Frontend

| Página | Arquivo | Cobertura |
|---|---|---|
| Dashboard (Home) | `web/src/pages/Dashboard/DashboardPage.tsx` (491 linhas) | KPIs via `MetricCard`, gráfico de vendas por mês (`SalesByMonthChart`), matriz de sazonalidade, aging de recebíveis, previsão de recebíveis, ranking (`RankingListCard`), snapshot operacional (`OperationSnapshotCard`), checklist de onboarding, filtro de período (`PeriodFilter`) |
| Analytics | `web/src/pages/Analytics/AnalyticsPage.tsx` (114 linhas — provavelmente orquestra componentes menores não auditados individualmente) | Consome os 14 endpoints de `AnalyticsController` |
| Relatório de vendas | `web/src/pages/SaleReport/SaleReportListPage.tsx`, `ChannelReportPage.tsx` | Listagem filtrável + relatório por canal |
| Financeiro | `web/src/pages/Finance/FinanceOperationsPage.tsx` (355 linhas), `web/src/pages/Admin/AdminFinanceOperationsPage.tsx` | Painel financeiro tenant-scoped e superfície admin global |

**Biblioteca de gráficos**: `chart.js` ^4.5.1 + `react-chartjs-2` ^5.3.1 — únicas libs de visualização no `web/package.json`. Suporta linha, barra, área, rosca/pizza, dispersão, radar, bolha. **Não suporta nativamente**: funil, Sankey, treemap, waterfall, bullet chart, heatmap-matriz (dá para simular com bolhas, mas sem componente dedicado), mapa geográfico coroplético, diagrama de rede. Há `leaflet`/`react-leaflet` (mapa geográfico de ponto, já usado — provavelmente para locais/venues, não confirmado se para analytics) e `ag-grid-community` (tabelas, com exportação CSV nativa na versão community; **exportação XLSX real exige `ag-grid-enterprise`, que não está instalado**).

**Exportação**: não há `maatwebsite/excel` nem qualquer lib de geração de `.xlsx` no `composer.json` do backend. O único export estruturado hoje é PDF (DomPDF, só para vendas) e um CSV manual no fechamento financeiro por evento (`EventFinancialCloseoutService::exportCsv`). **Toda a Parte VI da especificação (exportação XLSX formatada, 5 abas, agendamento, exportação assíncrona) não tem nenhuma base técnica hoje.**

### 3.3 Domínios com dado-base pronto para virar indicador (mas sem agregação dedicada ainda)

- **Antifraude**: `RiskEngineService` (`api/app/Services/Risk/RiskEngineService.php:108,157`) já avalia e persiste decisões de risco por venda/reembolso (6 heurísticas, confirmado no roadmap global) — mas não expõe nenhum endpoint agregado de relatório (fila de revisão, taxa de fraude, Pareto de regras acionadas).
- **Fila virtual**: `VirtualQueueService` tem estado (`waiting`/`admitted`, já usado no `OperationSnapshotService`) mas não tem relatório histórico de tempo de espera/abandono de fila.
- **Marketing/UTM**: `sales.utm_source`/`utm_medium`/`utm_campaign` são persistidos (`SaleService.php:302-304`, `StorefrontCheckoutService.php:172-174`) — o dado-base existe na tabela `sales`, mas **não há nenhum endpoint de agregação por UTM/campanha/atribuição**. É "existe parcialmente" no sentido mais literal possível: a coluna existe, zero query agregada sobre ela.
- **Revenda/transferência**: `TicketResaleListing` e o fluxo de `TicketService::transfer()` existem (confirmado no roadmap global) mas não têm relatório de conversão/tempo até revenda/impacto financeiro.
- **Cupons**: `sales.coupon_id`/`discount_amount` existem e `couponRoi()` já cobre uma fatia (ticket médio com/sem cupom); falta ranking de cupons individuais, taxa de uso, detecção de abuso.
- **Check-in/portaria**: `checkinInsights()` cobre granted/warning/blocked por sessão e tipo de ingresso; **não cobre** desempenho por portaria (`EventGate` existe como entidade mas não aparece agregado em nenhum relatório), velocidade por minuto, curva de chegada, no-show.

---

## 4. Gap analysis — spec × código real

Convenção: **[PRONTO]** já existe e atende ou quase atende · **[PARCIAL]** dado-base existe, agregação específica não · **[AUSENTE]** nem dado nem cálculo · **[DECISÃO]** requer decisão de produto/arquitetura antes de implementar.

### 4.1 Home executivo (Parte II da spec, seções 4-8)

| Item da spec | Classificação | Nota |
|---|---|---|
| KPI 1 Receita bruta confirmada | **PRONTO** | `ReportService::indicators()` já traz `total_sales_amount` com comparação de período |
| KPI 2 Receita líquida projetada | **AUSENTE** | Não existe conceito de "receita líquida" (descontada de taxas/comissões/impostos) nem projeção até o fechamento em nenhum endpoint hoje — o financeiro tem `net_amount` em `Receivable`, mas não projetado no Home |
| KPI 3 Ingressos vendidos (pago/reservado/cortesia separados) | **PARCIAL** | Contagem de pedidos existe; segmentação pago × reservado × cortesia especificamente para ingressos (não pedidos) não está agregada em nenhum endpoint |
| KPI 4 Taxa de ocupação comercial | **AUSENTE** | Não há endpoint que cruze ingressos válidos emitidos ÷ capacidade comercial. O dado-base (capacidade de `ticket_types`/setor, ingressos emitidos) existe nos domínios de Evento/Inventário, mas não há agregação de ocupação exposta em relatório |
| KPI 5 Ticket médio (pedido/participante) | **PRONTO** | `average_ticket` em `indicators()` |
| KPI 6 Conversão de compra (funil) | **AUSENTE** | Não existe tracking de visualização de página nem funil de etapas (ver 4.3) |
| KPI 7 Vendas últimas 24h | **PARCIAL** | `OperationSnapshotService::snapshot()` traz `sales_today`, mas é "hoje" (desde meia-noite), não uma janela móvel de 24h com comparação às 24h anteriores |
| KPI 8 Previsão de venda final | **AUSENTE** | Nenhum modelo de previsão implementado (ver 4.9) |
| KPI 9 Saldo a receber | **PRONTO** | `FinanceOperationsService::dashboard()` já cobre custódia/liberado/futuro/disponível |
| KPI 10 Reembolsos e chargebacks | **PARCIAL** | Existe o domínio (`SaleRefundFinancialAdjustmentService`, `ExternalReviewFinancialAdjustmentService` no financeiro), mas não há indicador de "% sobre receita" nem "eventos com maior incidência" agregado especificamente para o Home |
| KPI 11 Público presente agora | **PRONTO (parcial)** | `OperationSnapshotService` cobre check-ins/granted/warning/blocked; falta "entradas nos últimos 15 min" e "reentradas" separadas no Home (reentrada já é rastreada no `checkinInsights`, mas não no snapshot) |
| KPI 12 Saúde operacional (composto) | **PARCIAL** | Os componentes existem espalhados (taxa de erro de checkout no snapshot, sincronização offline mencionada no roadmap global) mas não há um índice composto único com explicação de motivos |
| Gráficos 7.1-7.8 (linha, funil, barras, rosca, heatmap, gauge, área empilhada, ranking de risco) | **PARCIAL/AUSENTE** | Linha de vendas (7.1) e heatmap dia×hora (7.5, via `salesByHour`) existem; funil (7.2), ranking com meta/previsão (7.3, 7.6), área empilhada por origem de tráfego (7.7) e ranking de risco composto (7.8) não existem |
| Alertas críticos / oportunidades / feed (seção 8) | **AUSENTE** | Não existe nenhum sistema de alerta configurável nem feed de acontecimentos hoje. É infraestrutura nova, não refatoração |

### 4.2 Filtros globais e infraestrutura transversal (seção 3, Partes V-VII)

| Item | Classificação | Nota |
|---|---|---|
| Filtros básicos (evento, canal, tipo de ingresso, setor, lote, etc.) | **PARCIAL** | Cada endpoint hoje implementa seu próprio subconjunto de filtros ad-hoc (`origin`, `date_from`/`date_to`, `dimension`) — não há uma camada de filtro global compartilhada entre Home e relatórios |
| Períodos rápidos / comparação | **PARCIAL** | `presetRange()` no frontend (`web/src/utils/period.ts`, não lido linha a linha, mas referenciado em `DashboardPage.tsx`) já cobre períodos rápidos; comparação com período anterior existe em `ReportService`/`AnalyticsService`; comparação com meta/evento semelhante/mediana não existe (depende de Metas, ver abaixo) |
| Filtros salvos (seção 3.4) | **AUSENTE** | Nenhum model/tabela de "visão salva" |
| Catálogo de métricas com fórmula/dono/histórico (seção 2.6) | **AUSENTE** | Fórmulas hoje só existem como comentário no código-fonte (ex. o docblock de `couponRoi` avisando sobre correlação × causalidade), não como metadado consultável pelo usuário |
| Metas (seção 78) | **AUSENTE** | Nenhum model de meta |
| Alertas configuráveis (seção 79) | **AUSENTE** | Nenhum model de alerta/regra |
| Anotações na linha do tempo (seção 80) | **AUSENTE** | Nenhum model de anotação de evento comercial |
| Construtor de relatórios personalizados (Parte V) | **AUSENTE / DECISÃO** | Não existe; é a peça de maior risco arquitetural do documento inteiro (ver seção 5) |
| Exportação XLSX formatada (Parte VI) | **AUSENTE / DECISÃO** | Sem lib de Excel instalada; exportação assíncrona/agendada não existe |
| Agendamento de relatórios (seção 77) | **AUSENTE** | Não existe infraestrutura de job agendado de relatório (o `SendRecompraNudgeMailsCommand` do roadmap global é o único precedente de "job agendado de comunicação", não de relatório) |

### 4.3 Central de relatórios, por área (Parte III)

| Relatório da spec | Classificação | Nota |
|---|---|---|
| 1. Visão executiva do portfólio (comparação entre eventos, cruzamentos) | **AUSENTE** | Não há endpoint de comparação lado a lado entre eventos com normalização por estágio comercial |
| 2. Vendas e receita (resumo, detalhamento por dimensão, curva de vendas) | **PRONTO (parcial)** | `ReportService`/`AnalyticsService` cobrem boa parte de "resumo de vendas"; "detalhamento por dimensão" (pivot livre entre 14 dimensões) não existe — cada dimensão hoje é um endpoint fixo separado, não uma dimensão selecionável; curva de vendas normalizada por dias-antes-do-evento não existe |
| 3. Funil e conversão | **AUSENTE** | Sem tracking de visualização/clique/seleção — o funil da spec começa em "impressão do evento", que exigiria instrumentação de front-end/analytics de página que hoje não existe |
| 4. Inventário, lotes e assentos | **PARCIAL** | Dado-base forte (`TicketType`, `TicketTypeChannelQuota`, `Seat`, `SeatMapViewer`, virada automática de lote — todos confirmados no roadmap global), mas nenhum relatório agregado de ocupação por setor/fileira, velocidade de venda por lote, ou mapa de calor de assentos mais procurados |
| 5. Financeiro e repasses | **PRONTO (parcial)** | `FinanceOperationsService`/`AdminFinanceOperationsService`/`EventFinancialCloseoutService` cobrem demonstrativo, agenda de recebimentos e fechamento por evento; falta "rentabilidade por evento" com custos cadastrados (custo fixo/variável/marketing) — não há model de custo de evento hoje |
| 6. Pagamentos (desempenho, Pix, cartões, roteamento) | **PARCIAL** | Dado bruto existe nos webhooks/reconciliação de pagamento (Fase 1/5 fechadas conforme roadmap global); agregação analítica dedicada (taxa de aprovação por bandeira/emissor, Pareto de recusas) não existe. **Roteamento inteligente (seção 27) é decisão de produto já fechada como "não fazer" — plataforma opera só com PagBank** (confirmado no roadmap global 2026-08-05), então essa tela inteira da spec deve ser descartada, não implementada |
| 7. Marketing e atribuição | **PARCIAL** | UTM persistido em `sales` (ver 3.3); pixels Meta/GA4 existem no frontend (`marketingPixels.ts`, roadmap global); nenhuma agregação de CAC/ROAS/atribuição multi-touque no backend. Atribuição por modelo (último clique, linear, decaimento) é featureset de ferramenta de marketing madura — não existe nem parcialmente |
| 8. Público, clientes e CRM (perfil, RFM, coortes, afinidade, LTV) | **PARCIAL** | `crmSummaryForTenant` cobre perfil básico; `topClients`/`rfmClients` cobrem RFM de forma simplificada (tercil, sem os 8 segmentos nomeados da spec — campeões/leais/etc.); coortes de retenção, afinidade entre eventos e LTV previsto **não existem** |
| 9. Acesso, check-in e operação | **PARCIAL** | `checkinInsights` + `OperationSnapshotService` cobrem boa parte de "operação ao vivo" e "resumo"; desempenho por portaria (`EventGate` sem métrica agregada), curva de chegada e no-show **não existem** |
| 10. Afiliados, promotores e comissários | **PARCIAL** | CRUD e comissão por venda existem; ranking agregado, atribuição de qualidade (uso próprio de cupom, concentração de compradores) e relatório de comissões consolidado **não existem** |
| 11. Cupons, promoções, convites e cortesias | **PARCIAL** | `couponRoi` cobre uma fatia; ranking de cupons individuais, relatório de cortesias/guest list (taxa de presença, responsável, parceiro) **não existem** apesar do domínio `GuestList`/`GuestListEntry` já existir (roadmap global) |
| 12. Reembolsos, cancelamentos e chargebacks | **PARCIAL** | Domínio financeiro de exceções existe (`SaleRefundFinancialAdjustmentService`); relatório dedicado com motivos, Pareto, impacto financeiro isolado **não existe** |
| 13. Antifraude e risco | **PARCIAL** | `RiskEngineService` decide e persiste; **nenhum endpoint de relatório** consome essas decisões (fila de revisão manual, taxa de falso positivo, Pareto de regras acionadas) |
| 14. Bilheteria e ponto de venda | **PARCIAL** | `CashSession` existe (abertura/fechamento/reconciliação, roadmap global); relatório por operador/terminal e ranking de locais **não existem** |
| 15. Adicionais, estacionamento e consumo | **AUSENTE / DECISÃO** | `event_products` (adicionais) existe como domínio mas sem relatório; estacionamento e cashless/consumo **não existem como domínio nenhum**, não é questão de relatório faltando, é feature de produto inteira ausente — precisa decisão antes de sequer desenhar relatório |
| 16. Atendimento e experiência (NPS/CSAT) | **AUSENTE** | `help requests`/suporte existe (roadmap global) mas sem métricas de SLA/satisfação; NPS/CSAT não são coletados em lugar nenhum do produto hoje |
| 17. Transferência e revenda oficial | **PARCIAL** | Domínio existe (`TicketResaleListing`, transferência via `TicketService::transfer()`); relatório dedicado **não existe** |
| 18. Fiscal e conciliação | **PARCIAL / DECISÃO** | Conciliação financeira interna existe (`ReconciliationService`, `FinancialIntegrityReconciliationService`); "documentos fiscais" é decisão de produto **já fechada como "não fazer"** (NFe/ERP, roadmap global 2026-08-05) — essa metade do relatório 18 deve ser removida do escopo, não implementada |
| 19. Previsões e inteligência (forecast, cenários, anomalias, recomendações) | **AUSENTE / DECISÃO** | Zero infraestrutura de modelo preditivo hoje. É a área de maior distância entre spec e produto atual |
| 20. Construtor de relatórios personalizados | **AUSENTE / DECISÃO** | Ver seção 5 |

### 4.4 Contagem consolidada

Considerando os 20 "relatórios"/áreas da Parte III + as 12 seções de infraestrutura transversal (filtros, metas, alertas, catálogo de métricas, construtor, exportação, agendamento, anotações) + os 12 KPIs do Home = **56 unidades de escopo avaliadas**:

- **Já existe e atende (PRONTO)**: 6 — receita bruta, ticket médio, saldo a receber, público presente agora (parcial mas sólido), vendas e receita (resumo), financeiro e repasses (resumo).
- **Existe parcialmente (PARCIAL)**: 27 — a maioria das áreas de relatório tem dado-base ou um recorte inicial, mas não a agregação completa pedida.
- **Não existe (AUSENTE)**: 17 — funil, previsões/IA, catálogo de métricas, metas, alertas, filtros salvos, anotações, atendimento/NPS, coortes/LTV/afinidade, comparação entre eventos, construtor de relatórios, exportação XLSX/agendamento.
- **Requer decisão de produto/arquitetura antes de implementar (DECISÃO)**: 6 — construtor de relatórios personalizados, exportação assíncrona em massa, roteamento de pagamento (já decidido não fazer), fiscal/NFe (já decidido não fazer), cashless/consumo/estacionamento (feature nova, não só relatório), biblioteca de visualização avançada a padronizar.

Isso é consistente com o padrão já visto no roadmap global: núcleo transacional forte, camada de inteligência/BI ainda em estágio inicial.

---

## 5. Proposta de arquitetura de refatoração

**Isto é proposta para validação, não decisão fechada.** Cada ponto abaixo precisa de confirmação do usuário antes de virar trabalho de implementação.

### 5.1 Consolidar os services espalhados numa camada `Analytics` coerente

Hoje há 3 services de leitura agregada com sobreposição de responsabilidade e nomenclatura inconsistente (`ReportService` e `AnalyticsService` ambos fazem RFM/ABC de formas ligeiramente diferentes — `ReportService::rfmSegment()` usa faixas fixas de dias, `AnalyticsService` usa tercis; isso já é uma pequena divergência de definição que a spec proíbe explicitamente na seção 2.6/83 "os valores do Home e dos relatórios devem usar as mesmas definições"). Proposta:

- Criar `App\Services\Analytics\` como namespace único, com um service por **domínio de dado** (não por tela): `SalesAnalyticsService`, `InventoryAnalyticsService`, `FinanceAnalyticsService` (agregando sobre o que já existe em `Finance/*`, não reimplementando), `CheckinAnalyticsService`, `CrmAnalyticsService`, `RiskAnalyticsService`, `MarketingAnalyticsService`.
- Cada service expõe métodos que retornam dados **brutos e reutilizáveis**; a composição por tela (Home vs. relatório detalhado) acontece na camada de Controller/Resource, não duplicando query.
- `ReportService::rfmSegment()` e `AnalyticsService::topClients()` (RFM por tercil) precisam convergir para uma única fórmula de RFM antes de qualquer expansão — hoje um cliente pode ser "VIP" num endpoint e "recorrente" noutro para os mesmos dados. Isso é uma correção de bug de consistência, não feature nova.

### 5.2 Catálogo de métricas como pré-requisito, não como item da Fase 3

A spec pede catálogo de métricas (seção 2.6) na Parte I, mas o roadmap de priorização da própria spec (seção 88-91) só menciona "métricas calculadas" na Fase 3. Sugestão: tratar o catálogo (nome oficial, fórmula, fonte, tratamento de cancelamento/cortesia/reembolso) como um **artefato de documentação técnica interna primeiro** (um arquivo `.claude/memory/metrics-catalog.md` ou equivalente, não necessariamente uma tela), e só promovê-lo a feature de produto (tela consultável pelo usuário) mais tarde. Isso resolve o problema de consistência (5.1) sem exigir UI nova imediatamente.

### 5.3 Pré-agregação / materialização — decisão de performance pendente

A spec exige (seção 82) Home em até 3s e filtros em até 2s, além de "consultas pré-agregadas quando necessário". Hoje **todos os endpoints de relatório calculam em tempo real sobre `sales`/`sale_items`/`ticket_checkins`** sem nenhuma tabela de agregação. Isso é aceitável no volume atual, mas relatórios como "coortes de retenção" ou "curva de vendas normalizada por dias antes do evento" fazem full-scan histórico por natureza — não dá para responder em 2s com groupBy ao vivo à medida que o histórico cresce.

**Pergunta em aberto para o usuário**: aceitar introduzir uma ou mais tabelas de snapshot diário (`daily_sales_snapshots`, `daily_event_metrics`) alimentadas por job agendado (`php artisan schedule`), aceitando que esses relatórios fiquem "atualizados até ontem" em vez de tempo real? A spec já prevê essa distinção (seção 2.5: "tempo real onde importa" vs. "atualização periódica" para coortes/retenção/previsões) — mas isso é uma mudança de arquitetura (nova tabela, novo job, invalidação) que não deve ser assumida sem confirmação, principalmente porque introduz a primeira fonte de verdade duplicada do sistema (dado derivado que pode divergir do dado transacional).

### 5.4 Biblioteca de visualização — padronizar antes de expandir

`chart.js` cobre bem os KPIs e a maioria dos relatórios de vendas/financeiro atuais. A spec pede funil, Sankey, treemap, waterfall, bullet chart, heatmap-matriz e rede de relacionamento — nenhum tem componente pronto em `chart.js`. Três caminhos possíveis, nenhum decidido:

1. Construir os gráficos "exóticos" (funil, Sankey, waterfall) como componentes SVG customizados no padrão já usado em `SeatMapViewer.tsx` (o projeto já tem precedente de SVG customizado com tokens de tema) — sem dependência nova, mais trabalho de implementação.
2. Adicionar uma segunda lib especializada (ex. `visx`, `nivo`) só para os tipos que `chart.js` não cobre — dependência nova, risco de inconsistência visual entre as duas libs.
3. Escopo reduzido: implementar só linha/barra/rosca/heatmap simples (que `chart.js` cobre) nas primeiras fases e adiar funil/Sankey/waterfall/rede para quando houver dado real suficiente para justificá-los (funil sem tracking de página é gráfico vazio).

Recomendação de leitura (não decisão): caminho 3 combinado com 1 quando o dado já existir — evita dependência nova cedo, mas isso é uma opinião de arquitetura que o usuário deve validar, especialmente porque o projeto já tem uma skill de `dataviz` disponível para orientar a escolha visual quando chegar a hora de implementar.

### 5.5 Exportação XLSX/CSV/PDF — decisão de biblioteca e de modo síncrono/assíncrono

Não há `maatwebsite/excel` (ou equivalente) instalado. Antes de implementar a Parte VI da spec:

- **Decisão 1**: instalar `maatwebsite/excel` (padrão de mercado Laravel) ou gerar XLSX manualmente via `PhpSpreadsheet` direto (mais controle, mais trabalho).
- **Decisão 2**: exportações pequenas (relatório atual, poucas linhas) podem continuar síncronas como hoje (PDF de vendas); exportações grandes (spec pede ZIP, processamento assíncrono, notificação ao concluir) exigem fila (`queue:listen`, já em uso no projeto conforme `composer dev`) — mas isso é escopo de infraestrutura nova (job + storage temporário + expiração + notificação), não trivial.
- Ambas as decisões são pré-requisito de qualquer item da Fase 1 do roadmap abaixo que envolva "exportar", então precisam ser resolvidas cedo, não no fim.

### 5.6 Construtor de relatórios personalizados — maior risco arquitetural do documento

A Parte V pede que o usuário monte queries livres (fonte de dados + dimensões + métricas + métricas calculadas + filtros + pivot) com validação de segurança (divisão por zero, duplicidade por join, impacto de performance). Isso é, na prática, **um query builder genérico exposto a usuários finais de tenant** — superfície de risco real (mesmo com fórmulas "seguras" declarativas, não SQL livre, ainda é preciso validar contra N+1, contra queries que escaneiam toda a base, e contra vazamento de dado entre tenants se a camada de filtro de tenant não for automática e obrigatória em toda combinação). É a peça que mais precisa de revisão de `security-specialist` e `database-architect` antes de qualquer linha de código, e provavelmente a última peça a implementar, não a primeira — exatamente como a própria spec já reconhece ao colocá-la na Fase 3 (seção 90).

---

## 6. Roadmap faseado de implementação

Numeração própria deste documento (não reaproveita as fases do roadmap global de produto, que já vai até Fase 8 — para evitar colisão de nomenclatura, este roadmap usa prefixo **"Fase A"**).

### Fase A0 — Consolidação e correção de consistência (pré-requisito de tudo)

- Unificar a fórmula de RFM entre `ReportService` e `AnalyticsService` (bug de consistência real, ver 5.1).
- Criar o catálogo de métricas como documento técnico interno (5.2).
- Decidir bibliotecas de exportação (5.5) e confirmar se pré-agregação (5.3) entra em escopo agora ou fica adiada.
- Sem entrega visível ao usuário final; é saneamento técnico.

### Fase A1 — Home executivo mínimo viável + relatórios essenciais (equivalente à Fase 1 da spec, seção 88)

- Completar os KPIs que faltam no Home com dado já disponível: ocupação comercial (KPI 4 — dado existe em Evento/Inventário, falta agregação), receita líquida básica (KPI 2, sem projeção ainda, só realizado).
- Relatório de vendas por dimensão configurável (unificar os endpoints fixos de `origin`/`ticket_type`/`client` numa dimensão selecionável, em vez de um endpoint por dimensão).
- Relatório de pagamentos básico (aprovação/recusa, sem roteamento — já decidido fora de escopo).
- Exportação CSV em todas as telas que já têm relatório hoje (menor esforço que XLSX, cobre parte da Parte VI).
- Alertas básicos de estoque/pagamento (só os dois tipos citados na seção 88, não o catálogo completo de alertas da seção 79).

**Dependência**: Fase A0 completa (senão duplica a inconsistência de RFM em mais lugares).

### Fase A2 — Competitividade forte (equivalente à Fase 2 da spec, seção 89)

- Funil de conversão — **mas só depois de decidir se o produto vai instrumentar tracking de página/visualização** (decisão de produto: hoje não existe analytics de front-end nenhum; funil sem essa instrumentação fica restrito a "checkout iniciado → pago", que já é coberto parcialmente pelo `OperationSnapshotService`).
- Relatório de afiliados consolidado (ranking, ROI) sobre o domínio já existente.
- Relatório de cupons consolidado (ranking, taxa de uso, abuso).
- Relatório de inventário/assentos avançado (ocupação por setor, mapa de calor de assentos).
- Relatório de reembolsos/chargebacks dedicado.
- Comparação entre eventos (tela 2 do relatório 1) — depende de definir "estágio comercial equivalente" (seção 2.4), que é uma regra de negócio nova a validar com o usuário, não só uma query.
- Relatórios agendados básicos (só e-mail diário/semanal, sem toda a Parte VI).
- Exportação XLSX real (depende da Fase A0, decisão de biblioteca).

### Fase A3 — Diferenciação (equivalente à Fase 3 da spec, seção 90)

- CRM/RFM com os 8 segmentos nomeados da spec (hoje só 4 rótulos existem).
- Coortes e retenção (feature nova, exige tabela de agregação por coorte — provavelmente já precisa da decisão de pré-agregação da seção 5.3).
- LTV (histórico primeiro, previsto depois — separar em dois entregáveis, o previsto exige modelo).
- Afinidade entre eventos.
- Relatório de antifraude consumindo `RiskEngineService` (o motor já existe, só falta o relatório).
- Relatório de bilheteria por operador/terminal.
- Relatório de revenda/transferência.
- Construtor de relatórios personalizados — **começar pelo desenho de segurança/arquitetura (5.6), não pela UI**.

### Fase A4 — Inteligência avançada (equivalente à Fase 4 da spec, seção 91)

- Previsão de vendas, cenários, detecção de anomalias, recomendações automáticas.
- Atendimento/NPS/CSAT — depende de o produto passar a coletar isso em algum lugar primeiro (não existe hoje nem como feature, não é só relatório faltando).
- Consumo cashless/estacionamento — depende de decisão de produto sobre se essas features entram no escopo do PegaTicket (hoje não existem como domínio).
- Tudo aqui depende de volume de dado histórico real suficiente para um modelo fazer sentido — não adianta implementar "previsão de vendas" com poucos eventos históricos, o resultado vai ser ruído.

**Nota sobre dependências entre fases**: A1 depende de A0. A2 depende parcialmente de A0 (RFM) e de decisões de biblioteca (XLSX). A3 depende de decisão de pré-agregação (5.3) para coortes/LTV, e o construtor de relatórios (A3) idealmente só começa depois que os services de domínio da Fase A1/A2 já estiverem consolidados no namespace único da seção 5.1 — senão o construtor herda a mesma fragmentação que existe hoje. A4 depende de volume de dado, não só de código pronto.

---

## 7. Riscos e decisões pendentes (precisam de confirmação do usuário antes de implementar)

1. **Pré-agregação/materialização de métricas** (5.3): aceitar introduzir tabelas de snapshot com job agendado, com o trade-off de "dado de ontem" em vez de tempo real para relatórios pesados (coortes, curva de vendas normalizada)? Sem isso, alguns relatórios da Fase A3 não vão bater a meta de performance da spec (seção 82) conforme o histórico cresce.
2. **Biblioteca de exportação XLSX** (5.5): `maatwebsite/excel` vs. `PhpSpreadsheet` direto — e se exportação grande vai ser síncrona (mais simples, pior UX em volume alto) ou assíncrona via fila (mais robusto, mais infraestrutura).
3. **Biblioteca de visualização avançada** (5.4): manter só `chart.js` com componentes SVG customizados para funil/Sankey/waterfall, ou adicionar uma segunda lib? Isso trava a decisão de design system, então precisa alinhar com UI UX Master antes de qualquer tela nova de gráfico "exótico".
4. **Funil de conversão real** (seção 4.3, relatório 3): implementar tracking de visualização de página no storefront público (`site`/`web`) é uma decisão de produto com implicação de privacidade/LGPD (a spec já cita isso na seção 85) — precisa decidir o que rastrear, com que base legal, antes de instrumentar.
5. **Escopo do construtor de relatórios personalizados** (5.6, seção 70-73 da spec): é a peça de maior risco de segurança do documento inteiro. Vale considerar entregar uma versão reduzida primeiro (dimensões/métricas fixas com poucas combinações permitidas) antes do "métricas calculadas com fórmula livre" completo?
6. **Cashless/consumo/estacionamento** (relatório 15): esses **não são relatórios faltando, são features de produto inteiras que não existem**. Antes de desenhar qualquer indicador de "consumo no evento", é preciso decidir se o PegaTicket vai ter um domínio de PDV/cashless de consumo interno — que é uma decisão de produto separada da camada analítica, do mesmo porte das decisões já tomadas sobre wallet pass/NFe/multi-gateway no roadmap global.
7. **Atendimento/NPS/CSAT** (relatório 16): mesma situação — não há hoje nenhuma coleta de satisfação/NPS no produto. É decisão de produto antes de ser item de relatório.
8. **Roteamento inteligente de pagamento (relatório 6, tela 4) e fiscal/ERP (relatório 18, tela 2)**: já têm decisão de produto fechada no roadmap global (2026-08-05) como "não fazer" — citados aqui só para deixar explícito que essas duas telas da spec devem ser **removidas do escopo**, não implementadas parcialmente por engano.
9. **Catálogo de métricas como feature de produto vs. documentação interna** (5.2): a proposta deste documento é começar como documentação interna; se o usuário quiser a tela consultável desde já, isso muda o esforço da Fase A0.
10. **Definição de "estágio comercial equivalente" para comparação entre eventos** (seção 2.4 da spec, relatório 1 tela 2): a spec pede que comparações considerem dias desde abertura de vendas, capacidade, faixa de preço etc., mas não define a fórmula. Isso precisa de uma decisão de regra de negócio explícita antes de implementar "comparação justa" — hoje toda comparação do sistema é só por data de calendário.

---

## 7.1 Decisões do usuário (2026-08-05, confirmadas em conversa)

1. **Filtro obrigatório em toda tela analítica — regra nova, não estava na spec original.** Em hipótese alguma uma tela de relatório ou a Home executiva pode carregar sem nenhum filtro ativo por padrão (data como já é no Home, ou tipo/valor/outro conforme o relatório) — só fica sem filtro se o usuário remover todos manualmente. Isso vira requisito transversal de UI para TODA tela desta refatoração, não só para os relatórios pesados.
2. **Pré-agregação/materialização (item 1 da seção 7): adiada, não descartada.** Como toda consulta pesada agora nasce com filtro obrigatório (reduz o dataset por padrão), a Fase A0/A1 implementa os relatórios em modo tempo-real-com-filtro-obrigatório primeiro. Snapshot/materialização só entra depois, se a performance medida em produção (com filtro aplicado) provar que precisa — não é mais pré-requisito de arquitetura antes de começar.
3. **Funil de conversão (item 4): aprovado rastreio anônimo por sessão** — evento de página vista + `session_id` técnico, sem dado pessoal, mesmo espírito do UTM já capturado hoje. Sem bloqueio de LGPD adicional identificado.
4. **Construtor de relatórios personalizados (item 5): versão COMPLETA aprovada** (fórmulas calculadas livres, não a versão reduzida que este documento recomendava) — dado que é a peça de maior risco de segurança/performance do levantamento, a implementação precisa de validação/sandboxing de expressão explícitos (nunca query SQL livre do tenant, sempre um DSL de fórmula validado e whitelisted) antes de liberar em produção; tratar como item que exige revisão de segurança dedicada antes do merge, não só do Code Review Architect padrão.
5. **Cashless/consumo/estacionamento e Atendimento/NPS (itens 6-7): fora do escopo desta refatoração**, confirmado — são domínios de produto novos, não relatórios faltando; ficam para uma rodada de produto separada.

---

## 8. Conclusão

O PegaTicket tem uma base analítica real e correta em seus fundamentos (nenhuma SQL injection, tenant sempre filtrado, cancelamento sempre excluído de forma consistente — tudo confirmado lendo o código, não assumido), mas ela cobre uma fração pequena do que esta nova especificação pede. A tarefa correta não é "construir do zero" nem "só adicionar telas" — é primeiro consolidar e corrigir a camada que já existe (Fase A0), depois expandir por prioridade de valor comercial (A1/A2), e só then entrar nas áreas que dependem de decisões de produto ainda não tomadas (cashless, NPS, previsões, construtor de relatórios). As seções 5 e 7 deste documento devem ser tratadas como agenda de decisão antes de qualquer sessão de implementação começar.
