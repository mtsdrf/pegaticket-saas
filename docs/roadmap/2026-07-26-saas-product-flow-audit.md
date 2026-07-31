# Maskats — Auditoria completa do fluxo SaaS

Data da auditoria: 26 de julho de 2026

## 1. Objetivo

Este documento registra a auditoria funcional e arquitetural do fluxo atual do Maskats antes de qualquer nova alteração de produto.

A auditoria foi feita lendo:

- agentes em `.claude/agents/`
- documentação em `docs/`
- memória do projeto em `.claude/memory/`
- frontend React em `web/src/`
- backend Laravel em `api/app/`, `api/routes/`, `api/tests/`
- rotas reais via `php artisan route:list --path=api/v1 --except-vendor`

Escopo da auditoria:

- fluxo atual ponta a ponta
- telas, rotas, APIs e permissões
- multi-tenancy
- estados de pedido
- pagamentos e assinatura
- estoque
- balcão / PDV / loja online / marketplace
- expedição e rota
- notificações
- testes
- documentação
- comparação funcional de referência com o Anota AI, sem copiar solução

## 2. Inventário do sistema existente

### 2.1 Agentes disponíveis e papel esperado

Agentes encontrados em `.claude/agents/`:

- `backend-laravel`
- `laravel-php-master`
- `frontend-react`
- `react-19-master`
- `database-architect`
- `database-reverse-engineer`
- `security-specialist`
- `payment-integration-specialist`
- `fiscal-document-specialist`
- `delivery-integration-specialist`
- `qa-testing-master`
- `backend-qa-specialist`
- `frontend-qa-specialist`
- `software-architect-specialist`
- `ui-ux-master`
- `business-specialist`
- `product-flow-orchestrator`
- `code-review-architect`
- `code-reviewer`
- `token-optimizer`
- `landing-page-specialist`
- `autonomous-marketing-director`

Leitura prática da malha de agentes:

- a base já tem agentes suficientes para operar produto, arquitetura, pagamento, fiscal, delivery, segurança, QA e UX sem depender de conhecimento improvisado
- o `product-flow-orchestrator` é adequado para quebrar o sistema por jornadas, estados, handoffs e gaps
- os agentes mais críticos para a próxima etapa do produto são: `software-architect-specialist`, `backend-laravel`, `frontend-react`, `delivery-integration-specialist`, `payment-integration-specialist`, `fiscal-document-specialist`, `security-specialist` e `qa-testing-master`

### 2.2 Estrutura macro observada

Monorepo:

- `api/` Laravel
- `web/` React + TypeScript + Vite
- `site/` site público

Contagem observada nesta auditoria:

- `168` migrations
- `378` rotas `api/v1` ativas observadas no backend
- `145` arquivos de páginas React
- `102` componentes React
- `155` arquivos de teste em `api/tests`

### 2.3 Domínios já implementados

No backend existem domínios ativos para:

- autenticação
- usuários, grupos e permissões
- empresas e perfis da empresa
- clientes
- endereços
- produtos
- estoque
- pedidos
- parcelas e pagamentos de pedidos
- loja online
- portal do cliente final
- PDV
- balcão / comanda / KDS
- assinatura e billing
- fiscal
- contabilidade
- LGPD / privacidade
- suporte
- rotas de entrega e cobrança
- webhooks e chaves de API
- marketplace / iFood
- treinamentos e onboarding

## 3. Modelo operacional atual

### 3.1 Multi-tenancy e controle de acesso

Estado observado:

- autenticação é global por usuário
- o contexto operacional depende de `tenant_uuid` no token JWT
- o middleware `ResolveTenant` valida:
  - empresa ativa
  - vínculo ativo do usuário com a empresa
  - perfil ativo da empresa
- o `PermissionService` combina:
  - permissões globais por `Group`
  - permissões do tenant por `TenantRole`
  - travas por plano
  - overrides de funcionalidade por empresa
- existe exceção funcional explícita para o proprietário da empresa, que pode enxergar fluxo de assinatura mesmo sem a permissão atribuída por grupo

Leitura arquitetural:

