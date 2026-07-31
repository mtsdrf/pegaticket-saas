# Database Analysis — Modules Map

7 módulos identificados a partir das 20 tabelas do `dump_base.sql`.

---

### Módulo: Estabelecimento (Tenant)
Tabelas envolvidas: `estabelecimento`
Funcionalidades prováveis:
- Cadastrar/editar dados do estabelecimento (nome, razão social, CNPJ, contatos, redes sociais, logo).
- Ativar/desativar estabelecimento.

Confiança: Alta
Base da análise: Confirmado por estrutura (é a raiz de tudo, referenciada por 18 outras tabelas).

---

### Módulo: Segurança / Acesso
Tabelas envolvidas: `usuario`, `tipo_usuario`
Funcionalidades prováveis:
- Login de usuário.
- CRUD de usuários por estabelecimento.
- CRUD de tipos/papéis de usuário (global).
- Controle de acesso por tipo de usuário (papel único por usuário, não granular por ação).

Confiança: Alta
Base da análise: Confirmado por FKs e ausência de tabela de permissão granular (diferente do novo sistema, que tem Functionality/Action/Group).

---

### Módulo: Localização
Tabelas envolvidas: `estado`, `cidade`, `bairro`, `endereco`
Funcionalidades prováveis:
- Cadastro de estado/cidade/bairro por estabelecimento (não compartilhado entre estabelecimentos).
- Cadastro de endereço (logradouro + CEP) vinculado a bairro/cidade/estado.
- Usado como base para o endereço do cliente.

Confiança: Alta
Base da análise: Confirmado por FKs em cadeia (estado→cidade→bairro→endereço) e uso por `cliente`.

---

### Módulo: Clientes
Tabelas envolvidas: `cliente`, `categoria_cliente`, `categoria_cliente_cliente`, `dia_ideal`, `periodo_ideal`, `cliente_novo`
Funcionalidades prováveis:
- Listar/criar/editar/inativar cliente.
- Vincular cliente a endereço (rua + número + complemento).
- Definir dia e período ideal de contato/entrega do cliente.
- Classificar cliente por categoria (N:N) — segmentação para campanhas/rotas.
- Marcar "confiança" do cliente (provável indicador de risco de crédito).
- Receber leads de formulário público (`cliente_novo`) e convertê-los em cliente completo.

Confiança: Alta (estrutura de cliente/categoria/localização) / Média (propósito exato de `dia_ideal`/`periodo_ideal`/`confianca`/`cliente_novo` — inferido pelo nome, não confirmado por regra explícita no banco).
Base da análise: Confirmado por FKs; significado de negócio é inferência.

---

### Módulo: Catálogo de Produtos
Tabelas envolvidas: `produto`, `tipo_produto`, `categoria_produto`
Funcionalidades prováveis:
- CRUD de categoria de produto (com ordenação/prioridade).
- CRUD de tipo de produto, vinculado a uma categoria (com ordenação/prioridade).
- CRUD de produto: nome, valor, descrição, imagem, disponibilidade, estoque (opcional), taxa de acréscimo (venda a prazo).
- Listagem de catálogo por categoria/tipo, filtrando por disponibilidade.

Confiança: Alta
Base da análise: Confirmado por FKs e colunas (`disponivel`, `quantidade`, `taxa_acrescimo`).

---

### Módulo: Pedidos / Vendas
Tabelas envolvidas: `pedido`, `pedido_produto`, `pedido_parcela`
Funcionalidades prováveis:
- Criar pedido para um cliente, com um ou mais produtos (preço travado no momento da venda).
- Marcar pedido como entregue / registrar data de entrega.
- Vender à vista ou parcelado (`parcelado`); se parcelado, gerar `pedido_parcela` por parcela.
- Registrar pagamento (total ou por parcela), com data e valor pago — inclui pagamento parcial.
- Consultar histórico de pedidos por cliente, incluindo saldo devedor calculado.
- Ações dedicadas de update parcial: marcar entregue, marcar pago (sem reenviar o pedido inteiro).
- **Exclusão de pedido é proibida** (confirmado pelo código-fonte — rota comentada). Só atualização.
- Vencimento de parcela não paga = dia 10 do mês seguinte à criação (confirmado pelo código-fonte, ver [[06-business-rules]]).
- Quando a última parcela de um pedido é paga, o pedido inteiro vira `entregue=true, pago=true` automaticamente (cascata confirmada pelo código-fonte).

Confiança: Alta — **confirmado pelo código-fonte** (não mais inferência), ver [[06-business-rules]].
Base da análise: Confirmado por FKs, colunas e lógica de `Pedido/CadastrarController`, `Pedido/AlterarController`, `PedidoParcela/CadastrarController`.

---

### Módulo: Relatórios e Indicadores
Tabelas envolvidas: `pedido`, `pedido_produto`, `pedido_parcela`, `cliente`, `endereco`/`cidade` (leitura agregada, nenhuma tabela própria)
Funcionalidades confirmadas pelo código-fonte (`Relatorio/PesquisarController`, `Home/PesquisarController`):
- Indicadores: total de pedidos, entregues/não entregues, pagos/não pagos, valor recebido, valor a receber — com filtro de período.
- Gráficos: pedidos por mês, pagos×não pagos, entregues×não entregues, recebido×a receber, pedidos por cidade.
- Listagem de pedidos com filtro (cliente, cidade, bairro, endereço, pago, entregue, período) para relatório.
- Listagem de clientes "adimplentes" (todos os pedidos pagos e entregues) com filtro de localização.
- **Exportação em PDF** de relatório de pedidos e de clientes (via `barryvdh/laravel-dompdf`).
- Home/Dashboard usa um subconjunto desses indicadores com filtro fixo de "última semana".

Confiança: Alta — confirmado pelo código-fonte.
Base da análise: `Relatorio/PesquisarController::{pesquisarIndicadores,pesquisarGraficos,pesquisarPedidos,pesquisarClientes,gerarRelatorioPdf}`, `Home/PesquisarController::pesquisar`.
**Risco confirmado**: os endpoints de listagem (`pesquisarPedidos`, `pesquisarClientes`) montam SQL por concatenação de string com valor de request — SQL injection real no legado, não replicar (ver [[06-business-rules]]).

---

### Módulo: Auditoria / Sistema
Tabelas envolvidas: `json`
Funcionalidades prováveis:
- Log genérico de alterações por tabela (nome da tabela + snapshot json).

Confiança: Baixa como fonte de auditoria confiável (não referencia linha, usuário ou tipo de ação).
Base da análise: Confirmado pelo banco que a tabela existe com esse propósito (comentários de coluna), mas a suficiência funcional é avaliação nossa, não do banco.

---

## Resumo de prioridade por módulo (ver detalhamento em [[05-crud-plan]])

| Módulo | Prioridade |
|---|---|
| Segurança/Acesso | Alta |
| Estabelecimento | Alta |
| Clientes | Alta |
| Catálogo de Produtos | Alta |
| Pedidos/Vendas | Alta |
| Relatórios e Indicadores | Alta (dashboard já tem placeholder aguardando isso) |
| Localização | Média |
| Auditoria/Sistema | Baixa (substituir pelo `AuditLog` já existente no novo sistema, não portar) |
