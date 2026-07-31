# Roteiro do vídeo — Apresentação do sistema PegaTicket

Roteiro passo a passo pra gravar um vídeo mostrando o sistema inteiro, usando as 3 empresas de demonstração já criadas (uma por plano), pra também deixar clara a diferença entre Prata/Ouro/Diamante durante a gravação.

## 0. Preparação antes de gravar

**Credenciais das empresas demo** (senha igual pra todas: `PegaTicket@2026`):

| Empresa | Plano | Dono | Funcionário |
|---|---|---|---|
| Cafeteria Prata Demo | Prata | dono.prata@pegaticket.com | funcionario.prata@pegaticket.com |
| Cafeteria Ouro Demo | Ouro | dono.ouro@pegaticket.com | funcionario.ouro@pegaticket.com |
| Restaurante Diamante Demo | Diamante | dono.diamante@pegaticket.com | funcionario.diamante@pegaticket.com |

**Antes de ligar a gravação:**
- Navegador em 100% de zoom, sem extensões visíveis na barra, aba anônima/perfil limpo (evita favoritos/histórico pessoal aparecendo).
- Feche qualquer outra aba com dado real de cliente (é ambiente compartilhado — confirme que só as abas de demo estão abertas).
- Tenha os 6 logins acima já copiados num bloco de notas à parte, pra não digitar errado ao vivo.
- Ordem sugerida de gravação: Login → Prata → Ouro → Diamante → Loja pública/Portal do cliente final → Módulo do contador → Encerramento. Cada seção abaixo já segue essa ordem.

---

## 1. Abertura — Login e identidade visual

1. Acesse a tela de login do sistema.
2. Aponte a headline **"Bem-vindo ao PegaTicket"** e a tagline **"Gestão clara para empresas em movimento."**
3. Clique no ícone de sol/lua (canto inferior direito) — mostre a transição entre tema claro e escuro. Isso existe em toda tela do sistema (login, loja, portal do cliente, área do contador), não só aqui.
4. Faça login com `dono.prata@pegaticket.com`.

**O que explicar:** o sistema é multi-empresa — um mesmo usuário pode ter acesso a mais de uma empresa, e o que cada tela mostra depende do plano contratado.

---

## 2. Plano Prata — Visão geral (Dashboard)

Tela inicial após login (`/`).

1. Aponte o título **"Visão geral"** e o subtítulo **"Acompanhe os principais números da operação."**
2. Ações rápidas no topo: **Novo pedido / Adicionar cliente / Cadastrar produto** — atalho direto pras 3 tarefas mais comuns do dia a dia.
3. 3 cards de métrica: **Pedidos entregues / Pedidos pendentes / Valor recebido** — números reais da empresa logada.
4. Gráfico de pedidos por mês, abaixo dos cards.
5. Repare no menu lateral esquerdo — é ele que muda de acordo com o plano. No Prata, é mais enxuto.

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

Menu lateral → **Pedidos** (`/pedidos`) e **Pedidos da Loja** (`/pedidos-loja`).

1. **Pedidos**: lançados manualmente pela equipe (venda por telefone/WhatsApp, por exemplo). Mostre a lista e abra 1 pedido — itens, valor total, status de pagamento e de entrega.
2. **Pedidos da Loja**: pedidos que vieram pelo catálogo público (Seção 10 mostra o catálogo do lado do cliente) — aqui é onde a equipe aprova/recusa e despacha esses pedidos.
3. Clique em **Novo pedido** (uma das ações rápidas do Dashboard) e monte um pedido ao vivo: escolha cliente, adicione produtos, confirme.

---

## 7. Loja online (configuração)

Menu lateral → **Loja online** (`/configuracoes/loja-online`).

Explique que aqui a empresa configura o catálogo público que o cliente final acessa sem login: horário de funcionamento, taxa de entrega por bairro, valor mínimo de pedido, tempo estimado de preparo. (A tela pública em si é mostrada na Seção 10.)

---

## 8. Relatórios (Prata)

Menu lateral → **Relatórios** (grupo).

1. **Análises** (`/analises`) — indicadores agregados.
2. **Relatório de pedidos** (`/relatorios/pedidos`) — filtros por período/status, exportável.
3. **Base de clientes** (`/relatorios/clientes`) — listagem/exportação de clientes.
4. **Recebíveis** (`/relatorios/recebiveis`) — o que já foi recebido vs. o que ainda está em aberto.

---

## 9. Redes sociais

Menu lateral → **Redes sociais** (`/redes-sociais`) — onde a empresa cadastra os links/handles usados na loja pública e materiais de divulgação.

**Fim da parte do plano Prata.** Faça logout.

---

## 10. Loja pública + Portal do cliente final

Ainda logado como visitante (sem conta), acesse a URL da loja da empresa (`/loja/<slug-da-empresa>`).

1. Mostre o catálogo público — produtos organizados por categoria, sem precisar de login.
2. Adicione um produto ao carrinho, avance até o checkout.
3. Finalize um pedido de teste (ou mostre até a etapa de confirmação, sem precisar concluir pagamento real).
4. Acesse `/portal/entrar` — aqui é onde o **cliente final** (não a equipe da empresa) acompanha os próprios pedidos depois de comprar: histórico de pedidos, favoritos, endereços salvos, vouchers/cupons, extrato de cashback e perfil.

