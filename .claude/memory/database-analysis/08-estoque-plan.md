# Módulo de Estoque — Plano (Fase 4, Parte 1) + integração com Venda (Fase 5, Parte 2)

Venda do usuário (2026-07-09): módulo completo de gerenciamento de estoque **antes** do Venda, e o Venda já nasce integrado com estoque. Usuário forneceu uma lista de 300 capacidades possíveis de um sistema de estoque, com a instrução explícita: **não precisa ter tudo agora, mas precisa estar tudo no radar** (nada pode ser esquecido/perdido, mesmo o que não entra no MVP).

Este documento mapeia os 300 itens em **MVP (Parte 1, implementar agora)** vs **Radar (documentado, propositalmente adiado)**, e define a arquitetura de entidades.

Sem tabela do legado para reaproveitar — o sistema legado (`/home/mtsdrf/workspace/pegaticket/`) **não tinha nenhum controle de estoque** (só `produto.quantidade`, um campo solto sem movimentação/auditoria). Este módulo é 100% novo, sem precedente a replicar — todas as decisões abaixo são de produto/arquitetura, não engenharia reversa.

## Entidades (MVP — Fase 4, Parte 1)

Nomenclatura em inglês, mesma decisão já aplicada a `Client`/`Product`.

### 1. Extensão de `Product` (colunas novas na tabela existente, sem entidade nova)
- `sku` (string nullable, único por tenant quando presente) — item 2
- `barcode` (string nullable) — item 3
- `brand` (string nullable, campo simples) — item 9 (radar: entidade `Brand` dedicada)
- `unit` (string, default `'un'`) — item 10 (radar: `UnitOfMeasure` + conversão, itens 162-165)
- `is_lot_controlled`, `is_expiry_controlled`, `is_serial_controlled` (boolean, default `false`) — itens 12-14. **Só as flags nascem agora** — a lógica de lote/validade/série em si é radar (itens 85-97), reservado para não exigir migration destrutiva depois.
- `min_stock`, `max_stock`, `reorder_point`, `reorder_qty` (integer nullable) — itens 27-30
- `last_purchase_cost` (decimal nullable, setável manualmente — sem módulo de compras ainda) — parte do item 99
- Item 11 (ativo/inativo) já coberto por `is_available` existente.
- **Item 7/8 (categoria/subcategoria) já coberto** pela hierarquia existente `ProductCategory`→`ProductType` — não cria nada novo.
- **Item 15 (variações: tamanho/cor/voltagem) fica de fora do MVP** — precisaria de uma entidade `ProductVariant` própria (SKU filho por combinação de atributo), mudança estrutural grande. Radar explícito, não uma flag solta como lote/validade.

### 2. `StockLocation` (`stock_locations`, tenant-scoped) — itens 16-19
- `name`, `type` (string livre: `deposito`/`filial`/`loja`, sem hierarquia rígida no MVP), `address` (string nullable, "endereço interno" simples — radar: estrutura formal de rua/corredor/prateleira), `is_default` (boolean, um por tenant), `is_active`.
- Ao criar o primeiro tenant/primeira vez que o módulo de estoque for usado, auto-criar uma location padrão ("Depósito Principal") — usuário não é obrigado a configurar local antes de conseguir vender.
- Radar: filial/corredor com hierarquia própria (item 17-19 em detalhe), multi-empresa formal (itens 155-161 — hoje resolvido via `tenant_id`, suficiente para o modelo de negócio atual).

### 3. `StockBalance` (`stock_balances`, tenant-scoped, único por `product_id`+`location_id`) — itens 20-26
- `quantity_on_hand`, `quantity_reserved`, `quantity_blocked` (integer, default 0).
- `quantity_available` = `on_hand - reserved - blocked`, **computado**, não persistido (evita saldo duplicado divergente).
- Item 26 (em trânsito) fica de fora do MVP — transferência é atômica (debita origem + credita destino na mesma transação), sem estado intermediário "em trânsito". Radar: workflow completo de transferência (itens 188-201).

### 4. `StockMovement` (`stock_movements`, tenant-scoped) — itens 31-58, ledger append-only (nunca update/delete, só nova movimentação de estorno)
- `product_id`, `location_id`, `destination_location_id` (nullable, só em transferência).
- `type`: enum `entry`,`exit`,`adjustment_positive`,`adjustment_negative`,`transfer`,`return`,`loss`,`block`,`unblock`,`reserve`,`reserve_cancel`,`reversal` — cobre itens 31-37, 39-43 (item 38 "baixa por vencimento" fica de fora, depende de controle de validade que é radar).
- `quantity`, `balance_before`, `balance_after` — itens 51-53.
- `reason` (string curto), `notes` (text nullable) — itens 48-49.
- `source_type`+`source_id` (polimórfico nullable — vai apontar para `Venda` na Parte 2) — item 50.
- `created_by`/`created_at` já vêm do `BaseModel` — itens 46-47, 65-70 (auditoria já é o padrão do projeto inteiro, nada novo a construir).
- Este ledger sozinho cobre boa parte de "histórico completo de movimentações" (item 44) e é a base de "rastreio do produto" (itens 249-259) — sem precisar de tabela extra, só query filtrada.
- Radar: aprovação de movimentação sensível como workflow multi-etapa (item 56) — MVP é só permissão (`perm:stock,{action}`), sem fluxo de aprovação separado.