- o desenho de multi-tenancy está maduro
- a separação entre plataforma e empresa está correta
- o acoplamento entre plano e funcionalidade existe, mas ainda impacta a experiência em vários pontos da UI porque o produto está distribuído em muitos módulos independentes

### 3.2 Perfis/jornadas mapeados

Jornadas observadas no sistema:

- cliente final:
  - loja pública
  - carrinho
  - checkout
  - tracking
  - portal autenticado por OTP
  - favoritos
  - pedir de novo
  - solicitar cancelamento
  - avaliar pedido
- operador interno:
  - clientes
  - produtos
  - pedidos internos
  - pedidos da loja
  - pagamentos
  - entregas
- cozinha / bar:
  - KDS do balcão
  - avanço de item por estação
- expedição:
  - hoje não existe módulo próprio de expedição separado
  - parte do fluxo fica diluída entre pedidos, rota, balcão e marketplace
- entregador:
  - não existe app/painel dedicado de entregador
  - a operação hoje é representada por marcações de entrega e pela tela de rota
- administrador da empresa:
  - configurações
  - usuários da empresa
  - perfis da empresa
  - assinatura
  - integrações
  - fiscal
  - contador
  - LGPD operacional
- administrador global:
  - usuários globais
  - grupos
  - funcionalidades
  - planos
  - empresas
  - auditoria
  - pendências de pagamento

## 4. Fluxo atual ponta a ponta

### 4.1 Entrada e ativação da empresa

Fluxo atual:

1. usuário pode entrar por login ou cadastro público
2. no cadastro público a empresa nasce em trial
3. existe aceite jurídico versionado de termos e privacidade
4. o sistema resolve empresa ativa e perfil de acesso após autenticação
5. o dashboard já contempla estado de empresa sem pedidos

Pontos fortes:

- onboarding inicial já não exige plano definitivo
- trilha de aceite jurídico ficou sólida
- a experiência inicial não fica mais quebrada para empresa vazia

Gaps:

- ainda não existe um fluxo operacional unificado “primeiros 30 minutos” com sequência obrigatória de implantação
- a central de treinamento ajuda, mas não substitui um wizard de ativação do negócio

### 4.2 Catálogo e base operacional

Fluxo atual:

1. empresa cadastra geografia auxiliar e clientes
2. empresa cadastra categorias, tipos e produtos
3. estoque é separado por local
4. loja online reaproveita catálogo e preço
5. marketplace iFood depende de matching / sync adicional

Pontos fortes:

- catálogo base já atende operação interna, loja pública e marketplace
- estoque já tem leitura paginada e movimentação estruturada
- opcionais de produto já entraram na modelagem

Gaps:

- catálogo ainda não está unificado por canal no nível de governança visual e operacional
- há fragmentação entre “produto”, “loja”, “marketplace” e “fiscal”

### 4.3 Pedidos internos

Fluxo observado:

- rota web: `/pedidos`
- API lista apenas `origin=staff`
- criação de pedido é centralizada em `OrderService`
- pedido reserva estoque
- pedido controla:
  - pago
  - entregue
  - parcelado
  - cancelado
  - saiu para entrega
- informações fiscais ficam em ação separada do grid

Estados observados:

- `confirmed`
- `rejected`
- `pending_approval`
- `cancellation_requested`

Sinais adicionais fora do status:

- `is_paid`
- `is_delivered`
- `is_out_for_delivery`
- `cancelled_at`

Leitura:

- a máquina de estados funcional existe, mas é híbrida
- parte do estado está em `status`
- parte do estado está espalhada em flags
- isso funciona, mas aumenta esforço cognitivo e risco de inconsistência de fluxo

### 4.4 Loja pública e pedidos da loja

Fluxo observado:

1. cliente acessa `/loja/:slug`
2. catálogo público valida plano e horário
3. checkout da loja resolve/relaciona cliente final e cliente interno
4. guardas de checkout:
   - horário
   - pickup habilitado
   - pedido mínimo
   - taxa de entrega por bairro
   - cupom
   - cashback
5. pedido nasce com `origin='storefront'`
6. pedido entra em fila de aprovação com `status='pending_approval'`
7. gestão operacional fica em `/pedidos-loja`

