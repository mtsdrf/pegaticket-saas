# Database Analysis — CRUD Plan

Rotas seguem o padrão já estabelecido em `.claude/memory/api-patterns.md` (`/api/v1/...`, envelope `{success,message,data,meta}`, `perm:{slug},{action}`, `tenant` middleware quando aplicável). Nomes de recurso já traduzidos para o padrão do novo sistema (inglês/estrutura do `api/`), mapeando da tabela legada em português.

---

## CRUD: Estabelecimento → **Tenant**
- Tabela principal: `estabelecimento` → mapeia para `tenants` (já existe no novo sistema).
- Tabelas relacionadas: nenhuma FK própria (é raiz).
- Rotas: já existem (`/api/v1/tenants`) — este CRUD **não é novo**, só precisa decidir se os campos extras do legado (razão social, CNPJ, contatos, redes sociais, logo) entram no `tenants` atual. Ver dúvida em [[07-implementation-roadmap]].
- Campos de formulário extra a avaliar: `razao_social`, `cnpj`, `email`, `telefone`, `celular`, `whatsapp`, `facebook`, `instagram`, `imagem`.
- Prioridade: **Alta** (mas é extensão de algo existente, não CRUD novo).

---

## CRUD: Usuario → já existe (**User** + **TenantUser**)
- Tabela principal: `usuario` → já coberto por `users`/`tenant_users`/`tenant_roles` no novo sistema, com modelo mais rico (N:N usuário↔tenant + RBAC granular via Group/Functionality/Action).
- `tipo_usuario` (papel simples, global) → decidir se vira `tenant_roles` (por tenant) ou um novo `Group` (sistema). Ver dúvida em [[07-implementation-roadmap]].
- Prioridade: **Não é CRUD novo** — só mapear dado legado para o modelo já pronto na migração de dados.

---

## CRUD: Cliente
- Tabela principal: `cliente`. Tabelas relacionadas: `endereco`, `dia_ideal`, `periodo_ideal`, `categoria_cliente` (N:N via `categoria_cliente_cliente`), `pedido` (histórico, leitura).
- Rotas REST sugeridas:
  ```
  GET    /api/v1/clientes
  POST   /api/v1/clientes
  GET    /api/v1/clientes/{uuid}
  PUT    /api/v1/clientes/{uuid}
  DELETE /api/v1/clientes/{uuid}
  POST   /api/v1/clientes/{uuid}/categorias/sync
  ```
- Campos de listagem: nome, telefone_principal, categoria(s), cidade/bairro (via endereço), confiança, ativo.
- Campos de formulário: nome, endereço (estado/cidade/bairro/logradouro/número/complemento/CEP), telefone_principal, telefone_secundario, dia_ideal, periodo_ideal, categorias (multi-select), observação, confiança.
- Validações **confirmadas pelo código-fonte** (`Cliente/ValidarController` do legado): só `nome` (2-90 chars), `endereco_id` e `ativo` são obrigatórios — telefone é opcional (corrige suposição anterior).
- Sub-recurso confirmado: `GET /clientes/{uuid}/pedidos` — histórico de pedidos do cliente com saldo devedor calculado (ver [[06-business-rules]]).
- Relacionamentos necessários: endereço deve existir/ser criado junto (sub-formulário, não há tela de endereço isolada do ponto de vista do usuário final no legado).
- Filtros prováveis: por categoria, por cidade/bairro, por dia_ideal/periodo_ideal, por confiança, por ativo.
- Ordenações prováveis: nome, data de inclusão.
- Permissões prováveis: `clientes.read/create/update/delete` (seguir padrão `Functionality`+`Action` já existente).
- Regras de exclusão: bloqueada se houver `pedido` vinculado (FK sem cascade) — **Confirmado pelo banco**. Recomendar inativar (`ativo=0`) em vez de excluir.
- Riscos: volume alto (~2000 clientes no legado) e **listagem sem paginação confirmada no código legado** — não replicar, paginar desde o início no novo CRUD.
- Prioridade: **Alta**.