**O que explicar:** são 2 públicos completamente diferentes — a equipe da empresa usa o sistema principal (o que foi mostrado até aqui), e o cliente final da empresa usa só essa área pública + o Portal, sem nunca ver o painel administrativo.

---

## 11. Plano Ouro — o que muda

Login com `dono.ouro@pegaticket.com`. Aponte que o menu lateral cresceu: agora aparecem **Estoque**, **PDV**, **Montar rota** e a seção de **Análises** ganha mais profundidade.

### Estoque
Menu lateral → **Estoque** (grupo).
1. **Locais de estoque** (`/estoque/locais`) — a empresa pode ter mais de 1 depósito/filial.
2. **Saldos** (`/estoque/saldos`) — quantidade disponível de cada produto, por local.
3. **Movimentações** (`/estoque/movimentos`) — histórico de entrada/saída/ajuste/transferência.

### PDV (frente de caixa)
Menu lateral → **PDV** (`/pdv`).
1. Explique o fluxo: abrir o caixa (valor inicial em dinheiro) → registrar vendas rápidas (sem precisar do fluxo completo de pedido) → fechar o caixa no final do dia, conferindo o valor.
2. Mostre uma venda de exemplo sendo registrada, com forma de pagamento.

### Cashback
Configurado em **Configurações** (`/configuracoes`) — mostre a seção de cashback: percentual de crédito por compra, limite por pedido, dias de carência antes do crédito ficar disponível, percentual máximo de resgate. O cliente final vê o extrato disso no Portal (Seção 10).

### Montar rota
Menu lateral → **Montar rota** (`/rotas`) — monta o roteiro de entrega do dia, agrupando pedidos por região/bairro.

**Fim da parte do plano Ouro.** Faça logout.

---

## 12. Plano Diamante — o que muda

Login com `dono.diamante@pegaticket.com`. Aponte que o menu ganhou mais 3 itens: **Balcão**, **Minha assinatura** e **Acesso de contadores**, além de campos fiscais nas Configurações.

### Balcão (mesas/comandas — operação tipo restaurante/bar)
Menu lateral → **Balcão** (grupo).
1. **Mesas** (`/balcao/mesas`) — mapa de mesas, cada uma com status (livre/ocupada). Abra uma mesa, adicione itens à comanda.
2. **Cozinha / Bar** (`/balcao/kds`) — tela pensada pra ficar fixa numa tela da cozinha/bar, mostrando a fila de itens a preparar, com botões grandes pra avançar o status (recebido → preparando → pronto → entregue na mesa).
3. Feche uma comanda de exemplo, mostrando a divisão de pagamento e a taxa de serviço (se configurada).

### Minha assinatura
Menu lateral → **Minha assinatura** (`/configuracoes/assinatura`) — onde o próprio dono da empresa gerencia a cobrança do PegaTicket: plano atual, período de cobrança (mensal/trimestral/anual), próxima fatura, cancelamento.

### Acesso de contadores
Menu lateral → **Acesso de contadores** (`/configuracoes/contadores`) — onde a empresa aprova o acesso de um escritório de contabilidade externo aos próprios relatórios financeiros (mostrado do outro lado na Seção 13).

### Configurações fiscais
Em **Configurações** (`/configuracoes`), mostre os campos de CNPJ/regime tributário/regras fiscais — preparação pra emissão de nota fiscal futura.

---

## 13. Módulo do Contador

Acesse `/contador/entrar` (é uma área separada, login próprio — o contador não usa o mesmo login da empresa).

1. Explique o fluxo: o escritório de contabilidade se cadastra (`/contador/cadastro`), confirma um código de segurança (autenticação em duas etapas), e depois solicita acesso a uma empresa cliente informando o CNPJ — a empresa precisa aprovar esse acesso (Seção 12, "Acesso de contadores").
2. Depois de aprovado, o contador vê **Empresas** (lista de clientes que autorizou acesso) e consegue consultar relatórios financeiros e trocar mensagens com a empresa — tudo sem nunca logar como se fosse a empresa.

---

## 14. Diferença de acesso — dono vs. funcionário

Faça logout e entre com `funcionario.diamante@pegaticket.com`.

Mostre lado a lado (ou compare de memória com o que apareceu logado como dono) que o menu lateral do funcionário é bem mais enxuto — só o que a função dele precisa no dia a dia (pedidos, clientes, produtos), sem acesso a configurações, assinatura, usuários ou dados financeiros sensíveis.

**O que explicar:** cada empresa pode criar quantos perfis de funcionário quiser, escolhendo exatamente o que cada um pode ver/fazer — não é tudo ou nada.

---

## 15. Encerramento

Sugestão de fechamento: recapitule os 3 planos em 1 frase cada —

- **Prata**: loja online, pedidos e clientes organizados num sistema só.
- **Ouro**: tudo do Prata + controle de estoque, frente de caixa (PDV), cashback e indicadores avançados.
- **Diamante**: tudo do Ouro + atendimento de mesa/comanda (restaurante e bar), gestão da própria assinatura e integração direta com o contador.

Encerre reforçando a tagline: **"Gestão clara para empresas em movimento."**