Pontos fortes:

- fluxo público está bem protegido por regras de negócio
- existe diferença clara entre pedido interno e pedido da loja
- existe polling e alerta de pedido novo na gestão da loja
- cliente final já possui portal, rastreio, favoritos e reorder

Gaps:

- pedido da loja ainda não deságua em uma esteira operacional única com produção, expedição e entrega
- a fila de aprovação é funcional, mas ainda é um “subfluxo paralelo”

### 4.5 PDV

Fluxo observado:

1. acesso em `/pdv`
2. sessão de caixa é pré-requisito
3. venda usa `origin='pdv'` e já nasce `confirmed`
4. soma dos pagamentos precisa fechar exatamente o total
5. venda pode operar online e offline
6. existe snapshot local, fila offline e idempotência por `client_sale_uuid`

Pontos fortes:

- fluxo de caixa está consistente
- idempotência offline foi desenhada corretamente
- restrição de meios eletrônicos offline está honesta

Gaps:

- PDV ainda é uma trilha operacional isolada em relação à gestão completa do pedido
- não existe uma camada analítica/operacional clara separando “venda balcão rápida” de “pedido com produção e entrega”

### 4.6 Balcão / comanda / cozinha

Fluxo observado:

1. mesa/comanda vivem em agregado separado
2. item da comanda anda por preparo em KDS
3. `Comanda` só vira `Order(origin='counter')` no fechamento
4. existe snapshot offline e fila local para comandas e itens
5. conflito multi-dispositivo já começou a ser tratado

Estados de preparo observados:

- `queued`
- `sent_to_station`
- `preparing`
- `ready`
- `delivered_to_table`
- `cancelled`

Pontos fortes:

- decisão arquitetural de não poluir `Order` com o ciclo de mesa foi correta
- KDS já existe
- operação offline controlada já existe

Gaps:

- o fluxo “cozinha → expedição → entrega/mesa” ainda não forma uma linguagem única do sistema
- expedição continua diluída
- fechamento de conta e pós-fechamento ainda pertencem a outro subdomínio

### 4.7 Marketplace / iFood

Fluxo observado:

1. integração por empresa
2. webhook + polling + recovery
3. pedidos externos entram em `marketplace_orders`
4. operador pode:
   - ver fila externa
   - refresh
   - importar
   - reenfileirar evento
   - enviar ações ao iFood
   - cancelar com motivo
5. importação gera `Order` interno canônico com `origin=ifood`

Pontos fortes:

- fundação de integração está acima da média de um MVP comum
- há trilha técnica real de eventos, ações, sync de catálogo e recovery
- o pedido externo é mantido separado antes da importação

Gaps:

- a jornada do operador ainda troca de tela e de linguagem entre pedido externo e pedido interno
- a importação é robusta tecnicamente, mas a experiência ainda é de backoffice técnico, não de operação fluida

### 4.8 Produção, expedição e entrega

Estado observado:

- produção existe parcialmente no balcão via KDS
- pedidos internos e da loja não têm uma esteira única de produção visual
- expedição não tem módulo próprio
- entrega tem marcações em pedido e tela de rota, mas não tem painel dedicado de entregador/despacho

Conclusão:

- esta é hoje uma das maiores lacunas de produto do Maskats
- o sistema já vende, cobra, lista, aprova, sincroniza e rastreia
- mas ainda não organiza toda a execução física do pedido em uma esteira simples

### 4.9 Pagamentos e assinatura

Assinatura SaaS:

- trial de 14 dias
- cancelamento
- renovação
- arrependimento
- troca de plano
- troca de cartão
- invoices, payments, refunds e webhooks
- integração com Mercado Pago já é parte relevante do sistema

Pagamentos de pedidos:

- pagamento de pedido interno
- parcelas
- reconciliação com Mercado Pago
- pagamentos do portal

Pontos fortes:

- o billing do SaaS está mais maduro que muitos módulos operacionais
- existe base de idempotência, webhook e reconciliação

Gaps:

