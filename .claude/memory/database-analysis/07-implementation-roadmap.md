# Database Analysis — Implementation Roadmap

Plano em fases para levar o que foi descoberto em `dump_base.sql` + código-fonte real do legado (`/home/mtsdrf/workspace/maskats/`, Laravel 8 + React 17 — ver [[01-schema-overview]] a [[06-business-rules]]) para o backend Laravel (`api/`) + frontend React (`web/`) já existentes. **Atualização 2026-07-09: Fases 1 a 7 (todo o backend) concluídas — ver [[architecture-decisions]] para o detalhe de cada módulo. O que resta é frontend (`web/`) e a Fase 8 (migração de dados).**

> 2026-07-06: acesso ao código-fonte completo do legado resolveu a maioria das dúvidas de negócio que antes eram só inferência de schema. Restam poucas decisões arquiteturais genuínas (ver seção de dúvidas, reduzida).

## Ponto de partida: o que já existe e não precisa ser recriado

O novo sistema (`api/`) já tem, mais robusto que o legado:
- Autenticação JWT + multi-tenant N:N (`tenants`/`tenant_users`/`tenant_roles`) — **superior** ao 1 usuário : 1 estabelecimento do legado.
- RBAC granular (`Group`/`GroupPermission`/`Functionality`/`Action`) — **superior** ao `tipo_usuario` único e global do legado.
- Auditoria real (`AuditLog` via Events/Listeners) — **superior** à tabela `json` do legado.
- Soft delete real (`deleted_at`) + `created_by`/`updated_by`/`deleted_by` — **superior** ao `ativo` booleano do legado.
- Padrão de API/Resource/Service/Repository já estabelecido (`.claude/memory/api-patterns.md`, `.claude/skills/laravel-api.md`).

**Conclusão: Fase 1 (base técnica) do processo padrão do agente já está pronta.** O trabalho real começa na Fase 2 (CRUDs), adaptando os módulos de negócio do legado (Cliente, Produto, Pedido) ao padrão arquitetural já existente — não recriando auth/permissão/auditoria do zero.

## Fase 2 — CRUDs base (cadastros simples/domínio)
- ✅ Categoria de Cliente (`ClientCategory`, tenant-scoped, CRUD completo) — implementado 2026-07-06.
- ✅ Categoria de Produto / Tipo de Produto (`ProductCategory`, `ProductType`, tenant-scoped, CRUD completo, `ProductType` valida `product_category_uuid` pertencente ao mesmo tenant) — implementado 2026-07-06.
- ✅ Revisão de segurança (Code Review Architect) encontrou IDOR cross-tenant real em update/destroy dos 3 módulos + no baseline `TenantRole` — corrigido no mesmo dia (guard `assertBelongsToCurrentTenant`), com testes de regressão. Também corrigido: N+1 em `ProductTypeService::paginate`, 500 em nome duplicado, bug de `insertGetId` em `GroupPermissionsTest`. Detalhe completo em [[architecture-decisions]]. 125 testes passando.
- ✅ Localização (`Estado`/`Cidade`/`Bairro`, **globais** sem `tenant_id`, compartilhados entre tenants — decisão tomada e implementada 2026-07-06, ver [[architecture-decisions]]) + `Endereco` (tenant-scoped, referencia a geografia global, valida cadeia Estado→Cidade→Bairro consistente via `LocationChainException`). CRUD completo, sem seed de dados reais do IBGE (fora de escopo por enquanto).
- ✅ `DiaIdeal`/`PeriodoIdeal` (tenant-scoped, mesmo padrão simples de `ClientCategory`) — implementado 2026-07-06.
- Guard `assertBelongsToCurrentTenant` aplicado em `Endereco`/`DiaIdeal`/`PeriodoIdeal` (não se aplica a `Estado`/`Cidade`/`Bairro`, que são globais). 159 testes passando.

## Fase 3 — CRUDs relacionais ✅ concluída (2026-07-09)
- ✅ Cliente → `Client` (com endereço criado inline, dia/período ideal, categorias N:N) — implementado 2026-07-08, revisado, corrigido.
- ✅ Produto → `Product` (com tipo/categoria derivada, imagem em disco local do servidor) — implementado 2026-07-09, revisado sem achados críticos/médios.
- 188 testes passando no total. Ver [[architecture-decisions]] para detalhe de cada módulo.

