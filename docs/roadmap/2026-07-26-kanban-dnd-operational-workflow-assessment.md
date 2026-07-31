# Levantamento: Fluxo tipo Trello com Drag and Drop

Data: 26 de julho de 2026

## Objetivo

Levantar o impacto para transformar cards operacionais em colunas estilo Trello com drag and drop, persistindo:

- usuário que moveu;
- data/hora da movimentação;
- status de origem e destino;
- motivo obrigatório quando a movimentação representar cancelamento.

O comportamento deve ser reaproveitado em todas as telas com dinâmica equivalente.

## Resumo executivo

O sistema já possui base forte para transições de estado com auditoria, mas ainda não possui um modelo unificado de “movimentação de card” como entidade operacional própria.

Hoje existem duas famílias principais de fluxo:

1. Pedidos
- `Order`
- transições via endpoints dedicados (`approve`, `reject`, `dispatch`, `deliver`, `pay`, `cancel`, `approve-cancellation`, `reject-cancellation`)
- auditoria por eventos/listeners

2. Balcão / KDS
- `ComandaItem`
- transições de preparo (`queued → sent_to_station → preparing → ready → delivered_to_table`, além de `cancelled`)
- auditoria por eventos/listeners

Conclusão:

- é viável implementar UX estilo Trello no frontend;
- não é recomendável persistir isso apenas com `AuditLog`;
- o backend precisa ganhar um histórico operacional padronizado de transições, especialmente para consulta por tela, timeline e analytics;
- a primeira implementação deve cobrir pedidos e KDS, porque são os fluxos com colunas reais hoje;
- outras telas com ações em card podem entrar depois se seguirem a mesma máquina de estados.

## Estado atual mapeado

### 1. Central de operação

Arquivo principal:
- [web/src/pages/Order/OrderListPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/OrderListPage.tsx)

Situação atual:
- já existe visão por filas:
  - `approval`
  - `production`
  - `dispatch`
  - `financial_pending`
- já existem cards operacionais no topo;
- já existem ações rápidas contextuais;
- já existe priorização visual;
- já existe coordenação com rotas e cobrança do dia.

Limitação atual:
- ainda não há drag and drop;
- as transições continuam acionadas por botão;
- não há coluna visual contínua estilo kanban;
- não existe histórico específico de “movi da coluna A para B”.

### 2. Pedidos da loja

Arquivo principal:
- [web/src/pages/Order/StorefrontOrderManagementPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/StorefrontOrderManagementPage.tsx)

Situação atual:
- listagem operacional separada;
- ações via modal:
  - aceitar
  - recusar
  - cancelar
  - saiu para entrega
  - entregar
  - concluir
- já possui caso especial de cancelamento solicitado.

Limitação atual:
- não é kanban;
- depende do modal para executar quase tudo;
- precisa convergir com a central para não duplicar lógica visual.

### 3. KDS do Balcão

Arquivo principal:
- [web/src/pages/Balcao/BalcaoKdsPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Balcao/BalcaoKdsPage.tsx)

Situação atual:
- já é a tela mais próxima de um kanban;
- possui colunas reais:
  - `sent_to_station`
  - `preparing`
  - `ready`
- avanço por botão grande;
- polling de 30s;
- auditoria já existe no backend.

Limitação atual:
- não usa drag and drop;
- não mostra histórico explícito por movimento;
- cancelamento continua fora da coluna principal.

### 4. Comanda do Balcão

Arquivo principal:
- [web/src/pages/Balcao/BalcaoComandaPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Balcao/BalcaoComandaPage.tsx)

Situação atual:
- lista itens por status;
- permite avançar preparo e cancelar;
- já exige motivo em cancelamento.

Limitação atual:
- é uma tela de detalhe, não um board;
- deve consumir o mesmo motor de transição/histórico do KDS, mas não precisa ser a primeira tela a virar Trello.

### 5. Marketplace / integrações

Arquivo relevante:
- [web/src/pages/Settings/IntegrationsPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Settings/IntegrationsPage.tsx)

Situação atual:
- há ações operacionais em cards/listas de pedidos externos.

Limitação atual:
- o fluxo depende do parceiro;
- não é o melhor primeiro alvo para DnD;
- recomenda-se deixar para fase posterior, depois da consolidação do fluxo canônico interno.

## Backend já existente

### Pedidos

Arquivo principal:
- [api/app/Services/Order/OrderService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Order/OrderService.php)