- a experiência do produto ainda não usa essa maturidade para simplificar o entendimento do dono da empresa
- operacionalmente, pagamento do pedido ainda não está desenhado como parte explícita da esteira de execução do pedido

### 4.10 Fiscal

Estado observado:

- diagnóstico fiscal por pedido
- preparação de documento fiscal interno
- histórico técnico
- tentativas
- sync de status com provider
- provider configurável por empresa
- prontidão fiscal

Conclusão:

- o módulo fiscal interno/manual está bem avançado
- ainda não há emissão oficial integrada ativa
- isso está corretamente documentado

### 4.11 LGPD e jurídico

Estado observado:

- termos e privacidade versionados
- aceites persistidos
- playbook operacional
- bloco de dados e privacidade
- solicitações de privacidade por empresa

Conclusão:

- para MVP comercial, a frente jurídico/LGPD operacional já está em estado apresentável
- o limite está na operação jurídica formal, não no produto base

## 5. Falhas, atritos e repetições

### 5.1 Principal problema de produto

O Maskats já tem muitos recursos, mas o fluxo principal de operação ainda está distribuído demais.

Hoje o sistema tem pelo menos cinco trilhas paralelas de pedido:

- pedido interno
- pedido da loja
- pedido do PDV
- pedido do balcão
- pedido do iFood

Todas elas convergem parcialmente para `Order`, mas a experiência do operador ainda não converge para uma esteira única.

### 5.2 Falhas e dificuldades identificadas

1. Existe fragmentação de contexto entre canais.
2. Produção, expedição e entrega não formam uma jornada visual única.
3. O estado do pedido está dividido entre `status` e flags laterais.
4. O marketplace tem boa fundação técnica, mas ainda parece ferramenta separada da operação.
5. O balcão tem KDS e offline, porém seu ciclo não conversa visualmente com os demais pedidos.
6. O dono da empresa ainda precisa entender módulos demais para operar bem.
7. O onboarding existe em peças, mas ainda não é um fluxo guiado único.
8. Há muita potência de backoffice e menos simplificação do “dia a dia do pedido”.

### 5.3 Etapas repetidas

Repetições percebidas:

- várias telas de pedido por origem
- múltiplas entradas para gestão operacional do mesmo conceito
- configuração distribuída entre hubs diferentes
- diferenças de linguagem entre módulos que deveriam parecer parte da mesma operação

## 6. Comparação funcional com a referência Anota AI

Referência pública consultada:

- Home: https://anota.ai/home/
- Primeiros passos: https://anota.ai/ajuda/primeiros-passos/
- Integrações: https://anota.ai/home/integracoes/
- Atendente virtual: https://anota.ai/home/funcionalidade/atendente-virtual/
- Gestão avançada: https://anota.ai/home/funcionalidade/gestao-avancada-anota-ai/
- Conteúdo sobre comandas: https://anota.ai/blog/sistema-de-comandas/
- Conteúdo sobre gestão de delivery: https://anota.ai/blog/sistema-de-gestao-para-delivery/
- Conteúdo sobre integração de pedidos com PDV/cozinha: https://anota.ai/blog/como-integrar-pedidos-do-whatsapp-pdv-e-cozinha-no-restaurante/

Leitura de referência:

- o Anota AI comunica uma operação mais centrada em esteira única de pedidos
- a mensagem pública deles enfatiza:
  - cardápio digital
  - automação de atendimento
  - centralização dos canais
  - KDS
  - PDV / comandas
  - pagamento online
  - gestão operacional concentrada

Comparação honesta:

- o Maskats já tem fundações técnicas mais profundas em várias áreas:
  - RBAC híbrido
  - LGPD operacional
  - fiscal interno/manual
  - assinatura SaaS
  - multi-tenancy robusto
  - offline controlado
  - marketplace com trilha técnica
- o Anota AI parece mais simples na narrativa operacional
- o ponto de vantagem do concorrente está menos em profundidade arquitetural e mais em fluidez da jornada principal

Conclusão comparativa:

- o Maskats não precisa copiar interface ou linguagem do Anota AI
- mas precisa aprender com a clareza deles no fluxo de operação central
- o problema a resolver não é “faltam módulos”
- o problema é “os módulos ainda não se apresentam como um sistema operacional único da empresa”