## Fase 4 — Estoque, Parte 1 ✅ concluída (2026-07-09, inserida antes de Pedido)
Pedido explícito do usuário: módulo de estoque completo (estrutura) antes do Pedido existir, para o Pedido já nascer integrado. Sem precedente no legado (que não tinha controle de estoque, só `produto.quantidade` solto) — 100% desenho novo. Escopo completo (MVP + tudo que fica "no radar" das 300 capacidades levantadas pelo usuário) em [[08-estoque-plan]], não duplicado aqui.
- `StockLocation` (locais de estoque, tenant-scoped, com local padrão auto-criado).
- Extensão de `Product`: `sku`, `barcode`, `brand`, `unit`, flags `is_lot_controlled`/`is_expiry_controlled`/`is_serial_controlled`, `min_stock`/`max_stock`/`reorder_point`/`reorder_qty`, `last_purchase_cost`.
- `StockBalance` (saldo por produto+local: on_hand/reserved/blocked, disponível computado).
- `StockMovement` (ledger append-only: entrada/saída/ajuste/transferência/devolução/perda/bloqueio/reserva/estorno).
- Permissões dedicadas (`stock.read/entry/exit/adjustment/transfer/block/reserve/view_costs/reverse`).
- Mecanismo de reserva (`reserve`/`reserve_cancel`) que a Fase 5 vai consumir.
- Fora do MVP (documentado, não esquecido): inventário físico, lote/validade/série (comportamento — as flags já nascem agora), custeio avançado, kit/composição, código de barras/etiquetas, importação em massa, workflow de transferência com estados, integrações externas, notificações. Ver [[08-estoque-plan]] para a lista completa.

## Fase 5 — Pedido, Parte 2 ✅ concluída (2026-07-09, integrado com Estoque desde o início)
- Pedido + itens (`pedido_produto`, snapshot de preço) + parcelas (`pedido_parcela`), transacional (mesmo padrão do legado).
- Ações dedicadas `marcar-entregue`/`marcar-pago` (confirmado pelo código-fonte, não é `update` genérico).
- Vencimento de parcela: configurável via `.env` (`PARCELA_VENCIMENTO_DIA`, default 10), com rollover pro próximo dia válido quando o dia não existir no mês — decisão e fórmula em [[architecture-decisions]].
- Cascata de quitação: última parcela paga marca o pedido inteiro como pago+entregue (regra confirmada no legado, replicar).
- Exclusão de pedido **não existe** (confirmado) — só cancelamento.
- **Cancelamento de pedido**: feature nova desenhada do zero (sem precedente legado), regra completa em [[architecture-decisions]] — bloqueado se houver parcela paga, pedido inteiro (não item a item), reverte reserva/saída de estoque via `StockMovement`.
- **Integração com estoque desde a criação**: criar pedido reserva estoque dos itens (`StockBalance.quantity_reserved`); marcar entregue converte reserva em saída real; cancelar reverte a reserva/saída. Depende da Fase 4 estar pronta.

## Fase 6 — Relatórios e dashboards ✅ indicadores/gráficos/PDF de Pedido+Cliente concluídos (2026-07-09)
- ✅ Indicadores e gráficos **confirmados pelo código-fonte** implementados: `ReportService`/`ReportController` (`GET reports/indicators`, `GET reports/charts`, `GET reports/orders`, `GET reports/clients`, `POST reports/orders/pdf`, `POST reports/clients/pdf`). Pedido cancelado nunca conta (regra nova do sistema, sem precedente no legado). Detalhe completo em [[architecture-decisions]].
- ✅ Exportação em PDF (pedidos e clientes) via `barryvdh/laravel-dompdf` v3.1 instalado e funcionando, views `resources/views/reports/{orders,clients}-pdf.blade.php`.
- ✅ Filtros reescritos com Query Builder/binding — nenhuma concatenação de string, ao contrário do legado.
- 250 testes passando no total (236 anteriores + 14 novos de `ReportTest`).
- Ainda em aberto (não implementado nesta rodada, radar para o futuro): relatório de inadimplência dedicado (não existe pronto no legado, seria feature nova baseada no `due_date` já confirmado) e relatórios de estoque (posição, movimentações, produtos abaixo do mínimo, produtos parados) — parte do radar de [[08-estoque-plan]].

## Fase 7 — Segurança e permissões ✅ concluída (2026-07-09)
- ✅ Mapear `tipo_usuario` legado para o RBAC novo (`TenantRole`) — já estava implementado desde a Fase 1 (`TenantService::create()` provisiona role "Owner" automático com todas as permissões); não era trabalho novo.
- ✅ Definir `Functionality`/`Action` para cada novo módulo — já vinha sendo feito incrementalmente em cada fase (2 a 6), não uma tarefa separada.
- ✅ Gap real encontrado e fechado: tenants criados antes de um módulo novo existir não ganhavam acesso automático a ele. Comando `php artisan tenants:sync-permissions` criado (idempotente, com `--tenant=uuid` opcional). Ver [[architecture-decisions]].
- 253 testes passando no total.