O que já existe:
- máquina de estados distribuída por métodos dedicados;
- eventos como:
  - `OrderApproved`
  - `OrderCancelled`
  - `OrderOutForDelivery`
  - `OrderDelivered`
  - `OrderPaid`
  - `OrderRejected`
  - `OrderUndelivered`
  - `OrderUndispatched`
  - `OrderCancellationRequested`
  - `OrderCancellationApproved`
  - `OrderCancellationRejected`
- listeners de auditoria já escrevem `AuditLog`.

Ponto importante:
- o sistema já grava ator e horário implicitamente pela combinação de evento + `AuditLog`;
- isso é suficiente para auditoria técnica;
- não é suficiente como histórico operacional de board, porque:
  - não existe uma tabela única consultável por “movimentações do card”;
  - não há modelo uniforme `from_column -> to_column`;
  - a UI teria que inferir histórico a partir de múltiplos eventos heterogêneos.

### Balcão / KDS

Arquivo principal:
- [api/app/Services/Balcao/ComandaItemService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Balcao/ComandaItemService.php)

O que já existe:
- máquina de estados formal;
- timestamps por etapa:
  - `sent_to_station_at`
  - `preparing_at`
  - `ready_at`
  - `delivered_at`
  - `cancelled_at`
- motivo de cancelamento:
  - `cancelled_reason`
- eventos e listeners específicos de auditoria.

Ponto importante:
- Balcão está mais maduro que Pedidos para histórico de etapa;
- mesmo assim, ainda falta uma trilha operacional padronizada por movimento de board.

## Gaps identificados

### 1. Falta uma entidade unificada de movimentação operacional

Hoje:
- pedidos usam eventos heterogêneos;
- balcão usa timestamps e eventos próprios;
- a UI não tem uma fonte única para montar timeline de arraste.

Necessidade:
- criar uma tabela do tipo `workflow_transition_logs` ou similar.

Campos mínimos recomendados:
- `uuid`
- `tenant_id`
- `workflow_type`
  - `order`
  - `comanda_item`
- `entity_id`
- `entity_uuid`
- `from_stage`
- `to_stage`
- `transition_type`
  - `move`
  - `cancel`
  - `undo`
  - `system_sync`
- `reason`
- `moved_by_user_id`
- `moved_by_user_uuid` opcional no resource
- `moved_at`
- `meta` JSON
  - origem visual
  - tela
  - canal
  - flags extras

### 2. As transições de Pedido não têm timestamps por etapa suficientemente claros

Hoje:
- há `out_for_delivery_at`, `delivered_at`, `paid_at`;
- mas não existe timestamp explícito de “entrou em produção” ou “foi aprovado e caiu no board de produção”.

Necessidade:
- ou derivar isso do log operacional novo;
- ou acrescentar colunas específicas quando a etapa tiver peso analítico alto.

Recomendação:
- para reduzir impacto, usar o log operacional como fonte oficial da entrada em cada coluna.

### 3. Cancelamento precisa ter comportamento especial no DnD

Regras desejadas:
- arrastar para uma coluna terminal de cancelamento não pode salvar direto;
- deve abrir modal de confirmação;
- motivo obrigatório;
- só depois disso persistir;
- cancelar precisa continuar respeitando as regras já existentes do domínio.

### 4. Undo / retorno de coluna precisa respeitar regra de negócio

Exemplos:
- pedido da loja pode voltar de `dispatch` para `production`;
- pedido entregue pode voltar para `dispatch` em alguns casos;
- item do KDS tem transições válidas próprias.

Conclusão:
- o board não pode ser livre;
- cada coluna precisa conhecer destinos permitidos por tipo de entidade.

### 5. Polling simples não é ideal para DnD multiusuário

Hoje:
- KDS e loja usam polling.

Problema:
- com múltiplos operadores arrastando cards, o risco de conflito visual aumenta.

Recomendação:
- fase 1 pode manter polling + optimistic UI com refetch;
- fase 2 deve avaliar broadcast/websocket/SSE para telas mais sensíveis.

## Telas candidatas para aplicação

### Fase 1

1. `Central de operação`
- [web/src/pages/Order/OrderListPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/OrderListPage.tsx)

2. `KDS do Balcão`
- [web/src/pages/Balcao/BalcaoKdsPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Balcao/BalcaoKdsPage.tsx)

### Fase 2

3. `Pedidos da loja`
- [web/src/pages/Order/StorefrontOrderManagementPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Order/StorefrontOrderManagementPage.tsx)

4. `Comanda do Balcão`
- [web/src/pages/Balcao/BalcaoComandaPage.tsx](/home/mtsdrf/workspace/pegaticket-saas/web/src/pages/Balcao/BalcaoComandaPage.tsx)