## 7. Proposta original e mais simples para o Maskats

### 7.1 Princípio central

Unificar a operação por esteira, não por origem.

Em vez de o sistema parecer vários subprodutos, ele deve parecer uma única operação com entradas diferentes.

### 7.2 Macrofluxo alvo

1. Entrada do pedido
   - loja
   - iFood
   - PDV
   - balcão
   - pedido manual

2. Triagem
   - pendente de aprovação
   - aprovado
   - recusado

3. Produção
   - aguardando preparo
   - em preparo
   - pronto

4. Expedição
   - aguardando separação
   - separado
   - aguardando retirada / envio

5. Entrega / consumo
   - retirado
   - saiu para entrega
   - entregue
   - servido na mesa

6. Pós-venda
   - pagamento
   - cancelamento
   - fiscal
   - avaliação
   - reordenar

### 7.3 Regra de desenho

- origem continua existindo
- mas origem vira atributo do pedido, não a navegação principal da operação

### 7.4 Navegação recomendada

Menu operacional simplificado:

- Operação
- Produção
- Entregas
- Clientes
- Produtos
- Financeiro
- Configurações

Subtelas especializadas podem continuar existindo, mas devem ficar atrás dessa navegação principal.

## 8. Inventário de telas

### 8.1 Telas atuais que devem permanecer

- dashboard
- clientes
- produtos
- estoque
- pedidos
- pedidos da loja
- pedidos iFood
- PDV
- balcão mesas
- KDS
- rota
- relatórios
- configurações
- assinatura
- suporte
- treinamento
- portal
- loja pública
- tracking

### 8.2 Telas atuais que devem ser alteradas

- dashboard
  - focar em operação do dia + onboarding
- pedidos
  - virar visão canônica multi-origem da operação
- pedidos da loja
  - virar fila especializada ou filtro salvo, não trilha isolada principal
- pedidos iFood
  - manter como backoffice técnico + detalhe, não como única porta operacional
- rota
  - aproximar da etapa de expedição/entrega
- balcão
  - integrar semanticamente com a esteira geral
- configurações
  - agrupar mais claramente “implantação”, “comercial”, “fiscal”, “integrações”, “jurídico”

### 8.3 Telas novas recomendadas

1. Central de operação
   - fila única de pedidos multi-origem
2. Central de produção
   - visão unificada de preparo
3. Central de expedição
   - separação e despacho
4. Central de entregas
   - rotas, status e confirmação
5. Implantação guiada
   - wizard do primeiro uso da empresa
6. Saúde operacional
   - integrações, webhooks, polling, filas, conflitos offline e incidentes

## 9. Estados e transições recomendados

### 9.1 Pedido canônico

Estados recomendados de alto nível:

- `draft`
- `pending_approval`
- `approved`
- `in_preparation`
- `ready_for_dispatch`
- `dispatched`
- `delivered`
- `served`
- `rejected`
- `cancelled`

Atributos laterais:

- origem
- canal
- tipo de fulfillment
- pago / parcialmente pago / pendente
- fiscal pronto / pendente / emitido
- com conflito de sincronização

### 9.2 Itens de produção

Estados recomendados:

- `queued`
- `sent_to_station`
- `preparing`
- `ready`
- `served`
- `cancelled`

### 9.3 Assinatura da empresa

Estados já maduros e suficientes para o momento:

- `trialing`
- `active`
- `past_due`
- `cancel_scheduled`
- `suspended`
- `canceled`

## 10. Backlog priorizado

### P0

1. Criar desenho canônico da esteira operacional multi-origem.
2. Transformar `Pedidos` em central operacional real, com filtros por origem em vez de telas isoladas como porta principal.
3. Criar camada explícita de produção.
4. Criar camada explícita de expedição.
5. Criar implantação guiada da empresa.
6. Consolidar linguagem de status e transições do pedido.

### P1

