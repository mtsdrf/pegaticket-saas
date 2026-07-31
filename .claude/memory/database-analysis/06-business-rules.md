# Database Analysis — Business Rules

Regras marcadas em 3 níveis de confiança: **Confirmado pelo código-fonte** (lido em `/home/mtsdrf/workspace/pegaticket/backend/app/` e `routes/api.php` — o mais confiável, é o comportamento real), **Confirmado pelo banco** (constraint/coluna do `dump_base.sql`) e **Inferência** (dedução sem prova direta).

> Atualizado em 2026-07-06 após acesso ao código-fonte completo do legado (`/home/mtsdrf/workspace/pegaticket/`, Laravel 8 + React 17, mesmo repo do `dump_base.sql` — confirmado pelo usuário). Isso resolveu a maior parte das dúvidas que estavam em [[07-implementation-roadmap]] como inferência.

## Confirmado pelo código-fonte

- **Vencimento de parcela**: não existe coluna dedicada, mas a regra existe na aplicação — ao criar um `pedido` não pago, `data_pagamento` é preenchida com **dia 10 do mês seguinte** (`Pedido/CadastrarController`), funcionando como "data de vencimento esperada" até o pagamento real substituir esse valor. Resolve a dúvida nº4 anterior.
- **`pedido.entregue` default 1 no banco é irrelevante na prática**: a aplicação sempre define `entregue` explicitamente a partir do request tanto no create quanto no update — o default do banco nunca é exercitado pelo fluxo normal. Não precisa ser replicado no novo modelo.
- **Pedido não pode ser excluído**: a rota `DELETE pedido/{pedido}` está **comentada** em `routes/api.php` — exclusão de pedido foi deliberadamente desabilitada no legado. Só existem ações de update (`alterar`, `alterarEntregue`, `alterarPago`).
- **Cascata de quitação por parcela**: ao cadastrar uma nova `pedido_parcela`, se **todas** as parcelas daquele pedido já estiverem pagas (incluindo a nova), o `pedido` inteiro é automaticamente marcado `entregue=true, pago=true, valor_pago=valor_total` (`PedidoParcela/CadastrarController`). Regra de negócio real, não óbvia pelo schema.
- **Saldo devedor do cliente**: calculado sob demanda (não hay coluna), somando por cliente: se `parcelado`, `valor_total - soma(valor_pago das parcelas)`; senão, `valor_total - valor_pago` do próprio pedido (`Cliente/PesquisarController::pedidos`/`clientePedido`).
- **`cliente.telefone_principal` NÃO é obrigatório** — `Cliente/ValidarController` só exige `nome`, `endereco_id`, `ativo`. Corrige inferência anterior ("pelo menos um telefone").
- **Indicadores de dashboard confirmados** (`Relatorio/PesquisarController::pesquisarIndicadores`, usados também no `Home/PesquisarController` para a tela inicial): total de pedidos, pedidos entregues/não entregues, pedidos pagos/não pagos, **valor_recebido** (soma `valor_pago` dos pagos) e **valor_a_receber** (soma `valor_total` dos não pagos) — com filtro opcional de período. Bate exatamente com os 3 cards que já estavam no placeholder do `DashboardPage.tsx` (Pedidos entregues / Pedidos pendentes / Valor recebido) — confirma que a direção estava certa, e revela 2 métricas a mais (pagos/não pagos, a receber).
- **Gráficos confirmados** (`Relatorio/PesquisarController::pesquisarGraficos`): pedidos por mês (linha/barra), pedidos pagos×não pagos (pizza), pedidos entregues×não entregues (pizza), valor recebido×a receber (pizza), **pedidos por cidade** (barra, via join pedido→cliente→endereco→cidade). Todos calculados sob demanda via query, nenhum vem de view.
- **Relatório em PDF é um módulo real**, não estava no plano anterior: `Relatorio/PesquisarController::gerarRelatorioPdf` gera PDF (via `barryvdh/laravel-dompdf`) de dois tipos — "pedidos" e "clientes" — a partir de uma view Blade (`relatorio_pedido_pdf`, `relatorio_cliente_pdf`).
- **Super-admin cross-tenant existia no legado**: `env('ESTABELECIMENTO_ADMIN_ID')` era um id de estabelecimento especial que, quando era o do usuário logado, removia o filtro de tenant em praticamente toda query de relatório/listagem. **Decisão do usuário (2026-07-06): esse conceito não vai existir no novo sistema.** Todo usuário/tenant fica estritamente isolado, sem bypass cross-tenant algum — ver [[architecture-decisions]]. Não portar esse padrão em nenhuma query nova (`Cliente/PesquisarController`, `Relatorio/*`, etc. do legado dependiam disso — a versão nova simplesmente sempre filtra por tenant, sem exceção).
- **Auth é padrão Laravel + tymon/jwt-auth**: guard `api` → driver `jwt`, model `Usuario implements JWTSubject`. `getJWTCustomClaims()` retorna vazio — **`estabelecimento_id`/`tipo_id` não vão dentro do JWT**, são sempre relidos do banco via `$request->user()` a cada request (diferente do novo sistema, que embute `tenant_id`/`tenant_uuid` como custom claims). O login devolve `tipo_id`/`estabelecimento_id` **criptografados** (`Crypt::encryptString`) no corpo da resposta só para uso de exibição no frontend — a autorização real sempre acontece no backend, então isso não é um risco de segurança, é só decorativo.
- **Resolução de tenant** é uma função de 3 linhas (`UtilidadeController::buscaEstabelecimento`): simplesmente `$request->user()->estabelecimento_id`. Nenhuma lógica de troca de tenant (não existe, porque no legado 1 usuário = 1 estabelecimento fixo).
- **Listagem de cliente sem paginação**: `Cliente/PesquisarController::pesquisar` faz `->get()` de todos os clientes do estabelecimento de uma vez, ordenado por nome. Com quase 2000 clientes já cadastrados (`AUTO_INCREMENT=1990`), isso é um risco de performance que **não deve ser replicado** — o novo CRUD de Cliente precisa paginar desde o início.
- **Risco de segurança confirmado (não replicar)**: `Relatorio/PesquisarController::pesquisarPedidos` e `::pesquisarClientes` montam SQL via **concatenação de string com valores do request direto na query** (`$select .= " AND pedido.cliente_id = ".$cliente_id;`), sem bind de parâmetro — **SQL injection real** no código legado. O novo sistema deve usar Query Builder/Eloquent com binding em todos os filtros de relatório, nunca concatenar.
- **Frontend legado**: React 17 + `react-router-dom` v5 + Bootstrap 4/`react-bootstrap`/`reactstrap` + Formik+Yup (formulário) + Redux + `react-query` + Chart.js v3/`react-chartjs-2` + `@nadavshaar/react-grid-table` (grid próprio, não ag-Grid) + `react-select`, `react-date-picker`, `react-text-mask` (máscaras) + `crypto-js` (decripta o `tipo_id`/`estabelecimento_id` do login no cliente). Stack antiga, **não reaproveitável como código** (Router v5, Redux, Bootstrap — tudo diferente da stack nova), mas a **lista de telas por página é o inventário definitivo de funcionalidades**: `Pages/{Auth,Bairro,CategoriaCliente,CategoriaProduto,Cidade,Cliente,DiaIdeal,Endereco,Estabelecimento,Estado,Home,Pedido,PeriodoIdeal,Produto,Relatorio,TipoProduto,TipoUsuario,Usuario}`.
- **Controllers legados seguem 1 ação por classe** (`Cadastrar/Alterar/Deletar/Pesquisar/Validar/VerificarDuplicidade` por entidade) — confirma que toda entidade tem validação de duplicidade dedicada (`VerificarDuplicidadeController`) além da validação de campo — provavelmente checagem de nome único por estabelecimento antes de salvar. Não há constraint `UNIQUE` no banco para isso (nenhuma tabela de domínio tem unique de nome) — é validação só de aplicação.