### Fase 3

5. Fluxos externos que herdem a mesma máquina operacional
- marketplace / integrações
- eventuais boards administrativos futuros

## Proposta de arquitetura

### Frontend

Criar um motor compartilhado de board, não tela a tela.

Componentes sugeridos:
- `WorkflowBoard`
- `WorkflowColumn`
- `WorkflowCard`
- `WorkflowMoveConfirmDialog`
- `WorkflowCancelReasonDialog`
- `useWorkflowBoard`

Contrato mínimo do card:
- `id`
- `title`
- `subtitle`
- `status`
- `priority`
- `timestamps`
- `badges`
- `allowedTransitions`
- `dragPayload`

Biblioteca recomendada:
- `@dnd-kit/core`
- `@dnd-kit/sortable`

Motivo:
- mais flexível e moderna;
- melhor para colunas customizadas e regras de drop;
- mais segura para mobile do que depender de HTML5 drag puro.

### Backend

Criar uma camada compartilhada de transição operacional:

Serviços sugeridos:
- `WorkflowTransitionLogger`
- `OrderWorkflowService`
- `ComandaItemWorkflowService`

Objetivo:
- centralizar a gravação do log operacional;
- manter `OrderService` e `ComandaItemService` como donos da regra de negócio;
- registrar `from_stage`, `to_stage`, ator, horário e motivo em toda transição.

## Regras de persistência

Cada movimentação válida deve salvar:

1. quem moveu
- usuário autenticado atual

2. quando moveu
- timestamp do servidor

3. de onde para onde
- coluna origem
- coluna destino

4. por que moveu
- obrigatório em cancelamento
- opcional em outras transições, se quiser ampliar depois

5. contexto
- tela de origem
- tipo da entidade
- origem do pedido (`storefront`, `staff`, `pdv`, `counter`, `ifood`) quando aplicável

## Regras funcionais por domínio

### Pedido

Colunas candidatas:
- `Aguardando aprovação`
- `Produção`
- `Expedição`
- `Financeiro pendente`
- `Cancelado` opcional como coluna terminal visual

Mapeamento não-livre:
- `approval -> production`
- `production -> dispatch`
- `dispatch -> financial_pending` via entrega sem pagamento
- `dispatch -> done` implícito quando entregue e pago
- `qualquer não terminal -> cancelled` com motivo e validação do backend

### KDS

Colunas:
- `Enviado à estação`
- `Em preparo`
- `Pronto`
- `Entregue`
- `Cancelado`

Mapeamento:
- `sent_to_station -> preparing`
- `preparing -> ready`
- `ready -> delivered_to_table`
- `qualquer não terminal -> cancelled` com motivo

## Riscos

### 1. Duplicar lógica entre botão e drag

Mitigação:
- drag deve chamar os mesmos endpoints/métodos já usados pelos botões;
- o board muda a UX, não a regra.

### 2. Conflito multiusuário

Mitigação:
- optimistic update com rollback;
- refetch após sucesso;
- travar card em processamento;
- mostrar toast de conflito se o status mudou antes da confirmação.

### 3. Cancelamento indevido por arraste acidental

Mitigação:
- nunca cancelar por drop direto;
- abrir confirmação + motivo.

### 4. Inconsistência entre Central e telas especializadas

Mitigação:
- compartilhar o mesmo motor de board;
- mesma resolução de colunas;
- mesma função de `allowedTransitions`.

## Plano de execução recomendado

### Etapa 1. Modelo e auditoria

- criar tabela de log operacional de transição;
- criar resources/queries para consultar timeline;
- ligar `OrderService` e `ComandaItemService` ao logger.

### Etapa 2. Motor de board reutilizável

- criar componentes e hook compartilhados;
- suportar drag, bloqueio, confirmação e rollback visual.

### Etapa 3. Central de operação

- trocar cards/topo por board real por colunas;
- manter resumo e exceções no topo;
- persistir toda movimentação.

### Etapa 4. KDS

- migrar colunas do KDS para drag and drop;
- manter alto contraste e interação touch-friendly.

### Etapa 5. Pedidos da loja / Comanda

- aplicar somente onde fizer sentido operacional;
- evitar duplicar experiência se a central já absorver a maior parte do fluxo.

## Recomendação final

Não implementar o drag and drop diretamente nas telas atuais sem antes criar:

1. log operacional unificado de transição;
2. motor visual compartilhado de board;
3. mapa formal de transições permitidas por domínio.

Se isso for respeitado, o comportamento tipo Trello pode ser aplicado com segurança e reaproveitamento real em todo o sistema.
