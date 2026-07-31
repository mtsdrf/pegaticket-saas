# Roteiro do vídeo — Apresentação do sistema PegaTicket

Roteiro passo a passo pra gravar um vídeo mostrando o sistema inteiro, usando a empresa de demonstração principal, com o plano unico atual liberando todas as funcionalidades.

## 0. Preparação antes de gravar

**Credenciais das empresas demo** (senha igual pra todas: `PegaTicket@2026`):

| Empresa | Plano | Dono | Funcionário |
|---|---|---|---|
| Operacao Demo PegaTicket | PegaTicket | dono.demo@pegaticket.com | funcionario.demo@pegaticket.com |

**Antes de ligar a gravação:**
- Navegador em 100% de zoom, sem extensões visíveis na barra, aba anônima/perfil limpo (evita favoritos/histórico pessoal aparecendo).
- Feche qualquer outra aba com dado real de cliente (é ambiente compartilhado — confirme que só as abas de demo estão abertas).
- Tenha os 2 logins acima já copiados num bloco de notas à parte, pra não digitar errado ao vivo.
- Ordem sugerida de gravação: Login → Operacao interna → Loja pública/Portal do cliente final → Fiscal/contador/assinatura → Encerramento.

---

## 1. Abertura — Login e identidade visual

1. Acesse a tela de login do sistema.
2. Aponte a headline **"Bem-vindo ao PegaTicket"** e a tagline **"Gestão clara para empresas em movimento."**
3. Clique no ícone de sol/lua (canto inferior direito) — mostre a transição entre tema claro e escuro. Isso existe em toda tela do sistema (login, loja, portal do cliente, área do contador), não só aqui.
4. Faça login com `dono.demo@pegaticket.com`.

**O que explicar:** o sistema é multi-empresa — um mesmo usuário pode ter acesso a mais de uma empresa, e o que cada tela mostra depende do plano contratado.

---

## 2. Visão geral (Dashboard)

Tela inicial após login (`/`).

1. Aponte o título **"Visão geral"** e o subtítulo **"Acompanhe os principais números da operação."**
2. Ações rápidas no topo: **Novo pedido / Adicionar cliente / Cadastrar produto** — atalho direto pras 3 tarefas mais comuns do dia a dia.
3. 3 cards de métrica: **Pedidos entregues / Pedidos pendentes / Valor recebido** — números reais da empresa logada.
4. Gráfico de pedidos por mês, abaixo dos cards.
5. Repare no menu lateral esquerdo — ele concentra toda a operação atual no mesmo plano.

---

## 3. Cadastros de Clientes

Menu lateral → **Clientes** (grupo).

1. **Clientes → Categorias de cliente** (`/clientes/categorias`): explique que serve pra agrupar clientes por perfil (ex.: "Atacado", "Varejo", "VIP") — usado depois em preço diferenciado e relatórios.
2. **Clientes → Dias ideais** (`/clientes/dias-ideais`): dia da semana em que aquele cliente prefere ser visitado/atendido (ex.: "Segunda", "Quarta") — ajuda o vendedor a organizar a rota de visitas.
3. **Clientes → Períodos ideais** (`/clientes/periodos-ideais`): turno preferido (manhã/tarde/noite) — usado junto com o dia ideal.
4. **Clientes → Clientes** (`/clientes`): cadastro completo — nome, telefone(s), categoria, dia/período ideal, endereço, se é cliente de confiança (`is_trusted`, afeta liberação de pedido sem pagamento antecipado). Abra 1 cliente de exemplo e mostre os campos preenchidos.

---

## 4. Cadastros de Endereço

Menu lateral → **Endereço** (grupo). São 4 telas em cascata — Estado contém Cidade, Cidade contém Bairro, e o Endereço em si junta tudo com rua/número/complemento/CEP.

1. **Estados** (`/estados`), **Cidades** (`/cidades`), **Bairros** (`/bairros`) — cadastro geográfico, compartilhado entre todas as empresas do sistema (não duplica Araraquara/SP pra cada cliente novo).
2. **Endereços** (`/enderecos`) — o endereço físico completo (rua, número, complemento, CEP), vinculado a um bairro.

**O que explicar:** essa estrutura evita erro de digitação de cidade/bairro e alimenta os mapas de rota e cálculo de taxa de entrega mais adiante.

---

## 5. Cadastros de Produto

Menu lateral → **Produto** (grupo).

1. **Categorias de produto** (`/produtos/categorias`) — agrupamento amplo (ex.: "Queijos", "Doces", "Bebidas").
2. **Tipos de produto** (`/produtos/tipos`) — subdivisão dentro da categoria (ex.: dentro de "Queijos": "Frescos", "Curados").
3. **Produtos** (`/produtos`) — cadastro final: nome, descrição, foto, preço, se está disponível pra venda, preço de atacado (se configurado), SKU/código de barras. Abra 1 produto de exemplo.

---

## 6. Pedidos

Menu lateral → **Pedidos** (`/pedidos`).

1. **Pedidos**: lançados manualmente pela equipe (venda por telefone/WhatsApp, por exemplo). Mostre a lista e abra 1 pedido — itens, valor total, status de pagamento e de entrega.
2. Clique em **Novo pedido** (uma das ações rápidas do Dashboard) e monte um pedido ao vivo: escolha cliente, adicione produtos, confirme.