1. Reposicionar `Pedidos da loja` e `Pedidos iFood` como visões especializadas da mesma operação.
2. Integrar melhor balcão/KDS com a operação canônica.
3. Criar painel de saúde operacional de integrações e conflitos offline.
4. Tornar o dashboard mais operacional e menos apenas analítico.

### P2

1. Painel dedicado de entregador ou despacho.
2. Reforço do self-service da empresa para configuração sem suporte.
3. Evolução do módulo fiscal oficial.
4. Ampliação de integrações de delivery além do iFood.

## 11. Distribuição do trabalho por agentes

### `software-architect-specialist`

- arquitetura do fluxo unificado
- contratos entre pedido canônico, produção e expedição
- desenho de transições

### `backend-laravel`

- refatoração de APIs do fluxo operacional
- agregadores e resources
- endpoints canônicos da esteira

### `frontend-react`

- central operacional
- telas de produção, expedição e implantação
- reaproveitamento dos padrões visuais existentes

### `delivery-integration-specialist`

- integração da esteira com iFood e futuros parceiros
- mapeamento de eventos externos para o pedido canônico

### `payment-integration-specialist`

- alinhamento entre cobrança SaaS, pagamentos de pedido e estados financeiros

### `fiscal-document-specialist`

- acoplamento correto entre estados do pedido e estados fiscais

### `security-specialist`

- review de permissões por transição operacional
- segregação entre plataforma, empresa e canais públicos

### `qa-testing-master`

- plano de testes ponta a ponta
- regressão por canal de pedido
- cenários offline, pagamento, integração e permissão

### `ui-ux-master`

- simplificação da navegação
- redução de sobrecarga cognitiva
- clareza da esteira principal

## 12. Estratégia de testes

### 12.1 Testes obrigatórios

- jornada completa por origem:
  - loja
  - pedido manual
  - PDV
  - balcão
  - iFood
- permissão por perfil:
  - operador
  - cozinha
  - expedição
  - administrador da empresa
  - administrador global
- pagamento:
  - pedido pago
  - parcial
  - cancelado
  - assinatura
- offline:
  - PDV
  - balcão
  - conflito
- integrações:
  - webhook
  - polling
  - retry
  - importação

### 12.2 Situação atual de testes

O projeto já tem cobertura forte em:

- auth
- permissions
- orders
- storefront
- portal
- subscription
- payment webhooks
- stock
- PDV
- balcão
- fiscal
- marketplace
- LGPD
- infraestrutura

Gaps mais prováveis:

- testes E2E de jornada unificada do produto
- testes mais fortes da experiência cross-módulo no frontend

## 13. Rollout, métricas e rollback

### 13.1 Estratégia de rollout

1. manter APIs atuais funcionando
2. criar camada canônica nova primeiro
3. migrar telas gradualmente
4. habilitar por feature flag por empresa se necessário
5. só depois remover trilhas antigas

### 13.2 Métricas

- tempo até o primeiro pedido da empresa
- tempo médio entre entrada e conclusão do pedido
- taxa de pedidos presos por etapa
- taxa de erro operacional por canal
- quantidade de cliques até ações principais
- taxa de sincronização com sucesso no offline
- taxa de importação de marketplace com sucesso

### 13.3 Rollback

- manter rotas/telas legadas até estabilização
- encapsular a nova esteira atrás de feature flag
- rollback por módulo:
  - operação
  - produção
  - expedição

## 14. Conclusão executiva

O Maskats já não é um protótipo. Ele tem profundidade real em:

- multi-tenancy
- RBAC
- billing SaaS
- operação de pedidos
- PDV
- balcão
- offline controlado
- fiscal interno/manual
- LGPD operacional
- marketplace

O problema atual não é falta de base técnica.

O problema principal é de orquestração de produto:

- muitos fluxos bons
- pouca unificação da operação principal

Se eu fosse transformar isso na próxima frente estratégica, eu seguiria nesta ordem:

1. central operacional de pedidos multi-origem
2. camada explícita de produção
3. camada explícita de expedição/entrega
4. implantação guiada da empresa
5. painel de saúde operacional

Essa é a menor rota para transformar o sistema atual em um SaaS mais simples de operar, mais fácil de vender e mais difícil de abandonar.