### 5. Permissões — `Functionality` `stock`, actions: `read`, `entry`, `exit`, `adjustment`, `transfer`, `block`, `reserve`, `view_costs`, `reverse` — itens 57-64 (nível de ação, não aprovação em múltiplas etapas).

### 6. Reserva de estoque — mecanismo que a Parte 2 (Venda) vai consumir
`reserve`/`reserve_cancel` incrementam/decrementam `quantity_reserved` em `StockBalance`. Ao criar um Venda: reserva o estoque dos itens. Ao marcar entregue: converte reserva em saída real (`exit`). Ao cancelar: `reserve_cancel` (se ainda não convertida) ou `reversal` (se já tinha saído).

## Fora do MVP — radar explícito (não esquecido, só adiado)

Agrupado por tema, com os números da lista original do usuário entre parênteses:

- **Inventário físico completo** (72-84): abertura, congelamento de saldo, contagem por produto/local/lote, divergência, aprovação, ajuste automático, histórico de inventários.
- **Lote/validade/série** (85-97): datas de fabricação/validade, FEFO/FIFO, número de série, rastreabilidade dedicada. As flags (`is_lot_controlled` etc.) já nascem no MVP para não exigir migration retroativa, mas o comportamento não existe ainda.
- **Custeio avançado** (98-105, exceto `last_purchase_cost` manual): custo médio, custo por lote, valorização de estoque, histórico de custo, ajuste de custo.
- **Indicadores avançados** (106-117): abaixo do mínimo/acima do máximo (dá pra calcular via query simples quando venda, mas sem dashboard dedicado ainda), giro, Curva ABC, sugestão de reposição, previsão de ruptura — nascem junto com a Fase de Relatórios/Dashboard (renumerada para Fase 6).
- **Relatórios dedicados de estoque** (118-131): parte da Fase 6 (Relatórios), não da Fase 4.
- **Busca/filtros avançados** (132-140): filtros básicos (categoria, local, status) entram no MVP via `paginate()`, os demais (lote, validade, fornecedor — fornecedor nem existe como entidade ainda) ficam de fora.
- **Código de barras/QR/etiquetas** (141-147): depende de scanner/impressora no frontend, feature própria futura.
- **Importação/exportação em massa** (148-154): radar, útil pra Fase 8 (migração de dados) mas não é dependência dela.
- **Unidade de medida com conversão** (162-165): MVP só tem o campo `unit` solto, sem fator de conversão.
- **Kit/composição de produtos** (166-175): feature própria grande (produto composto, montagem/desmontagem, produção simples) — fora do MVP inteiro.
- **Workflow de transferência com estados** (188-201): criada/enviada/recebida/cancelada, conferência, recebimento parcial — MVP é transferência atômica sem estados intermediários. Item 201 ("baixa automática por venda/venda") **é justamente o core da Parte 2**, não é radar.
- **Integrações externas / e-commerce / marketplace / webhooks** (202-210): sem parceiro de integração definido, não faz sentido construir agora.
- **Notificações** (210-217): estoque baixo, vencimento, etc. — depende de um sistema de notificação que o projeto ainda não tem (nem para outros módulos).
- **Configurações avançadas** (218-229): tipos de ajuste/motivo configuráveis por tela (MVP usa enum fixo no código), regras de bloqueio configuráveis, casas decimais (MVP assume quantidade inteira, igual ao legado), edição retroativa, fechamento de período.
- **Anexos em movimentação** (239-248): fotos de avaria, comprovantes, documentos — radar.
- **Analytics/timeline avançada** (260-267): "quem mais ajustou", "produtos com mais divergência" — depende de inventário físico (radar) e relatórios (Fase 6).
- **Mobile dedicado/PWA offline** (268-278): o `web/` já é mobile-first (restrição de produto existente), mas modo offline com fila de sincronização é feature própria grande, radar.
- **API externa dedicada + rate limit por cliente** (279-289): a API REST interna já cobre consulta/movimentação (é a mesma API do resto do sistema) — uma API pública separada para terceiros é radar.
- **Multiempresa/multifilial formal** (155-161, 290): resolvido hoje via `tenant_id` (já é a unidade de isolamento do SaaS) + `StockLocation` para multi-local dentro do tenant. Hierarquia formal de filial/matriz é radar caso vire requisito real.

Itens de segurança/LGPD/auditoria (290-299) já são cobertos pela arquitetura existente do projeto inteiro (tenant isolation, `BaseModel`, `AuditLog`, RBAC) — nada específico de estoque a construir aí. Item 298 (impedir exclusão de produto com movimentação) segue a mesma filosofia já decidida para `Client`/`Product`: soft-delete livre, sem bloqueio de banco (ver [[architecture-decisions]] → "Exclusão de Cliente/Produto com venda vinculado").

## Regra de execução

Mesma regra do roadmap principal: escopo abaixo precisa de aprovação explícita antes da implementação começar. Ver [[07-implementation-roadmap]].
