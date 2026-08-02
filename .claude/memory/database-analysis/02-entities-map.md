# Database Analysis — Entities Map

Classificação das 20 tabelas do `dump_base.sql`. Ver [[01-schema-overview]] para o padrão estrutural comum (auditoria, `ativo`, `estabelecimento_id`, `uuid`) — não repetido tabela a tabela abaixo, só o que é específico de cada uma.

## Visão geral

| Tabela | Tipo | Descrição provável | Escopo tenant |
|---|---|---|---|
| `estabelecimento` | Entidade principal (raiz) | O negócio/loja que usa o sistema | é a raiz, não tem `estabelecimento_id` |
| `usuario` | Segurança | Usuário do sistema, 1 por estabelecimento | sim |
| `tipo_usuario` | Domínio/segurança | Papel do usuário (role simples) | **não** (global) |
| `estado` | Domínio/localização | Estado (UF) | sim |
| `cidade` | Domínio/localização | Cidade, filha de estado | sim |
| `bairro` | Domínio/localização | Bairro, filho de cidade | sim |
| `endereco` | Auxiliar | Logradouro reutilizável (sem número) | sim |
| `cliente` | Entidade principal | Cliente do estabelecimento | sim |
| `cliente_novo` | Técnica/lead | Captura de lead (form público) | não |
| `categoria_cliente` | Domínio | Segmento/tag de cliente | sim |
| `categoria_cliente_cliente` | Pivô N:N | Cliente ↔ Categoria de cliente | sim |
| `dia_ideal` | Domínio | Preferência de dia de contato do cliente | sim |
| `periodo_ideal` | Domínio | Preferência de período (manhã/tarde/noite?) | sim |
| `categoria_produto` | Domínio | Categoria de produto (com prioridade/ordem) | sim |
| `tipo_produto` | Domínio | Subtipo de produto, filho de categoria | sim |
| `produto` | Entidade principal | Item do catálogo | sim |
| `venda` | Transacional (núcleo) | Venda/venda do cliente | sim |
| `venda_produto` | Pivô/transacional | Item de linha do venda (snapshot de preço) | herda do venda |
| `venda_parcela` | Transacional | Parcela de pagamento do venda | herda do venda |
| `json` | Técnica/auditoria fraca | Log genérico de alteração por tabela | sim |

## Entidades principais (detalhado)

### `estabelecimento`
Descrição: o tenant/negócio. Tipo: Entidade principal (raiz).
Campos próprios: `nome`, `endereco` (string livre, **não é FK** para a tabela `endereco` — confirmado pelo banco: é só um campo de texto), `razao_social`, `cnpj`, `email`, `telefone`, `celular` (**NOT NULL**, único contato obrigatório), `whatsapp`, `facebook`, `instagram`, `imagem` (`longblob` — logo armazenada no banco, não como arquivo/URL).
Observações: equivalente conceitual ao `Tenant` do novo sistema, mas muito mais rico em dados de contato/marca. Ver mapeamento em [[07-implementation-roadmap]].

### `usuario`
Descrição: usuário do sistema. Tipo: Segurança.
FKs: `tipo_id` → `tipo_usuario` (belongsTo), `estabelecimento_id` → `estabelecimento` (belongsTo), `inclusao_usuario_id`/`alteracao_usuario_id` → `usuario` (auto-referência).
Observações: 1 usuário pertence a exatamente 1 estabelecimento (`estabelecimento_id NOT NULL`) — diferente do novo sistema, onde `TenantUser` permite 1 usuário em N tenants. `password varchar(200)` sem forma de confirmar algoritmo de hash pelo schema (nenhuma coluna/comment indica). Auto-FK `inclusao_usuario_id NOT NULL` implica que o primeiro usuário do banco precisa referenciar um `usuario.id` já existente — provável bootstrap especial (inferência).

### `cliente`
Descrição: cliente do estabelecimento. Tipo: Entidade principal.
FKs: `estabelecimento_id`, `endereco_id` (belongsTo `endereco`), `dia_ideal_id`/`periodo_ideal_id` (nullable, belongsTo).
Campos próprios: `numero`, `complemento` (ficam no **cliente**, não no `endereco` — permite vários clientes compartilharem o mesmo logradouro com números diferentes), `telefone_principal`/`telefone_secundario`, `observacao`, `confianca` (tinyint, default 1 — provável rating/flag de confiança para venda a prazo).
Observações: `dia_ideal`/`periodo_ideal` + `confianca` + módulo de parcelas em `venda` sugerem fortemente um negócio de **venda porta-a-porta com crediário** (compatível com o nome "PegaTicket" ~ "mascate" = vendedor ambulante/porta-a-porta). *Inferência de alta confiança, não confirmada.*