## Confirmado pelo banco (sem mudança desde a versão anterior)

- Toda tabela de domínio tem `ativo` (default 1) — desativação é sempre lógica, nunca há `deleted_at`/exclusão física modelada.
- `estabelecimento_id` obrigatório (`NOT NULL`) em toda tabela de domínio.
- `inclusao_usuario_id NOT NULL` em toda tabela auditada.
- FKs sem `ON DELETE CASCADE`/`SET NULL` explícito → exclusão do "pai" é bloqueada se houver "filho".
- `cliente.numero`/`complemento` ficam no cliente, não no `endereco`.
- `pedido_produto.valor_momento_venda` é snapshot independente de `produto.valor`.
- `pedido_produto.quantidade_produto` é `float` (permite fração).
- `tipo_usuario` é a única tabela de domínio sem `estabelecimento_id` (papel global).
- `usuario.uuid` é `varchar(200)` vs `varchar(40)` das demais — inconsistência de schema.
- Charset misto `latin1`/`utf8mb4` — risco de mojibake numa migração de dados real.
- Nenhum índice de busca textual — busca por nome é `LIKE` sem otimização.
- `estabelecimento.celular` é o único contato `NOT NULL`.
- `produto.quantidade` é nullable.

## Inferência restante (não resolvida pelo código lido)