---

## 7. Relatórios

Menu lateral → **Relatórios** (grupo).

1. **Análises** (`/analises`) — indicadores agregados.
2. **Relatório de pedidos** (`/relatorios/pedidos`) — filtros por período/status, exportável.
3. **Base de clientes** (`/relatorios/clientes`) — listagem/exportação de clientes.
4. **Recebíveis** (`/relatorios/recebiveis`) — o que já foi recebido vs. o que ainda está em aberto.

---

Faça logout para seguir para a perspectiva publica da loja.

---

## 8. Loja online e operação omnichannel

Faça login novamente com `dono.demo@pegaticket.com`. Aponte que o mesmo plano já inclui **Estoque**, **Pedidos da Loja**, **Loja online**, **Montar rota** e **Análises**.

### Estoque
Menu lateral → **Estoque** (grupo).
1. **Locais de estoque** (`/estoque/locais`) — a empresa pode ter mais de 1 depósito/filial.
2. **Saldos** (`/estoque/saldos`) — quantidade disponível de cada produto, por local.
3. **Movimentações** (`/estoque/movimentos`) — histórico de entrada/saída/ajuste/transferência.

### Loja online
Menu lateral → **Loja online** (`/configuracoes/loja-online`).
1. Mostre a configuração do catálogo público: horário de funcionamento, taxa de entrega por bairro, valor mínimo de pedido e tempo estimado de preparo.
2. Em seguida, abra **Pedidos da Loja** (`/pedidos-loja`) e explique que essa é a fila dedicada para aprovar, despachar, entregar e receber pedidos vindos do canal online.

### Montar rota
Menu lateral → **Montar rota** (`/rotas`) — monta o roteiro de entrega do dia, agrupando pedidos por região/bairro.

Faça logout para seguir para a experiencia publica do cliente final.

---

## 9. Loja pública + Portal do cliente final

Ainda logado como visitante (sem conta), acesse a URL da loja da empresa demo (`/loja/<slug-da-empresa>`).

1. Mostre o catálogo público — produtos organizados por categoria, sem precisar de login.
2. Adicione um produto ao carrinho, avance até o checkout.
3. Finalize um pedido de teste (ou mostre até a etapa de confirmação, sem precisar concluir pagamento real).
4. Acesse `/portal/entrar` — aqui é onde o **cliente final** (não a equipe da empresa) acompanha os próprios pedidos depois de comprar: histórico de pedidos, favoritos, endereços salvos e perfil.

**O que explicar:** são 2 públicos completamente diferentes — a equipe da empresa usa o sistema principal, e o cliente final da empresa usa só essa área pública + o Portal, sem nunca ver o painel administrativo.

---

## 10. Fiscal, contador e assinatura

Faça login com `dono.demo@pegaticket.com`. Aponte que o mesmo plano unico tambem inclui os blocos de **contabilidade**, **fiscal** e governanca avancada da operacao, alem da gestao de assinatura.

### Minha assinatura
Menu lateral → **Minha assinatura** (`/configuracoes/assinatura`) — onde o próprio dono da empresa gerencia a cobrança do PegaTicket: plano atual, período de cobrança (mensal/trimestral/anual), próxima fatura, cancelamento.

### Acesso de contadores
Menu lateral → **Acesso de contadores** (`/configuracoes/contadores`) — onde a empresa aprova o acesso de um escritório de contabilidade externo aos próprios relatórios financeiros (mostrado do outro lado na Seção 13).

### Configurações fiscais
Em **Configurações** (`/configuracoes`), mostre os campos de CNPJ/regime tributário/regras fiscais — preparação pra emissão de nota fiscal futura.

---

## 11. Módulo do Contador

Acesse `/contador/entrar` (é uma área separada, login próprio — o contador não usa o mesmo login da empresa).

1. Explique o fluxo: o escritório de contabilidade se cadastra (`/contador/cadastro`), confirma um código de segurança (autenticação em duas etapas), e depois solicita acesso a uma empresa cliente informando o CNPJ — a empresa precisa aprovar esse acesso (Seção 10, "Acesso de contadores").
2. Depois de aprovado, o contador vê **Empresas** (lista de clientes que autorizou acesso) e consegue consultar relatórios financeiros e trocar mensagens com a empresa — tudo sem nunca logar como se fosse a empresa.

---

## 12. Diferença de acesso — dono vs. funcionário

Faça logout e entre com `funcionario.demo@pegaticket.com`.

Mostre lado a lado (ou compare de memória com o que apareceu logado como dono) que o menu lateral do funcionário é bem mais enxuto — só o que a função dele precisa no dia a dia (pedidos, clientes, produtos), sem acesso a configurações, assinatura, usuários ou dados financeiros sensíveis.

**O que explicar:** cada empresa pode criar quantos perfis de funcionário quiser, escolhendo exatamente o que cada um pode ver/fazer — não é tudo ou nada.

---

## 13. Encerramento

Sugestão de fechamento: recapitule o plano unico em 3 frentes —

- **Operação**: pedidos, clientes, produtos, estoque e loja online no mesmo sistema.
- **Gestão**: relatórios, analytics, financeiro, rotas e configurações no mesmo pacote.
- **Governança**: assinatura, fiscal e contador já liberados no mesmo plano.

Encerre reforçando a tagline: **"Gestão clara para empresas em movimento."**