### `produto`
Descrição: item do catálogo. Tipo: Entidade principal.
FKs: `estabelecimento_id`, `tipo_produto_id`.
Campos próprios: `valor`, `descricao`, `imagem` (`longblob`), `disponivel` (flag), `quantidade` (nullable — provável estoque, mas não obrigatório: pode haver produto sem controle de estoque), `taxa_acrescimo` (nullable — provável acréscimo para venda parcelada).

### `venda`
Descrição: venda/venda. Tipo: Transacional (núcleo do negócio).
FKs: `estabelecimento_id`, `cliente_id`.
Campos próprios: `entregue` (flag, default **1** — confirmado pelo banco; nome sugere "não entregue" mas default é entregue=true, possível inconsistência de nome/default a validar), `data_entrega`, `data_pagamento`, `valor_pago`, `valor_total` (NOT NULL), `pago` (flag, sem default), `parcelado` (flag, sem default), `observacao`.
Observações: `pago`/`parcelado` sem default sugerem que a aplicação sempre define esses valores explicitamente na criação (nunca conta com default do banco) — *inferência*.

### `venda_produto`
Descrição: item de linha do venda. Tipo: Pivô/transacional.
FKs: `venda_id`, `produto_id`.
Campos próprios: `valor_momento_venda` (snapshot do preço na venda — **boa prática confirmada pelo banco**, preço não muda retroativamente se o produto mudar de preço depois), `quantidade_produto` (**float**, não inteiro — permite produto vendido por peso/fração).

### `venda_parcela`
Descrição: parcela de pagamento do venda. Tipo: Transacional.
FKs: `venda_id`.
Campos próprios: `numero` (ordem da parcela), `valor`, `pago`, `valor_pago`, `data_pagamento`, `observacao`.
Observações: cada parcela é rastreada individualmente (paga ou não, valor pago, data) — suporta pagamento parcial de uma parcela específica. Confirma o modelo de crediário/parcelamento.

## Tabelas auxiliares/domínio (localização)

`estado` → `cidade` (FK estado_id) → `bairro` (FK cidade_id) → `endereco` (FK bairro_id + cidade_id + estado_id, denormalizado — referencia os 3 níveis diretamente, não só o nível imediato). Todas escopadas por `estabelecimento_id`: **cada estabelecimento mantém sua própria lista de estados/cidades/bairros**, não é uma tabela geográfica global compartilhada. *Confirmado pelo banco.* Isso é redundante entre estabelecimentos (cada um recadastra "São Paulo" do zero) — candidato a virar tabela global no novo sistema, ver [[07-implementation-roadmap]].

## Tabelas auxiliares/domínio (cliente e produto)

- `categoria_cliente` / `categoria_cliente_cliente` (pivô N:N com `cliente`) — segmentação de cliente por tags.
- `dia_ideal`, `periodo_ideal` — preferência de contato do cliente (ver inferência do modelo de negócio acima).
- `categoria_produto` (tem `prioridade`) → `tipo_produto` (tem `prioridade`, FK categoria_produto) → `produto`. Duas camadas de categorização de produto, ambas ordenáveis.

## Tabela de domínio/segurança global

- `tipo_usuario`: única tabela de domínio **sem** `estabelecimento_id` — papéis de usuário são globais, compartilhados entre todos os estabelecimentos. *Confirmado pelo banco.* Diferente de `categoria_produto`/`dia_ideal`/etc, que são por estabelecimento.

## Tabelas técnicas/fora do padrão

- `cliente_novo`: sem PK/FK/índice/auditoria — provável tabela de captura de lead de formulário público, não uma entidade de CRM completa. *Inferência.*
- `json`: log genérico (tabela + json de valores), sem referência à linha alterada, usuário ou tipo de ação. Auditoria fraca — ver [[06-business-rules]] e [[07-implementation-roadmap]] (o novo sistema já tem solução melhor: `AuditLog` via Events/Listeners).
