---
name: kanban-dnd-workflow-assessment-2026-07-26
description: Levantamento para fluxo tipo Trello com drag and drop nos cards operacionais.
metadata:
  type: feature-analysis
---

# Resumo

Em 26 de julho de 2026 foi feito o levantamento para evoluir cards operacionais para um fluxo estilo Trello com drag and drop, persistindo ator, horário, origem/destino e motivo obrigatório em cancelamento.

# Conclusões principais

- O sistema já possui base forte de transição auditada em `SaleService` e `ComandaItemService`.
- `AuditLog` atual é suficiente para auditoria técnica, mas não é uma fonte ideal para timeline operacional de board.
- A implementação precisa de uma entidade unificada de transição operacional.
- Primeiros alvos reais:
  - `Central de operação`
  - `KDS do Balcão`
- Alvos posteriores:
  - `Vendas da loja`
  - `Comanda do Balcão`

# Decisão recomendada

Antes de implementar o board:

1. criar tabela de log operacional unificada;
2. criar motor compartilhado de board no frontend;
3. formalizar mapa de transições permitidas por tipo de entidade.

# Progresso executado

- Em 26 de julho de 2026 a etapa 1 foi concluída no backend.
- A tabela unificada de transições operacionais foi criada em [api/database/migrations/2026_07_27_000000_create_workflow_transition_logs_table.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/migrations/2026_07_27_000000_create_workflow_transition_logs_table.php).
- O log operacional agora é escrito por [api/app/Listeners/Workflow/WriteWorkflowTransitionLog.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Listeners/Workflow/WriteWorkflowTransitionLog.php) usando [api/app/Services/Workflow/WorkflowTransitionLogger.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Workflow/WorkflowTransitionLogger.php).
- Eventos de venda e balcão foram enriquecidos para carregar `saleId` ou `itemId`, além de `fromStage` e `toStage` quando aplicável, reduzindo inferência frágil no listener.
- Cobertura inicial pronta em [api/tests/Feature/Workflow/WorkflowTransitionLogTest.php](/home/mtsdrf/workspace/pegaticket-saas/api/tests/Feature/Workflow/WorkflowTransitionLogTest.php), validando aprovação de venda e cancelamento de item de comanda com motivo.
- Em 26 de julho de 2026 a etapa 2 também foi iniciada e concluída para os dois primeiros alvos definidos.
- A central de operação em [web/src/pages/Order/SaleListPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/SaleListPage.tsx) agora usa colunas arrastáveis e zonas de ação terminal, preservando botões rápidos e filtros existentes.
- O KDS em [web/src/pages/Balcao/BalcaoKdsPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Balcao/BalcaoKdsPage.tsx) agora usa o mesmo padrão visual/comportamental, com cancelamento exigindo motivo.
- A infraestrutura visual compartilhada do board vive em `web/src/components/workflow/*`, pronta para futura expansão para `Vendas da loja` e outras filas com dinâmica semelhante.
- Em 26 de julho de 2026 a expansão para `Vendas da loja` também foi concluída em [web/src/pages/Order/StorefrontOrderManagementPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/StorefrontOrderManagementPage.tsx), mantendo o modal de gestão detalhada e adicionando arraste operacional por etapas.
- Em 26 de julho de 2026 a timeline visual do histórico operacional por card também foi concluída.
- O backend passou a expor leitura de histórico via:
  - `GET /api/v1/orders/{order}/workflow-transitions`
  - `GET /api/v1/storefront-sales/{order}/workflow-transitions`
  - `GET /api/v1/balcao/comandas/{uuid}/items/{itemUuid}/workflow-transitions`
- O frontend ganhou o diálogo compartilhado [web/src/components/workflow/WorkflowTimelineDialog.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/components/workflow/WorkflowTimelineDialog.tsx), reutilizado na central operacional, nos vendas da loja e no KDS.
- Cada timeline agora mostra quem moveu, quando moveu, de qual etapa para qual etapa e qual motivo foi informado em cancelamentos.
- Em 26 de julho de 2026 o fluxo de marketplace/iFood recebeu tratamento equivalente, mas respeitando seu domínio próprio.
- Em vez de reaproveitar `workflow_transition_logs`, [web/src/pages/Marketplace/MarketplaceOrdersPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Marketplace/MarketplaceOrdersPage.tsx) passou a consolidar `events`, `actions` e `imported_at/internal_order` em uma timeline cronológica única para o venda externo.
- A mesma tela também passou a derivar sinais de SLA operacional a partir de `queue_status`, `latest_event_at`, `last_synced_at`, `raw_updated_at` e `imported_at`, sem exigir mudança de schema nesta fase.
- Em 26 de julho de 2026 essa leitura de SLA do marketplace também subiu para o backend via `operationsSummary`, permitindo cockpit agregado por integração e deixando a tela menos dependente de cálculo isolado por card.
- Próximos candidatos naturais para a mesma infraestrutura: `Vendas iFood/marketplace` com transições compatíveis, visão consolidada por responsável/turno e telemetria de SLA por etapa.

# Documento base

- [docs/roadmap/2026-07-26-kanban-dnd-operational-workflow-assessment.md](/home/mtsdrf/workspace/pegaticket-saas/docs/roadmap/2026-07-26-kanban-dnd-operational-workflow-assessment.md)
- Em 26 de julho de 2026 essa mesma régua operacional também passou a alimentar o dashboard geral.
- [api/app/Services/Report/ReportService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Report/ReportService.php) agora consolida filas internas e exceções de marketplace em `GET /api/v1/reports/operation-health`, e [web/src/pages/Dashboard/DashboardPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Dashboard/DashboardPage.tsx) passou a exibir esse cockpit resumido de saúde da operação.
- Refinamento adicional no mesmo dia: o cockpit de saúde operacional do dashboard passou a virar ponto de entrada da operação, com deep-links para `/vendas?stage=...` e `/vendas-ifood`, mantendo a mesma semântica de etapa usada no board.
- Expansão seguinte no mesmo dia: a navegação contextual passou a cobrir também a fila especializada da loja e a operação de marketplace, com `Vendas da Loja` aceitando `?stage=` para foco do board e `Vendas iFood` aceitando `?focus=`/`?queue_status=` para recortes rápidos de criticidade.
- Em 27 de julho de 2026 esse fluxo contextual foi concluído com sinalização visual de origem: as filas abertas a partir do dashboard agora mostram no topo qual contexto operacional motivou a navegação.