- **Modelo de negócio "porta-a-porta"** (`dia_ideal`/`periodo_ideal`/`confianca`): o código confirma que esses campos existem e são usados no cadastro de cliente, mas não vi regra de negócio que os *consuma* ativamente (ex.: nenhuma tela de "rota do dia" nos controllers lidos). Podem ser só metadados informativos preenchidos manualmente, sem automação por trás. **Ainda vale confirmar com o usuário** se há uso funcional real (ex.: relatório/filtro por dia ideal) além do CRUD de cliente.
- **`usuario.password`**: algoritmo de hash não confirmado pelo código lido (não vi o `AuthController::login` chamando `Hash::make`/`bcrypt` diretamente — usa `auth()->attempt()` padrão do Laravel, que assume o hash configurado no guard). Presumivelmente `bcrypt` padrão do Laravel 8, mas **confirmar antes de migrar senhas reais**.

## Riscos a levar para o plano de implementação (atualizado)

1. Modelo de tenant: legado é 1 usuário : 1 estabelecimento fixo (sem troca), novo sistema já suporta N:N com troca — **migração é estritamente uma simplificação para um caso específico do modelo novo**, não confirmado gerar problema.
2. `tipo_usuario` (papel único, global, só um nome) vs RBAC granular novo — decisão de mapeamento ainda pendente, mas o legado não tem granularidade de permissão por ação (é literalmente só um nome de papel, sem tabela de permissão associada) — plausível mapear para `Group` simples inicialmente.
3. ~~Super-admin cross-tenant~~ — **decidido**: não existirá no novo sistema (2026-07-06). Nenhuma query nova deve ter bypass de tenant.
4. Charset `latin1`/`utf8mb4` misto — conversão obrigatória numa migração de dados real.
5. Imagens em BLOB (`estabelecimento`, `produto`) — extrair para arquivo/S3 antes de migrar dados reais.
6. **SQL injection nos relatórios legados** — ao implementar o módulo de Relatórios no novo sistema, replicar a *funcionalidade*, nunca o padrão de concatenação de SQL.
7. **Paginação obrigatória** em Cliente (e provavelmente Produto/Pedido) desde o primeiro CRUD novo — o legado já opera com ~2000 clientes sem paginar, o novo não deve repetir isso.
8. Validação de duplicidade (nome único por estabelecimento) existe em toda entidade no legado só via `Validator` de aplicação, sem constraint no banco — se for regra real de negócio, o novo sistema deveria considerar constraint `UNIQUE` composta (`estabelecimento_id`, `nome`) no banco, não só validação de app.
