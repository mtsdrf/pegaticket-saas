# Database Analysis — Relationships Map

Todos os relacionamentos abaixo são **Confirmado pelo banco** (FKs explícitas no dump, seção "Restrições para tabelas"). Nenhum relacionamento por convenção de nome sem FK foi encontrado — o schema é consistente nesse ponto.

## Auditoria (padrão universal, omitido nas tabelas abaixo)
Toda tabela com colunas de auditoria tem 2 FKs para `usuario`:
`inclusao_usuario_id → usuario.id`, `alteracao_usuario_id → usuario.id`.
`usuario` também referencia a si mesma dessa forma (auto-relacionamento).

## Multi-tenant (padrão universal, omitido nas tabelas abaixo)
Toda tabela com `estabelecimento_id` tem FK `estabelecimento_id → estabelecimento.id`.

## Relacionamentos de domínio

| De | Para | Tipo | Coluna FK | Cardinalidade | Laravel sugerido |
|---|---|---|---|---|---|
| `usuario` | `tipo_usuario` | belongsTo | `usuario.tipo_id` | N:1 | `Usuario::tipoUsuario()` |
| `cidade` | `estado` | belongsTo | `cidade.estado_id` | N:1 | `Cidade::estado()` |
| `bairro` | `cidade` | belongsTo | `bairro.cidade_id` | N:1 | `Bairro::cidade()` |
| `endereco` | `estado`, `cidade`, `bairro` | belongsTo (×3, denormalizado) | `endereco.estado_id/cidade_id/bairro_id` | N:1 cada | `Endereco::estado()/cidade()/bairro()` |
| `cliente` | `endereco` | belongsTo | `cliente.endereco_id` | N:1 | `Cliente::endereco()` |
| `cliente` | `dia_ideal` | belongsTo (nullable) | `cliente.dia_ideal_id` | N:1 | `Cliente::diaIdeal()` |
| `cliente` | `periodo_ideal` | belongsTo (nullable) | `cliente.periodo_ideal_id` | N:1 | `Cliente::periodoIdeal()` |
| `cliente` ↔ `categoria_cliente` | via `categoria_cliente_cliente` | belongsToMany | `categoria_cliente_cliente.{cliente_id,categoria_cliente_id}` | N:N | `Cliente::categorias()` / `CategoriaCliente::clientes()` |
| `tipo_produto` | `categoria_produto` | belongsTo | `tipo_produto.categoria_produto_id` | N:1 | `TipoProduto::categoriaProduto()` |
| `produto` | `tipo_produto` | belongsTo | `produto.tipo_produto_id` | N:1 | `Produto::tipoProduto()` |
| `pedido` | `cliente` | belongsTo | `pedido.cliente_id` | N:1 | `Pedido::cliente()` |
| `pedido_produto` | `pedido` | belongsTo | `pedido_produto.pedido_id` | N:1 | `PedidoProduto::pedido()` |
| `pedido_produto` | `produto` | belongsTo | `pedido_produto.produto_id` | N:1 | `PedidoProduto::produto()` |
| `pedido` | `pedido_produto` | hasMany | (inverso da linha acima) | 1:N | `Pedido::itens()` |
| `pedido_parcela` | `pedido` | belongsTo | `pedido_parcela.pedido_id` | N:1 | `PedidoParcela::pedido()` |
| `pedido` | `pedido_parcela` | hasMany | (inverso da linha acima) | 1:N | `Pedido::parcelas()` |
| `usuario` | `estabelecimento` | belongsTo | `usuario.estabelecimento_id` | N:1 | `Usuario::estabelecimento()` |

## Tabela pivô — `categoria_cliente_cliente`

- Entidade A: `cliente`. Entidade B: `categoria_cliente`.
- Cardinalidade: N:N.
- Campos adicionais na relação: nenhum além dos de auditoria/tenant padrão (não carrega dado próprio como "data de atribuição" ou "peso da categoria").
- Precisa de model própria? **Não obrigatoriamente** — é um pivot simples (`belongsToMany` com `withTimestamps`/`withPivot` mínimo), mas como tem `id`/`uuid`/auditoria própria, no Eloquent isso normalmente vira uma tabela pivô "rica" com `Model` dedicado (`->using(CategoriaClienteCliente::class)`) para não perder essas colunas — decisão de implementação, não de modelagem (marcar para Laravel PHP Master).

## `pedido_produto` — pivô ou entidade?

Tecnicamente é uma tabela de associação `pedido` × `produto`, mas carrega dado de negócio real (`valor_momento_venda`, `quantidade_produto`) que não existe nem em `pedido` nem em `produto`. **Recomendação: tratar como entidade própria (model `PedidoProduto`/`PedidoItem`), não como pivot Eloquent simples** — é o padrão line-item de pedido, não uma associação genérica.

## Dependências entre módulos (para ordem de implementação)

```
estabelecimento (raiz)
  → tipo_usuario (global, sem dependência de estabelecimento)
  → usuario (depende de estabelecimento + tipo_usuario)
  → estado → cidade → bairro → endereco (cadeia de localização, cada nível depende do anterior)
  → dia_ideal, periodo_ideal, categoria_cliente (domínio simples, só depende de estabelecimento)
  → cliente (depende de endereco, dia_ideal, periodo_ideal)
  → categoria_cliente_cliente (depende de cliente + categoria_cliente)
  → categoria_produto → tipo_produto → produto (cadeia de catálogo)
  → pedido (depende de cliente)
  → pedido_produto (depende de pedido + produto)
  → pedido_parcela (depende de pedido)
```

Nenhuma dependência circular encontrada. `cliente_novo` e `json` não têm FK, portanto não entram nessa cadeia.