---

## CRUD: Categoria de Cliente
- Tabela principal: `categoria_cliente`.
- Rotas: `GET/POST/PUT/DELETE /api/v1/categorias-cliente`.
- Campos: nome. Sem `prioridade` (diferente de categoria de produto).
- Regras de exclusão: bloqueada se vinculada a algum cliente (FK em `categoria_cliente_cliente`).
- Prioridade: **Média** (cadastro simples, mas usado como filtro em Clientes).

---

## CRUD: Produto
- Tabela principal: `produto`. Tabelas relacionadas: `tipo_produto` (belongsTo), `categoria_produto` (via tipo_produto).
- Rotas REST sugeridas:
  ```
  GET    /api/v1/produtos
  POST   /api/v1/produtos
  GET    /api/v1/produtos/{uuid}
  PUT    /api/v1/produtos/{uuid}
  DELETE /api/v1/produtos/{uuid}
  ```
- Campos de listagem: nome, categoria, tipo, valor, disponível, quantidade (estoque).
- Campos de formulário: nome, tipo_produto (select, filtrado por categoria), valor, descrição, imagem (upload — **recomendar migrar de `longblob` para arquivo/URL**, ver [[06-business-rules]]), disponível, quantidade, taxa_acrescimo.
- Validações prováveis: nome e valor obrigatórios, tipo_produto obrigatório.
- Filtros prováveis: por categoria, por tipo, por disponibilidade.
- Ordenações prováveis: nome, valor, prioridade do tipo/categoria.
- Permissões prováveis: `produtos.read/create/update/delete`.
- Regras de exclusão: bloqueada se vinculado a `pedido_produto` (histórico de venda) — recomendar inativar (`disponivel=0`/`ativo=0`) em vez de excluir.
- Prioridade: **Alta**.

---

## CRUD: Categoria de Produto / Tipo de Produto
- Tabelas: `categoria_produto` (nome, prioridade), `tipo_produto` (nome, prioridade, categoria_produto_id).
- Rotas: `GET/POST/PUT/DELETE /api/v1/categorias-produto` e `/api/v1/tipos-produto`.
- Ordenação por `prioridade` (drag-and-drop de ordem é candidato de UX, não confirmado como requisito).
- Prioridade: **Média** (suporte ao catálogo, não usado diretamente pelo cliente final).

---

## CRUD: Pedido (com sub-recursos)
- Tabela principal: `pedido`. Tabelas relacionadas: `cliente` (belongsTo), `pedido_produto` (hasMany, sub-recurso), `pedido_parcela` (hasMany, sub-recurso).
- Rotas REST sugeridas:
  ```
  GET    /api/v1/pedidos
  POST   /api/v1/pedidos
  GET    /api/v1/pedidos/{uuid}
  PUT    /api/v1/pedidos/{uuid}
  DELETE /api/v1/pedidos/{uuid}

  GET    /api/v1/pedidos/{uuid}/itens
  POST   /api/v1/pedidos/{uuid}/itens
  DELETE /api/v1/pedidos/{uuid}/itens/{itemUuid}

  GET    /api/v1/pedidos/{uuid}/parcelas
  POST   /api/v1/pedidos/{uuid}/parcelas
  PATCH  /api/v1/pedidos/{uuid}/entregue
  PATCH  /api/v1/pedidos/{uuid}/pago
  ```
  (`entregue`/`pago` como ações dedicadas — **confirmado pelo código-fonte**, o legado já tem `alterarEntregue`/`alterarPago` separados de `alterar`, evita reenviar o pedido inteiro só para marcar status.)
