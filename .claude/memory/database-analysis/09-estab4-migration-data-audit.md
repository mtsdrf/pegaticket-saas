# Auditoria de dados — migração do estabelecimento id=4 (Js Queijos e Doces)

Passo 1 (análise pura, sem script/plano de migração) do fluxo `database-reverse-engineer`. Fonte: `backup_prod_pegaticket.sql` (raiz do repo, 23MB, mysqldump COM dados reais — diferente de `dump_base.sql` usado em [[01-schema-overview]]/[[07-implementation-roadmap]], que era só estrutura). Analisado em 2026-07-22 via parsing Python dos `INSERT INTO` (sem subir MySQL), nunca contra `DB_HOST` do projeto.

**Alvo único de migração**: `estabelecimento.id = 4`, uuid `5f90a014-a13a-41d8-bd20-f90f11a3e1ba`, nome "Js Queijos e Doces". Único estabelecimento a migrar — os outros 3 (`id=1` admin PegaTicket, `id=2` teste, `id=3` outro cliente real) ficam de fora.

Este arquivo complementa (não substitui) [[01-schema-overview]]–[[07-implementation-roadmap]], que documentam a estrutura geral das 20 tabelas sem dados. Aqui o foco é: volume real por tabela escopado ao estabelecimento 4, integridade referencial nos dados de fato, e mapeamento campo-a-campo contra o schema atual (`api/database/migrations`).

## 1. Volume de dados escopado ao estabelecimento 4

Contagem via parsing direto dos `INSERT INTO` (não é `COUNT(*)` de banco vivo, é grep/parse do dump — mesmo resultado, sem subir servidor).

| Tabela | Direto/Indireto até `estabelecimento_id=4` | Linhas escopadas | Total na tabela (todos os 4 estabs) |
|---|---|---|---|
| `estabelecimento` | é a própria linha | 1 | 4 |
| `usuario` | direto | 2 | 5 |
| `estado` | direto | 1 | 3 |
| `cidade` | direto | 6 | 15 |
| `bairro` | direto | 48 | 56 |
| `endereco` | direto | 628 | 631 |
| `dia_ideal` | direto | 31 | 45 |
| `periodo_ideal` | direto (nenhuma linha do estab 4 no dump, ver achado abaixo) | 0 | 9 |
| `categoria_produto` | direto | 3 | 18 |
| `tipo_produto` | direto | 18 | 45 |
| `produto` | direto | 176 | 295 |
| `cliente` | direto | 1913 | 1978 |
| `cliente_novo` | tabela sem `estabelecimento_id`, sem FK — não é possível escopar (ver seção 6) | — | 1 (tabela inteira) |
| `categoria_cliente` | direto | 0 (tabela **vazia** no dump inteiro) | 0 |
| `categoria_cliente_cliente` | direto | 0 (tabela **vazia** no dump inteiro) | 0 |
| `json` | direto | 1 | 3 |
| `pedido` | direto | **38.052** | 38.172 |
| `pedido_produto` | indireto via `pedido_id → pedido.estabelecimento_id` | **92.246** | 92.592 |
| `pedido_parcela` | indireto via `pedido_id → pedido.estabelecimento_id` | **0** | 8 |

Achado: `periodo_ideal` tem 0 linhas para o estabelecimento 4 no dump (as 9 linhas existentes pertencem a estab 1/2/3). Isso é consistente com `cliente.periodo_ideal_id` do estab 4 ser sempre `NULL` — não há uso real desse campo por esse tenant (ver seção 4).

## 2. `pedido` — schema completo e distribuição real dos dados (estab 4)