## Testes e revisão (cross-cutting, não é uma fase sequencial)
- Já aplicado em toda entrega desde a Fase 2: cenário feliz, validação, permissão, erro, regressão — por CRUD novo (`.claude/skills/testing.md`, `.claude/agents/qa-testing-master.md`).
- Code Review Architect revisa arquitetura/segurança/performance antes de cada entrega (`.claude/agents/code-review-architect.md`) — continua valendo para Fase 4/5 em diante, sem exceção.

## Frontend (`web/`)
- Reaproveitar `AppLayout`/`TenantMenu` já existentes.
- Listagens (Clientes, Produtos, Pedidos) em **ag-Grid** (já instalado, ainda sem uso). O legado usava `@nadavshaar/react-grid-table` própria — **não reaproveitável**, ag-Grid é a escolha já feita para o projeto novo.
- Formulários e componentes em **MUI** (já instalado). Legado usava Bootstrap 4/Formik/Yup/react-select/react-date-picker/react-text-mask — **não reaproveitável como código** (stack totalmente diferente), mas a lista de campos por formulário (já mapeada em [[05-crud-plan]]) é reaproveitável como especificação.
- Gráficos de indicador (Fase 5) em **Chart.js** — mesma lib do legado (`react-chartjs-2`), só a versão muda (legado v3, novo v5) e a integração é refeita com API/tema novos.
- Legado usa Redux + `react-query` para estado/cache — o projeto novo já resolve isso com Context (`AuthContext`) + services simples; **reavaliar se `react-query`/TanStack Query vale a pena adicionar** quando as listagens ficarem mais complexas (cache de lista, invalidação) — não decidido ainda, não instalar sem necessidade real.
- Seguir identidade visual Maskats (`.claude/memory/design-system.md`, `.claude/memory/brand-guidelines.md`) e mobile-first (`.claude/memory/maskats_mobile_first.md` / restrição registrada em `project-summary.md`) — o legado era desktop-first (Bootstrap admin padrão), **não replicar o layout visual antigo**, só a funcionalidade.

## Dúvidas para validação — todas resolvidas em 2026-07-08

1. ~~**Papéis de usuário**~~ — resolvida: legado só tinha "super admin" (cross-tenant, não existe mais) e "admin" (opera dentro do estabelecimento). Mapeado 1:1 para `TenantRole` (não `Group`). Ver [[architecture-decisions]].
2. ~~**`dia_ideal`/`periodo_ideal`/`confianca`**~~ — resolvida: investigação exaustiva (backend+frontend do legado) não achou automação nenhuma; usuário confirmou que o uso era manual/humano, não de sistema. Ficam como campos informativos simples no `Cliente`, sem trabalho extra. Ver [[architecture-decisions]].
3. ~~**Migração de dados reais**~~ — resolvida: existe dump com dados reais, tratado como Fase 8 própria (ETL), não bloqueia Fase 2/3/4/5. Ver [[architecture-decisions]].
4. ~~**Cancelamento de pedido**~~ — resolvida: é feature nova (sem precedente no legado), desenhada dentro da Fase 4 como decisão de produto própria, não réplica.
5. ~~**Localização (Estado/Cidade/Bairro)**~~ — resolvida e implementada 2026-07-06: viraram tabelas globais compartilhadas (sem seed de dados reais do IBGE ainda). Ver [[architecture-decisions]].
6. ~~**`cliente_novo`/`Publico` (captura de lead)**~~ — resolvida: confirmado fora de uso, não será replicado. Fora de escopo.

Resolvidas (ver [[06-business-rules]] e [[architecture-decisions]]): modelo de tenant, vencimento de parcela (dia 10 do mês seguinte), `pedido.entregue` default, exclusão de pedido (não existe), super-admin cross-tenant (não existe no novo sistema), papéis de usuário, migração de dados, cancelamento de pedido, captura de lead pública, dia/período ideal (sem automação, campo informativo).

**Nenhuma dúvida bloqueando — Fase 3 pode começar.**

## Regra de execução

Nenhuma fase acima deve começar sem: (1) resposta às dúvidas relevantes daquela fase, (2) aprovação explícita do usuário do escopo da fase, (3) plano de CRUD específico revisado por Code Review Architect antes da implementação — conforme `.claude/agents/database-reverse-engineer.md` → "Regras de implementação".