- Campos de listagem: cliente, valor_total, pago, parcelado, entregue, data_entrega.
- Campos de formulário: cliente (busca), itens (produto + quantidade, valor travado no submit), parcelado (sim/não → gera parcelas), observação.
- Validações prováveis: pelo menos 1 item, cliente obrigatório, valor_total = soma dos itens (regra a confirmar com o usuário — banco não garante isso via CHECK, e o código legado também não recalcula/valida isso no servidor, confia no valor enviado pelo frontend — **candidato a melhoria** no novo sistema: validar `valor_total` no backend).
- Relacionamentos necessários: criação de pedido + itens deve ser transacional (tudo ou nada) — seguir padrão `DB::transaction` já usado no projeto (mesmo padrão do legado).
- Regra de vencimento e cascata de quitação: **confirmadas pelo código-fonte**, ver [[06-business-rules]] — vencimento = dia 10 do mês seguinte se não pago; última parcela paga quita o pedido inteiro automaticamente.
- Filtros prováveis: por cliente, por status (pago/pendente/entregue), por período, por localização do cliente (cidade/bairro/endereço — confirmado pelo relatório legado).
- Ordenações prováveis: data de inclusão (mais recente primeiro).
- Permissões prováveis: `pedidos.read/create/update`, `pedidos.marcar-entregue`, `pedidos.marcar-pago` (ações dedicadas, não `update` genérico).
- Regras de exclusão: **confirmado pelo código-fonte** — pedido não pode ser excluído (rota de delete estava comentada no legado). Só existe update/ações de status.
- Riscos: é o módulo mais complexo (transação com sub-recursos, cálculo de parcelas, snapshot de preço, cascata de quitação) — maior superfície de regra de negócio.
- Prioridade: **Alta**, mas **depende de Cliente e Produto existirem primeiro**.

---

## CRUD: Relatórios / Indicadores (confirmado pelo código-fonte, novo módulo não previsto na 1ª versão do plano)
- Sem tabela própria — leitura agregada de `pedido`/`cliente`/`produto`/`endereco`.
- Rotas REST sugeridas (ação, não recurso CRUD tradicional):
  ```
  GET  /api/v1/relatorios/indicadores?data_inicio=&data_fim=
  GET  /api/v1/relatorios/graficos?data_inicio=&data_fim=
  GET  /api/v1/relatorios/pedidos?cliente_id=&cidade_id=&bairro_id=&endereco_id=&pago=&entregue=&data_inicio=&data_fim=
  GET  /api/v1/relatorios/clientes?cidade_id=&bairro_id=&endereco_id=  (clientes com todos os pedidos pagos+entregues)
  POST /api/v1/relatorios/pedidos/pdf
  POST /api/v1/relatorios/clientes/pdf
  ```
- Indicadores/gráficos exatos: ver [[04-modules-map]] → "Relatórios e Indicadores" e [[06-business-rules]].
- **Todo filtro deve usar Query Builder com binding** — o legado tem SQL injection real nesses endpoints (concatenação de string), não replicar de forma alguma.
- PDF: `barryvdh/laravel-dompdf` (mesmo pacote do legado) funciona em Laravel 13 — reaproveitável como dependência, não como código (as views Blade seriam recriadas do zero seguindo o design system PegaTicket).
- Permissões prováveis: `relatorios.read`, `relatorios.exportar-pdf`.
- Prioridade: **Alta** — o `DashboardPage.tsx` atual já tem um placeholder esperando exatamente os indicadores confirmados aqui.

---

## CRUD: Localização (Estado/Cidade/Bairro/Endereço)
- Prioridade: **Média/Baixa** — candidato a virar tabela **global compartilhada** (IBGE) em vez de recadastro por tenant, ver [[07-implementation-roadmap]]. Se mantido por tenant, CRUD simples em cascata (estado→cidade→bairro), usado como select dependente no formulário de Cliente.

---

## Fora do CRUD tradicional

- `cliente_novo`: não é CRUD, é uma **caixa de entrada de leads** — tela de listagem + ação "converter em cliente" (cria `cliente` a partir dos dados capturados). Prioridade: Média.
- `json`: não deve virar CRUD nem ser portado — substituído pelo `AuditLog` já existente no novo sistema. Prioridade: N/A (descartar).