```sql
CREATE TABLE `pedido` (
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `inclusao_usuario_id` int(11) NOT NULL,
  `inclusao_data` datetime DEFAULT current_timestamp(),
  `alteracao_usuario_id` int(11) DEFAULT NULL,
  `alteracao_data` datetime DEFAULT NULL,
  `id` int(11) NOT NULL,
  `uuid` varchar(40) NOT NULL,
  `estabelecimento_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `entregue` tinyint(1) NOT NULL DEFAULT 1,
  `data_entrega` datetime DEFAULT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `valor_pago` float(10,2) DEFAULT NULL,
  `valor_total` float(10,2) NOT NULL,
  `pago` tinyint(1) NOT NULL,
  `parcelado` tinyint(1) NOT NULL,
  `observacao` varchar(200) DEFAULT NULL
);
-- PK(id); FK cliente_id→cliente, estabelecimento_id→estabelecimento,
-- inclusao_usuario_id/alteracao_usuario_id→usuario (todas ADD CONSTRAINT reais, sem CASCADE explícito = RESTRICT)
```

Não há campos de subtotal/desconto/frete/forma de pagamento — só `valor_total` (bruto final) e `valor_pago`.

**Distribuição real (38.052 pedidos do estab 4)**:
- `ativo`: sempre `1` (nunca `0`) — não há "soft-delete por flag" exercido para pedido deste tenant, apesar do campo existir.
- `entregue`: `1` em 38.042, `0` em 10.
- `pago`: `1` em 35.857, `0` em 2.195.
- `parcelado`: sempre `0` — **nenhum pedido do estabelecimento 4 usa parcelamento**, apesar do campo existir e de haver linhas de `pedido_parcela` no banco (todas pertencem ao estab 3, ver seção 3).
- Range de `inclusao_data`: `2022-09-15 23:33:37` → `2026-07-22 18:27:54` (dado até a data do dump).
- Range de `data_entrega` (nas linhas não-nulas): `2022-09-16 22:19:23` → `2026-07-22 18:27:54`.
- `valor_total`: soma de **R$ 4.217.946,33** nos 38.052 pedidos.
- `valor_pago`: nunca `NULL` (preenchido em 100% das linhas, mesmo quando `pago=0`) — soma **R$ 3.226.599,06**. Ver achado de integridade na seção 5 sobre o que esse campo realmente significa.
- `observacao`: preenchida em 11.741 de 38.052 (30,8%) — texto livre, geralmente nome/apelido do cliente ou anotação de cobrança ("Tem conta pra traz", "Sebastiana" etc.) — **não reproduzido aqui integralmente por poder conter dado de cliente real**.

## 3. `pedido_produto` — schema e integridade (estab 4)

```sql
CREATE TABLE `pedido_produto` (
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `inclusao_usuario_id` int(11) NOT NULL,
  `inclusao_data` datetime DEFAULT current_timestamp(),
  `alteracao_usuario_id` int(11) DEFAULT NULL,
  `alteracao_data` datetime DEFAULT NULL,
  `id` int(11) NOT NULL,
  `uuid` varchar(40) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_momento_venda` float(10,2) NOT NULL,
  `quantidade_produto` float(10,2) NOT NULL
);
-- FK pedido_id→pedido, produto_id→produto (RESTRICT implícito)
```

- `valor_momento_venda` é **congelado no momento da venda** (não é FK pro preço atual do `produto` — é uma cópia numérica). **CORREÇÃO (2026-07-22, ver seção 5)**: a frase original aqui dizia "equivalente ao `order_items.unit_price` atual" — ERRADA. Confirmado por reconciliação 100% contra os 38.052 pedidos do estab4: `valor_momento_venda` já é o TOTAL da linha (quantidade-inclusive), equivalente a `order_items.line_total`, não a `unit_price`. `unit_price` correto = `valor_momento_venda / quantidade_produto`.
- Não existe campo de desconto por item — qualquer desconto só aparece implícito na diferença entre `pedido.valor_total` e a soma dos itens (ver seção 5).
- 0 linhas órfãs (`pedido_id` inexistente) e 0 linhas com `produto_id` inexistente em toda a tabela (não só no escopo do estab 4) — integridade referencial 100% íntegra nesse ponto, apesar de não haver `FOREIGN KEY` real impedindo isso no schema... **correção**: há sim `ADD CONSTRAINT fk_pedido_produtoXpedido`/`fk_pedido_produtoXproduto` reais no dump (ver seção 7), então a integridade é garantida pelo próprio banco, confirmada também pelos dados.

## 4. `pedido_parcela` — schema e achado crítico de volume

```sql
CREATE TABLE `pedido_parcela` (
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `inclusao_usuario_id` int(11) NOT NULL,
  `inclusao_data` datetime DEFAULT current_timestamp(),
  `alteracao_usuario_id` int(11) DEFAULT NULL,
  `alteracao_data` datetime DEFAULT NULL,
  `id` int(11) NOT NULL,
  `uuid` varchar(40) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `valor` float(10,2) NOT NULL,
  `pago` tinyint(1) NOT NULL,
  `valor_pago` float(10,2) DEFAULT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `observacao` varchar(150) DEFAULT NULL
);
-- FK pedido_id→pedido (RESTRICT)
```

**Achado**: a tabela inteira (todos os 4 estabelecimentos) tem só **8 linhas** no dump (`AUTO_INCREMENT=14`, ou seja só 13 linhas já existiram historicamente, 5 já removidas fisicamente — não há `ativo=0` nelas, foram de fato `DELETE`d, o que contradiz o padrão "nunca apaga fisicamente" observado em [[01-schema-overview]] para as outras tabelas). Das 8 linhas restantes, **todas pertencem a `pedido_id` do estabelecimento 3** — nenhuma ao estabelecimento 4. Confirmado também por: nenhum `pedido` do estab 4 tem `parcelado=1` (seção 2).

Conclusão prática: `pedido_parcela` é **irrelevante para a migração do estabelecimento 4** — não há nenhuma linha para migrar. O equivalente atual (`order_installments`) não precisa ser populado para este tenant.

## 5. Integridade cruzada `pedido` × `pedido_produto` (estab 4) — achado mais relevante

**CORRIGIDO INTEIRAMENTE em 2026-07-22 — a análise original abaixo (mantida riscada/histórica por transparência) partiu de uma coluna interpretada errada.** A comparação certa é `pedido.valor_total` contra `SUM(pedido_produto.valor_momento_venda)` **sem multiplicar por `quantidade_produto`** (o campo já é o total da linha, não o preço unitário — ver seção 3). Refeita essa comparação para os 38.052 pedidos do estab 4 (38.049 com pelo menos 1 item, 3 vazios): **38.049 de 38.049 batem exatamente (diferença < R$0,01, 0 divergências)**. Confirmado de novo, de forma independente, pelo dry-run de `legacy:migrate-estabelecimento` (reconciliação item-a-item embutida no comando). Não existe desconto/acréscimo implícito nenhum — `pedido.valor_total` é sempre e exatamente a soma dos `valor_momento_venda` dos itens.

**Análise original (ERRADA, mantida para rastreabilidade — não usar)**: ~~Comparando `pedido.valor_total` contra `SUM(pedido_produto.valor_momento_venda * quantidade_produto)` por pedido: 24.078 pedidos batiam exato, 13.405 "menor" (desconto implícito suposto), 566 "maior" (acréscimo implícito suposto), 3 vazios. "Achado grave isolado": pedido `id=21131` teria `valor_total=32.400,00` mas soma dos itens `R$29.160.000,00` (900 unidades × R$32.400), sugerindo erro de digitação de quantidade, com padrão semelhante em mais 741-4.157 pedidos.~~ **Nenhuma dessas divergências era real** — eram todas o mesmo artefato de multiplicar `valor_momento_venda` (já total) por `quantidade_produto` de novo. O pedido `id=21131` é um pedido válido: 900 unidades × R$36,00/unidade = R$32.400,00 total, batendo exato com `valor_total`. Não havia desconto, não havia acréscimo, não havia erro de digitação — era 100% interpretação errada da coluna, tanto na análise Python original quanto na primeira versão do comando de migração desta sessão.

**3 pedidos** (`id` 36195, 36492, 36533) continuam **genuinamente vazios** (zero linhas em `pedido_produto`, `valor_total=0.00`) — isso não muda com a correção.

Nenhum órfão de FK encontrado: 0 `pedido_produto` com `pedido_id` inexistente, 0 com `produto_id` inexistente (em toda a tabela, todos os 4 estabelecimentos).

## 6. `json` — o que é, na prática

```sql
CREATE TABLE `json` (
  `tabela` varchar(50) NOT NULL COMMENT 'Nome da tabela referenciada',
  `json` longtext NOT NULL COMMENT 'Json com os valores atualizados',
  `estabelecimento_id` int(11) NOT NULL
);
-- sem PK, sem FK, sem índice
```

3 linhas no dump inteiro, uma por estabelecimento com dado (`estabelecimento_id` 2, 3 e 4), sempre `tabela='pedido'`. É um **snapshot/backup histórico de `pedido`** em formato JSON denormalizado (cada item do array tem `id`, `cliente_id`, **`nome`** do cliente embutido, `produtos`/`produto_ids`, valores em formato string BR "161,00", datas em `dd/mm/aaaa`) — aparentemente gerado numa migração/reestruturação anterior do próprio sistema legado (formato antigo do pedido, antes de virar a estrutura relacional atual com `pedido_produto`).

Para o estabelecimento 4: 3.951 entradas, `id` de pedido variando entre 9 e 4067. **Todos os 3.951 ids já existem na tabela `pedido` atual** (0 ids exclusivos do snapshot) — ou seja, não há dado de pedido "perdido" só recuperável via essa tabela. **Conclusão: `json` pode ser ignorada na migração** — é histórico redundante, já coberto por `pedido`/`pedido_produto`. Contém nome de cliente embutido em texto livre (PII) — não reproduzido aqui além da estrutura.

## 7. `cliente` vs `cliente_novo`

`cliente` (1913 linhas do estab 4) é a entidade real, com toda a auditoria/tenant/FK padrão (`estabelecimento_id`, `endereco_id`, `dia_ideal_id`, `periodo_ideal_id` etc.) — é o que popula `pedido.cliente_id`.

`cliente_novo` tem só 4 colunas (`inclusao_data`, `nome`, `whatsapp`, `email`), **sem PK, sem índice, sem FK, sem `estabelecimento_id`, sem `id`**, e **1 única linha no dump inteiro** (não é possível escopar ao estabelecimento 4 porque a tabela não tem nenhum vínculo com `estabelecimento`). Não há campo que referencie `cliente` (nenhuma FK, nenhum `cliente_id`). *Inferência (não confirmada pelo banco)*: parece captura de lead de um formulário público genérico ("quero ser cliente"/contato), não uma tabela de negócio nem uma migração incremental incompleta de `cliente` — a única linha existente nem é da JS Queijos e Doces. **Não há dado de `cliente_novo` a migrar para o estabelecimento 4.**

## 8. Mapeamento tentativo — schema atual (`api/database/migrations`) — SÓ HIPÓTESE, a confirmar

### `estabelecimento` → `tenants`
| Legado | Atual (`tenants`) | Observação |
|---|---|---|
| `nome` | `name` | bate |
| — | `slug` | **não existe no legado** — precisa ser gerado (decisão: a partir de `nome`?) |
| `razao_social`, `cnpj` | `cnpj` (só CNPJ, 14 chars) | `razao_social` **não tem campo equivalente em `tenants`** hoje |
| — | `ie`, `im`, `cnae`, `tax_regime`, `fiscal_environment`, `ibge_city_code` | campos fiscais novos, todos nullable, sem fonte no legado — ficam vazios |
| `endereco` (string livre) | `endereco_id` (FK→`enderecos`, estrutura normalizada) | legado é 1 string; atual é entidade normalizada (`estados`/`cidades`/`bairros`/`enderecos`) — precisa geocodificar/parsear ou criar manualmente 1 endereço para o tenant |
| `email`, `telefone`, `celular`, `whatsapp`, `facebook`, `instagram` | **nenhum campo equivalente existe em `tenants` nem em `tenant_settings`** (confirmado lendo todas as migrations de `tenants`/`tenant_settings`) | **gap real — decisão do usuário necessária** (perder, ou adicionar campo novo antes de migrar) |
| `imagem` (longblob, sempre NULL para os 4 estabs no dump) | `logo_data`/`logo_mime`/`logo_path` | sem dado a migrar (campo vazio no legado) |
| `id`/`uuid` | `id`/`uuid` | uuid do legado pode ser preservado como `tenants.uuid` (já é uuid v4 válido) |

### `cliente`/`cliente_novo` → `clients`
| Legado (`cliente`) | Atual (`clients`) | Observação |
|---|---|---|
| `nome` (varchar 200) | `name` (varchar **90**) | **risco de truncamento** — checar nomes >90 chars antes de migrar |
| `estabelecimento_id` | `tenant_id` | bate (modelo 1:1 estab→dados, igual ao novo tenant_id em `clients`) |
| `endereco_id` | `endereco_id` | bate estruturalmente, mas ver `numero`/`complemento` abaixo |
| `numero`, `complemento` (na tabela `cliente`) | **não existem em `clients`** — migration `2026_07_10_100004_add_numero_complemento_to_enderecos_table.php` já documenta exatamente esse problema: no legado `numero`/`complemento` ficam no cliente, no novo sistema viram atributos de `enderecos.numero`/`enderecos.complemento` | **já antecipado no schema atual** — comentário da migration cita literalmente "Fase 8 (migração de dados reais)". Implicação: 1 `endereco_id` do legado pode ser compartilhado por vários clientes com `numero` diferente — migração provavelmente precisa **criar 1 linha em `enderecos` por combinação (endereco_id legado + numero + complemento)**, não reaproveitar 1:1 |
| `telefone_principal`, `telefone_secundario` | `phone_primary`, `phone_secondary` | bate |
| `dia_ideal_id`, `periodo_ideal_id` | `dia_ideal_id`, `periodo_ideal_id` | bate (tabelas equivalentes existem: `dia_ideais`, `periodo_ideais`, ambas tenant-scoped) |
| `observacao` (varchar 200) | `notes` (text) | bate, atual é mais permissivo |
| `confianca` (tinyint) | `is_trusted` (boolean) | bate semanticamente |
| `ativo` | `is_active` | bate |
| — | `cpf_cnpj`, `ie`, `ie_indicator` (fiscal, novos) | sem fonte no legado, ficam vazios |
| `cliente_novo` | sem equivalente — não migra (seção 7) | |

### `categoria_cliente`/`categoria_cliente_cliente` → `client_categories`/`client_client_categories`
Ambas as tabelas do legado estão **vazias no dump inteiro** (0 linhas, apesar de `AUTO_INCREMENT` alto — 16 e 87.980 — indicando uso histórico intenso já todo removido/truncado antes deste dump). **Nada a migrar** para o estabelecimento 4 nesse par. *Achado a levar ao usuário*: por que `categoria_cliente_cliente` tem `AUTO_INCREMENT=87980` mas 0 linhas — dado foi apagado fisicamente em algum momento (mesmo padrão incomum já visto em `pedido_parcela`, seção 4).

### `produto`/`categoria_produto`/`tipo_produto` → `products`/`product_categories`/`product_types`
| Legado | Atual | Observação |
|---|---|---|
| `categoria_produto.nome`, `prioridade`, `estabelecimento_id` | `product_categories.name`, `priority`, `tenant_id` | bate 1:1 |
| `tipo_produto.nome`, `prioridade`, `categoria_produto_id`, `estabelecimento_id` | `product_types.name`, `priority`, `product_category_id`, `tenant_id` | bate 1:1 |
| `produto.nome` | `products.name` | bate (sem limite de tamanho divergente visível) |
| `produto.valor` (float 10,2) | `products.price` (**decimal** 10,2) | mesma escala, mas legado usa `float` (ponto flutuante binário) e atual usa `decimal` (exato) — risco clássico de arredondamento residual (ex.: `0.1+0.2`) ao converter float→decimal; checar diffs após conversão, não assumir 1:1 exato |
| `produto.descricao` | `products.description` (text) | bate |
| `produto.imagem` (longblob, sempre NULL nos 176 produtos do estab 4 — confirmado) | `products.image_data`/`image_mime`/`image_path` | sem dado a migrar |
| `produto.disponivel` | `products.is_available` | bate |
| `produto.quantidade` (int nullable) | `products.stock_quantity` (int **default 0**, não nullable) | legado permite NULL = "sem controle de estoque"; atual força um número — decisão: `NULL`→`0`? |
| `produto.taxa_acrescimo` | `products.surcharge_rate` | bate |
| `produto.tipo_produto_id` | `products.product_type_id` | bate |
| — | `products.sku`, `barcode`, `brand`, `unit`, `is_lot_controlled`, `is_expiry_controlled`, `is_serial_controlled`, `min_stock`, `max_stock`, `reorder_point`, `reorder_qty`, `last_purchase_cost`, `wholesale_min_quantity`, `wholesale_price`, `ncm`, `cest`, `origin`, `default_cfop`, `csosn_cst` | **todos sem fonte no legado**, todos nullable/com default — ficam vazios. `unit` tem default `'un'`, não é problema |
| — | `products.tenant_id` × **`product_type_id` obrigatório (FK NOT NULL)** | confirmar que os 176 produtos do estab 4 têm `tipo_produto_id` sempre preenchido e válido (integridade já confirmada, seção "achados de integridade" abaixo) |

### `pedido`/`pedido_produto`/`pedido_parcela` → `orders`/`order_items`/`order_installments` + `payments` (comparação mais importante)
| Legado | Atual | Observação |
|---|---|---|
| `pedido.estabelecimento_id` | `orders.tenant_id` | bate |
| `pedido.cliente_id` | `orders.client_id` (FK NOT NULL) | bate |
| — | `orders.stock_location_id` (FK **NOT NULL**) | **legado não tem conceito de local de estoque** — orders atual exige 1 `stock_location_id` obrigatório. Precisa criar 1 `stock_location` (provavelmente "Loja"/default) para o tenant antes de migrar pedidos — **decisão do usuário** |
| `pedido.parcelado` | `orders.is_installment` | bate semanticamente, mas **sempre `false`** para o estab 4 (seção 2/4) — `order_installments` fica vazio para este tenant |
| `pedido.valor_total` | `orders.total_amount` | bate, mas atual é **sempre calculado no backend a partir da soma de `order_items`** (comentário explícito na migration) — os 13.971 pedidos do estab 4 (seção 5) onde `valor_total` ≠ soma dos itens **não podem ser recriados via fluxo normal de criação de pedido**; precisam de inserção direta que preserve o `total_amount` histórico, ignorando a regra "calculado" (procedimento de migração, não do fluxo de app) |
| `pedido.valor_pago` | `orders.paid_amount` | bate — comentário da migration atual já cita literalmente "paridade com o legado, campo valor_pago", então o mapeamento já foi validado antes por quem escreveu a migration |
| `pedido.pago` | `orders.is_paid` | bate |
| `pedido.data_pagamento` | `orders.paid_at` | bate |
| `pedido.entregue` | `orders.is_delivered` | bate |
| `pedido.data_entrega` | `orders.delivered_at` **e/ou** `orders.expected_delivery_date` | **ambíguo** — atual separa "data prevista" (`expected_delivery_date`, imutável) de "data real de entrega" (`delivered_at`, setada só por ações de sistema). Legado tem 1 único campo `data_entrega`. Qual dos dois vira o destino da migração é decisão do usuário |
| — | `orders.due_date` | sem fonte direta no legado (pedido não-parcelado não tem vencimento explícito) — fica `NULL`? |
| `pedido.observacao` | `orders.notes` | bate |
| **sem equivalente** | `orders.discount_amount` | **poderia** receber a diferença negativa calculada na seção 5 (13.405 pedidos), mas isso é reconstrução, não dado direto do legado — decisão do usuário |
| **sem equivalente** | `orders.delivery_fee` | idem, para os 566 pedidos com `valor_total` > soma dos itens — mas também pode ser erro de digitação (seção 5), não frete real. **Ambíguo, não decidir sozinho** |
| **sem equivalente** | `orders.coupon_id`, `cashback_redeemed_amount`, `service_fee`, `cash_session_id` | sistema legado não tinha cupom/cashback/comanda/caixa — ficam vazios/NULL, sem perda de informação real |
| **sem equivalente** | `orders.status` (string, default `confirmed`) e `orders.origin` (default `staff`) | legado não tem status textual nem origem — inferir de `pago`+`entregue`? Precisa de tabela de decisão explícita do usuário (o quê vira `cancelled`? o legado não tem conceito de cancelamento — sem `ativo=0` em nenhum pedido do estab 4) |
| `pedido.ativo` | soft delete atual (`deleted_at`) | nunca `0` nos dados do estab 4 (seção 2) — não há pedido "desativado" a preservar como soft-deleted |
| `pedido_produto.produto_id`/`pedido_id` | `order_items.product_id`/`order_id` | bate |
| `pedido_produto.quantidade_produto` (float 10,2) | `order_items.quantity` (**decimal 12,3**) | mesma ideia (quantidade fracionária suportada nos dois lados), mas atual tem 3 casas decimais contra 2 do legado — sem perda, só folga extra |
| `pedido_produto.valor_momento_venda` | `order_items.unit_price` (decimal 10,2) | bate, ambos "preço congelado no momento da venda" (mesmo conceito, comentário idêntico na migration atual) |
| — | `order_items.line_total` | **calculado** (`unit_price * quantity`), sem equivalente direto no legado — mas dá pra derivar direto na migração, sem ambiguidade |
| `pedido_parcela` (0 linhas para estab 4) | `order_installments` | **nada a migrar** para este tenant (seção 4) |
| **sem equivalente** | `payments` (polimórfica, `payable_type`/`payable_id`→`orders`, campos `method`, `status`, `amount`, `provider`) | legado **não registra forma de pagamento** em nenhuma tabela (nem `pedido`, nem `pedido_parcela`) — `payments.method` fica sempre `NULL` se migrar 1 `payment` por pedido pago, ou a migração pode optar por não popular `payments` nenhuma (só `orders.is_paid`/`paid_amount`). **Decisão do usuário**: criar `payments` sintéticos ou não |

### `usuario`/`tipo_usuario` → `users`/`tenant_users`/`tenant_roles`
| Legado | Atual | Observação |
|---|---|---|
| `usuario.email` | `users.email` (**unique global**, não por tenant) | 2 usuários do estab 4 no legado — checar se o e-mail já existe em algum `User` já cadastrado no sistema atual antes de inserir (colisão possível, principalmente se o usuário já testou o sistema novo com o mesmo e-mail) |
| `usuario.password` | `users.password` | **algoritmo de hash do legado desconhecido** (não inspecionado aqui, fora de escopo desta análise estrutural) — se não for bcrypt compatível com o `Hash::check` do Laravel, migração de senha não pode ser 1:1; provavelmente exige reset de senha forçado para os usuários do estab 4 |
| `usuario.nome` | `users.name` | bate |
| `usuario.ativo` | `users.is_active` | bate |
| `usuario.tipo_id` → `tipo_usuario` (só 2 linhas globais: "Administrador PegaTicket", "Administrador") | `tenant_users.tenant_role_id` → `tenant_roles` (por tenant, com slug/permissões via `GroupPermission`) | **modelo bem diferente**: legado tem 1 tabela de tipo global e simples; atual tem role por tenant + sistema de permissão via Group/Functionality/Action. Não há mapeamento direto — role do(s) usuário(s) do estab 4 no sistema novo precisa ser definida do zero (decisão do usuário, provavelmente "Administrador" do tenant) |
| `usuario.estabelecimento_id` (1 usuário pertence a exatamente 1 estabelecimento) | `tenant_users` (N:N entre `users` e `tenants`) | modelo novo é mais flexível (1 usuário pode ter acesso a vários tenants) — pros 2 usuários do estab 4, migração é simples (1 linha em `tenant_users` cada), mas é uma mudança de modelo, não mapeamento direto de coluna |

### `endereco`/`bairro`/`cidade`/`estado`/`dia_ideal`/`periodo_ideal` → equivalentes atuais
Todos existem hoje com nomes quase idênticos (`enderecos`, `bairros`, `cidades`, `estados`, `dia_ideais`, `periodo_ideais`) e são tenant-scoped (`tenant_id`) exatamente como o legado é estabelecimento-scoped (`estabelecimento_id`) — mapeamento estrutural direto, 1:1 nas colunas centrais (`nome`→`name`, `estado_id`→`estado_id` etc.), com 2 diferenças:
- `enderecos` atual tem `numero`/`complemento` (movidos do `cliente` legado, ver seção `clients` acima) e `lat`/`lng`/`geocode_status`/`geocoded_at` (geocodificação, sem fonte no legado, fica pendente/`NULL` até rodar o job).
- Todas as tabelas atuais (`estados`, `cidades`, `bairros`) têm `unique(nome)` composto por hierarquia (ex.: `uniq_cidade_bairro_name`) — checar se os 6 `cidade`/48 `bairro`/1 `estado` do estab 4 não colidem em nome dentro do mesmo tenant (não verificado nesta análise, ponto de atenção antes do script de migração).

## Achados de integridade — resumo (estabelecimento 4)

1. **Nenhum órfão de FK** em toda a cadeia checada: `pedido→cliente`, `cliente→endereco`, `cliente→dia_ideal`/`periodo_ideal`, `produto→tipo_produto`, `tipo_produto→categoria_produto`, `pedido_produto→pedido`/`produto` — 0 casos em todas as checagens.
2. **Nenhum vazamento cross-tenant** nos dados do estab 4 (nenhum `cliente_id`, `endereco_id`, `dia_ideal_id`, `periodo_ideal_id`, `tipo_produto_id`, `categoria_produto_id` referenciando outro estabelecimento) — isolamento de dado 100% íntegro apesar de não haver esse tipo de constraint no schema (é constatação empírica dos dados, não garantia estrutural).
3. ~~**`pedido.valor_total` diverge da soma de `pedido_produto`** em 13.971 de 38.052 pedidos (36,7%)~~ — **RETIRADO, 2026-07-22**: era artefato de interpretação errada de `valor_momento_venda` como preço unitário (ver seção 5, correção completa). A fórmula certa (`SUM(valor_momento_venda)` sem multiplicar por quantidade) bate exato em 38.049 de 38.049 pedidos com item.
4. ~~**1 pedido (`id=21131`) com erro grave de quantidade**~~ — **RETIRADO, 2026-07-22**: não é erro de digitação, é pedido válido (900un × R$36,00 = R$32.400,00 total, batendo exato com `valor_total`). Não existem "centenas de outros pedidos" com esse problema — não havia problema nenhum.
5. **3 pedidos "vazios"** (sem nenhuma linha em `pedido_produto`), `valor_total=0`.
6. **`pedido_parcela`**: 0 linhas para o estab 4 (as 8 linhas existentes no banco inteiro pertencem ao estab 3) — tabela irrelevante para esta migração.
7. **`categoria_cliente`/`categoria_cliente_cliente`**: vazias no dump inteiro, apesar de `AUTO_INCREMENT` indicar uso histórico intenso (87.980) — dado foi apagado fisicamente em algum momento, fora do padrão "nunca apaga, só `ativo=0`" do resto do schema.
8. **`json`**: contém só histórico já coberto por `pedido`/`pedido_produto` (0 ids exclusivos) — seguro ignorar na migração.

## Decisões pendentes — precisam de consulta ao usuário antes de qualquer plano/script de migração

1. ~~**`valor_total` ≠ soma dos itens (13.971 pedidos)**~~ — **RESOLVIDO por correção de interpretação, não por decisão de negócio (2026-07-22)**: não havia divergência real (ver seção 5). `total_amount` migra como `pedido.valor_total` verbatim de qualquer forma (decisão de negócio do usuário, mantida), mas não há mais "achado de discrepância" a explicar.
2. ~~**Pedido `id=21131` e os ~740+ outros com discrepância grande de quantidade**~~ — **RESOLVIDO, não existiam** (ver seção 5).
3. **3 pedidos sem itens** (`id` 36195, 36492, 36533): migrar como pedido vazio, ou descartar?
4. **`orders.stock_location_id` é obrigatório e não existe no legado**: criar 1 `stock_location` default para o tenant antes de migrar os 38.052 pedidos?
5. **`orders.status`/`origin`**: sem equivalente no legado — qual regra determina `status` (confirmed/cancelled/etc.) a partir de `pago`+`entregue`+ausência de `ativo=0`? `origin` sempre `'staff'`?
6. **`orders.delivered_at` vs `expected_delivery_date`**: `pedido.data_entrega` do legado vira qual dos dois?
7. **`payments` (polimórfica)**: criar 1 `payment` sintético por pedido pago (sem `method` real, já que o legado não registra forma de pagamento), ou não popular `payments` e manter só `orders.is_paid`/`paid_amount`?
8. **`tenants.slug`**: gerar a partir de `nome` ("Js Queijos e Doces") — confirmar formato esperado (ex.: `js-queijos-e-doces`) e checar unicidade.
9. **`tenants.email`/`telefone`/`celular`/`whatsapp`/`facebook`/`instagram`**: não existe campo equivalente hoje em `tenants` nem `tenant_settings` — perder esses dados, ou pedir para adicionar campo(s) novos antes de migrar? (Todos preenchidos no legado para o estab 4, exceto `email`/`telefone` — não reproduzidos aqui por serem dado de contato do estabelecimento.)
10. **`clients.name` varchar(90) vs `cliente.nome` varchar(200)**: checar se algum dos 1913 clientes do estab 4 tem nome >90 caracteres (truncamento) antes de migrar — não verificado nesta análise.
11. **`products.price`/`clients`/etc. `float`→`decimal`**: aceitar conversão direta (risco de resíduo de arredondamento de ponto flutuante) ou normalizar/arredondar explicitamente durante a migração?
12. **`products.stock_quantity` NOT NULL default 0** vs `produto.quantidade` nullable: `NULL` do legado (sem controle de estoque) vira `0` (com controle, zerado) — isso muda o comportamento funcional do produto no sistema novo (bloqueio de venda sem estoque etc., ver `tenant_settings.block_order_without_stock`)? Precisa decisão explícita, não default silencioso.
13. **Papel/role dos 2 usuários do estabelecimento 4** no sistema novo (`tenant_roles`): qual role atribuir (Administrador do tenant, presumivelmente, mas não confirmado)?
14. **Senha dos usuários legados**: hash compatível com Laravel `Hash::check`, ou forçar reset de senha no primeiro acesso?
15. **`estados`/`cidades`/`bairros` do estab 4 com `unique(name)` composto no schema atual**: confirmar que não há colisão de nome antes de inserir (não verificado).

Nenhum desses pontos foi decidido nesta análise — ficam registrados para confirmação explícita antes do plano de migração (Passo 2).

## Passo 2 (2026-07-22) — dry-run implementado, todas as decisões acima fechadas com o usuário

Todos os 15 pontos pendentes foram decididos (ver histórico da tarefa): `total_amount` preservado como está (nunca recalculado, inclusive pedido 21131); pedidos com discrepância grande migram como estão; 3 pedidos vazios migram com `total_amount=0`; `stock_location` "Loja" criado como default; `status='confirmed'`/`origin='staff'` fixos; `data_entrega`→`delivered_at`; `payments` sintético 1-por-pedido-pago (`provider=manual`, `method=null`, `status=paid`); slug `js-queijos-e-doces`; plano Diamante; hash bcrypt do legado preservado literalmente; owner=Jefferson (usuario id=4), funcionária Jenifer (usuario id=5) com role customizada.

**Migration aditiva aplicada em produção** (`2026_07_22_150000_add_legacy_contact_fields_to_tenants_table.php`): `tenants.razao_social/email/phone/mobile_phone/whatsapp/facebook/instagram`, todas nullable — resolve a lacuna do ponto 9.

**Comando novo**: `php artisan legacy:migrate-estabelecimento {--dump=} {--commit}` (`api/app/Console/Commands/MigrateLegacyEstablishmentCommand.php` + parser dedicado `api/app/Console/Commands/Support/LegacyDumpParser.php`, tokenizer character-a-character do dump, sem depender de banco legado vivo). Sem `--commit`: só relatório, zero escrita — dry-run rodado em 2026-07-22 contra o dump real, TODAS as contagens/somas bateram exatamente com os números já validados independentemente (38.052 pedidos, 92.246 itens, 1.913 clientes, 176 produtos, 628 endereços, 48 bairros, 6 cidades, 1 estado, 31 dia_ideal, 2 usuários, `SUM(valor_total)`=R$4.217.946,33, `SUM(valor_pago)`=R$3.226.599,06, 35.857 pedidos pagos) — dump íntegro, parser correto. Nenhum `User`/`Tenant` pré-existente colide (e-mails/slug livres no banco de `.env` no momento do teste). `--commit` foi implementado por completo (schema/DTO reais, 1 única `DB::transaction()`, guard de idempotência por slug, reconciliação final por `SUM(orders.total_amount)` com `throw` se não bater) mas **NUNCA EXECUTADO** — só o dry-run rodou.

**Achado RESOLVIDO em 2026-07-22 (mesmo dia, revisão de coordenação)**: o parágrafo acima (removido) apontava a suposição de "`valor_momento_venda` = preço unitário" desta seção como contrariada por um bug documentado em `coding-standards.md` (2026-07-14, `ImportLegacyJsQueijosCommand`). Confirmado por parsing direto do dump contra os 38.052 pedidos do estab4: a suposição original ESTAVA ERRADA, o bug documentado é que estava certo — `valor_momento_venda` já é o TOTAL da linha. `MigrateLegacyEstablishmentCommand` foi corrigido: `line_total = valor_momento_venda` (direto), `unit_price = valor_momento_venda / quantidade_produto`. Reconciliação item-a-item embutida no dry-run confirma **38.049 de 38.049 pedidos-com-item batendo exato** (0 divergência) contra `orders.total_amount`. Ver seção 5 (reescrita) para o histórico completo do erro e da correção. `orders.total_amount` nunca mudou (sempre `pedido.valor_total` verbatim, decisão de negócio inalterada).

`composer test`: 843/843 (baseline preservado, sem regressão da migration nova em `tenants` nem da correção de fórmula).

**Comandos legados antigos, não usar**: `api/app/Console/Commands/Migration/MigrateJsQueijosEDocesCommand.php` (`migration:js-queijos-e-doces`) e `ImportLegacyJsQueijosCommand.php` (`import:legacy-js-queijos`) são tentativas anteriores desta mesma migração, de sessões passadas — `ImportLegacyJsQueijosCommand` tem `TARGET_TENANT_ID=2` hardcoded que hoje aponta para um tenant de demo diferente (não mais "Js Queijos e Doces"), não deve ser executado como está. O usuário confirmou seguir só com `legacy:migrate-estabelecimento` (este comando, desta sessão).

## Estágio 2 (2026-07-22) — ensaio completo com `--commit` contra banco descartável local, aprovado

Adicionada opção `{--database=}` ao comando (`config(['database.default' => $db]); DB::purge($db);` no início de `handle()`, com log explícito da conexão efetiva) — cofre de segurança para nunca escrever sem alvo explícito, reaproveitável para a rodada final em produção também.

**Setup do ensaio**: `sqlite` file descartável (`api/storage/app/rehearsal/legacy_rehearsal.sqlite`, fora do `.env`/`DB_HOST` real — nunca tocado), `php artisan migrate --database=sqlite` (todas as migrations do projeto já são sqlite-safe, guardadas por `DB::connection()->getDriverName()==='mysql'` nos poucos `ALTER...MODIFY` específicos de MySQL) + seeders `ActionsSeeder`/`FunctionalitiesSeeder`/`InitialPlansSeeder`/`PlanPricesSeeder` (plano `diamante` criado).

**2 bugs reais encontrados e corrigidos rodando o `--commit` de verdade pela primeira vez** (nenhum dos dois existia no dry-run porque dry-run não chama Services de domínio):
1. `TenantRoleService::create()`/`TenantProvisioningService` disparam eventos de auditoria com `actorId: Auth::id()` — `null` em contexto de console, e o construtor do evento exige `int`. Corrigido com o mesmo truque já usado em `DemoPlansPresentationSeeder::buildTenant()`: `Auth::setUser($ownerUser)` logo após criar o `User` do Jefferson, antes de qualquer chamada a Service de domínio.
2. `TenantRolePermissionService::syncPermissions()`/`assertBelongsToCurrentTenant()` dependem do binding `app('tenant_id')` que o middleware `tenant` (`ResolveTenant`) normalmente popula — inexistente em contexto de console. Corrigido com o mesmo truque de `DemoPlansPresentationSeeder`: `app()->instance('tenant'/'tenant_id'/'tenant_uuid', ...)` logo após criar o Tenant.

**Reconciliação completa do ensaio — tudo bateu exato**:
- `orders`: 38.052 (esperado 38.052) ✓
- `order_items`: 92.246 (esperado 92.246) ✓
- `payments`: 35.857 (esperado 35.857) ✓
- `clients`: 1.913 ✓, `products`: 176 ✓
- `SUM(orders.total_amount)`: R$4.217.946,33 exato ✓ (reconciliação dentro da própria transação também passou, sem `throw`)
- `orders.codigo`: 0 nulos, 38.052 valores distintos (sem colisão da unique `uniq_tenant_order_codigo`), `tenants.next_order_code` = 39.051 (999 + 38.052, consistente) — `orders:backfill-codigo` rodou certo dentro da transação
- **Reconciliação item-a-item** (`SUM(order_items.line_total)` por pedido vs `orders.total_amount`): **38.049 de 38.049 pedidos-com-item exatos, 0 divergência**, 3 pedidos vazios (os mesmos IDs já esperados) — confirma a fórmula corrigida (ver achado acima) na escrita real, não só no dry-run
- Jefferson (owner): `User` criado com hash bcrypt preservado literalmente (`password_get_info()` confirma bcrypt válido, `Hash::check()` rejeita senha errada corretamente — hash utilizável), vinculado ao role `owner` (896 permissões = cartesiano completo functionality×action do plano Diamante)
- Jenifer (funcionária): `User` criado, role `funcionario` com EXATAMENTE as 10 permissões pedidas (`orders:read/create/deliver/pay`, `clients:read/create/update`, `products:read`, `dashboard:read`, `reports:read`), nem mais nem menos
- `tenant_settings.block_order_without_stock` = `false` ✓; `stock_locations`: 1 registro "Loja", `is_default=true` ✓

Banco descartável destruído após validação (`rm` do arquivo sqlite). `composer test` rodado de novo depois de descartar: 843/843, ambiente principal (`.env`/`DB_HOST` real) confirmadamente intocado (contagem de `tenants`/`orders` idêntica antes/depois: 3 tenants, 28 orders).

**Ainda NÃO rodado contra `DB_HOST` real** — próxima rodada, só após o usuário confirmar explicitamente à luz deste resultado.
