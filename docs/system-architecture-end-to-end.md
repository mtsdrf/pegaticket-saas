# Maskats — Arquitetura Completa de Ponta a Ponta

Data de referência: **25 de julho de 2026**  
Base desta documentação: **código-fonte atual do monorepo + configuração ativa do backend + banco de dados ativo**.

## 1. Objetivo deste documento

Este documento consolida a visão arquitetural real do Maskats no estado atual, cobrindo:

- produto e posicionamento do sistema;
- topologia do monorepo;
- arquitetura backend, frontend e landing;
- identidade, autenticação, autorização e multi-tenancy;
- módulos de negócio já implementados;
- modelo de dados e áreas mais pesadas do banco;
- integrações externas;
- filas, agendamentos, webhooks e automações;
- segurança, auditoria e observabilidade;
- deploy e operação;
- gaps e próximos cuidados arquiteturais.

Este material descreve prioritariamente o **estado atual real**. Onde existir evolução em andamento, isso é explicitamente marcado como parcial, manual ou futuro.

## 2. Resumo executivo

O **Maskats** é um SaaS multiempresa orientado a operação comercial, com foco mobile-first, que já cobre uma base ampla de capacidades:

- administração da plataforma;
- gestão de empresas;
- usuários, grupos, perfis e permissões;
- clientes, endereços e catálogos auxiliares;
- produtos, categorias, tipos, preços e opcionais;
- estoque e movimentações;
- pedidos internos e pedidos originados da loja/iFood;
- relatórios operacionais e analíticos;
- loja online, portal do cliente final e jornada de recompra;
- assinatura, faturamento e integração com Mercado Pago;
- módulo fiscal em fluxo interno/manual;
- módulo do contador;
- PDV e balcão;
- webhooks e integrações externas;
- trilha de auditoria e governança de acesso por plano.

Em 25/07/2026, o repositório representa um sistema já bastante amplo, com:

- **384 rotas** Laravel registradas;
- **104 tabelas** no banco ativo;
- backend em **Laravel 13 / PHP 8.3+**;
- frontend principal em **React 19 / TypeScript / Vite / MUI / ag-Grid**;
- base ativa em **MariaDB 11.8.8**;
- tabelas operacionais mais pesadas em produção:
  - `orders`: **42.25 MB**
  - `order_items`: **31.16 MB**
  - `payments`: **20.64 MB**

## 3. Topologia do monorepo

Estrutura principal:

```text
maskats-saas/
├── api/     Backend Laravel 13
├── web/     Aplicação autenticada React 19
├── site/    Landing institucional pública
├── docs/    Documentação operacional e roadmaps
├── .claude/ Agentes especialistas + memória arquitetural
└── README.md
```

Separação de responsabilidades:

- `api/` é a **fonte única de verdade** de regras de negócio, dados e integrações.
- `web/` concentra múltiplas experiências de interface no mesmo frontend:
  - sistema interno autenticado;
  - portal do cliente final;
  - loja pública;
  - área do contador.
- `site/` é a presença institucional e comercial, separada do app autenticado.

## 4. Stack e runtime

### 4.1 Backend

- Laravel 13
- PHP 8.3+
- MySQL/MariaDB
- JWT manual com `php-open-source-saver/jwt-auth`
- Swagger bilíngue com `darkaonline/l5-swagger`
- Geração de PDF com `barryvdh/laravel-dompdf`
- Push com `minishlink/web-push`
- TOTP com `pragmarx/google2fa`

### 4.2 Frontend principal

- React 19
- TypeScript
- Vite 8
- Material UI
- ag-Grid Community
- Chart.js
- Axios
- React Router v7
- Vite PWA plugin

### 4.3 Infra atual observada

- hospedagem compartilhada para produção;
- deploy automatizado por GitHub Actions;
- publicação por SSH/rsync;
- backend e frontend publicados separadamente;
- CORS configurado para backend desacoplado;
- sem evidência, neste levantamento, de stack containerizada ativa em produção.

## 5. Visão arquitetural geral

### 5.1 Estilo predominante

O sistema segue uma arquitetura monolítica modular, com separação forte por domínio e camadas internas.  
Não há microserviços. A composição principal está em:

- controllers finos;
- requests de validação;
- DTOs de entrada;
- services de negócio;
- resources de saída;
- eventos/listeners para auditoria;
- scheduler e jobs para processos assíncronos.

### 5.2 Padrão backend por feature

Fluxo canônico:

```text
Request → Controller → Service → Model/Query/apoio → Resource
```

Características recorrentes:

- controllers orquestram;
- services concentram regra de negócio;
- DTOs transformam entrada validada;
- resources padronizam resposta;
- mutações relevantes disparam eventos de auditoria;
- `BaseModel` centraliza UUID, soft delete e colunas de autoria.

### 5.3 Contrato HTTP

Padrão de sucesso:

```json
{
  "success": true,
  "message": "...",
  "data": {},
  "meta": {
    "request_id": "..."
  }
}
```

Padrão de erro:

```json
{
  "success": false,
  "message": "...",
  "code": "SOME_ERROR_CODE",
  "errors": {},
  "meta": {}
}
```

## 6. Experiências de interface existentes

O frontend `web/` já atende múltiplas frentes:

### 6.1 Sistema interno autenticado

Uso por:

- administradores da plataforma;
- proprietários da empresa;
- operadores;
- financeiro;
- estoque;
- logística;
- atendentes;
- gestores.

Principais áreas já implementadas:

- dashboard;
- administração;
- clientes;
- produtos;
- estoque;
- pedidos;
- relatórios;
- analytics;
- integrações;
- assinatura;
- configurações da empresa;
- fiscal;
- rotas;
- PDV;
- balcão;
- suporte.

### 6.2 Loja pública

Fluxos públicos já existentes:

- catálogo por slug da empresa;
- categorias;
- cálculo de taxa de entrega;
- validação de cupom;
- checkout;
- acompanhamento de pedido;
- manifesto PWA.

### 6.3 Portal do cliente final

Fluxos já implementados:

- autenticação por OTP;
- histórico de pedidos;
- favoritos;
- cashback;
- vouchers;
- endereços;
- perfil;
- “pedir novamente”.

### 6.4 Área do contador

Fluxos já implementados:

- cadastro do escritório;
- TOTP;
- login segregado;
- solicitação de acesso à empresa;
- relatórios e dados por tenant aprovado;
- troca de mensagens entre empresa e escritório.

### 6.5 Landing institucional

`site/` é um projeto separado voltado a:

- marketing;
- apresentação comercial;
- posicionamento do produto;
- conteúdo institucional.

## 7. Identidade, autenticação e fronteiras de segurança

O sistema opera com **três identidades JWT distintas**, todas manuais:

### 7.1 Staff (`User`)

Usada no sistema principal.

Capacidades:

- login administrativo;
- troca de empresa ativa;
- administração global;
- operação interna;
- auto cadastro de empresas;
- permissões via grupo e perfil.

### 7.2 Cliente final (`FinalCustomer`)

Usada no portal e em trechos da loja.

Capacidades:

- OTP;
- consulta de pedidos;
- favoritos;
- cashback;
- vínculo com empresas;
- pedidos novamente;
- avaliação.

### 7.3 Escritório contábil (`AccountingOffice`)

Usada no módulo do contador.

Capacidades:

- TOTP obrigatório;
- acesso multiempresa aprovado;
- relatórios e mensagens por empresa autorizada.

### 7.4 Isolamento entre identidades

O projeto trata explicitamente as fronteiras entre identidades.

Regras centrais:

- token de uma identidade não deve autenticar em rota da outra;
- resolução de token valida `subject model`;
- blacklist por `jti`;
- frontend usa clients e storages separados por contexto.

## 8. Multi-tenancy e controle de acesso

### 8.1 Modelo de empresa

No produto, o conceito exposto ao usuário é **empresa**.  
Internamente, a modelagem continua baseada em `Tenant`.

### 8.2 Estrutura de acesso

O modelo atual combina:

- **GroupPermission** para escopo global/plataforma;
- **TenantRolePermission** para escopo operacional dentro da empresa;
- **PlanFunctionality** para gate comercial do que o plano libera.

### 8.3 Camadas de decisão de acesso

Uma rota tenant-scoped passa, em ordem conceitual, por:

1. autenticação;
2. empresa ativa no token/contexto;
3. vínculo ativo com a empresa;
4. perfil ativo na empresa;
5. permissão global ou permissão do perfil;
6. funcionalidade liberada no plano;
7. posse do recurso quando aplicável.

### 8.4 Perfis e grupos

Existem dois níveis diferentes:

- **grupos**: governança global, administração e acessos estruturais;
- **perfis da empresa**: operação do dia a dia dentro da empresa.

### 8.5 Planos

O sistema já possui monetização por plano, com base atual em:

- Prata
- Ouro
- Diamante

O gate comercial já está conectado às funcionalidades do backend.

## 9. Domínios e módulos do sistema

### 9.1 Núcleo administrativo e plataforma

Módulos:

- autenticação;
- usuários;
- grupos;
- funcionalidades;
- ações;
- auditoria;
- empresas;
- planos;
- convites;
- exportação de dados;
- API keys;
- release notes;
- suporte.

Objetivo:

- governança da plataforma;
- bootstrap de empresas;
- segurança e rastreabilidade;
- gestão de produto e acessos.

### 9.2 Cadastro e CRM operacional

Módulos:

- clientes;
- categorias de cliente;
- dia ideal;
- período ideal;
- endereços;
- estados, cidades e bairros.

Observação importante:

- `Estado`, `Cidade` e `Bairro` são catálogos globais;
- `Endereco` e `Client` permanecem tenant-scoped.

### 9.3 Catálogo comercial

Módulos:

- produtos;
- categorias de produto;
- tipos de produto;
- preços por categoria;
- promoções;
- favoritos;
- atacado;
- imagem do produto;
- opcionais e grupos de opcionais.

Este domínio hoje atende tanto operação interna quanto loja pública e integrações de delivery.

### 9.4 Estoque

Módulos:

- locais de estoque;
- saldos;
- movimentações;
- reservas;
- bloqueios;
- perdas;
- transferências;
- devoluções;
- ajustes.

O estoque já é integrado aos fluxos de pedido e entrega.

### 9.5 Pedidos

Módulos:

- pedidos internos;
- itens de pedido;
- parcelas;
- pagamento;
- entrega;
- cancelamento;
- fila operacional;
- rastreio;
- preparo;
- origem do pedido.

O domínio de pedido é um dos agregados centrais do sistema.

### 9.6 Loja online e delivery direto

Módulos:

- storefront por empresa;
- horários de funcionamento;
- taxa de entrega;
- cupons;
- checkout;
- promoções;
- favoritos;
- cashback;
- reativação;
- avaliação;
- manifesto PWA;
- telemetria de abandono de carrinho.

### 9.7 Financeiro e assinaturas

Módulos:

- assinaturas;
- preços por plano;
- invoices;
- payments;
- refunds;
- eventos de assinatura;
- chave de idempotência de pagamento;
- conciliação;
- issues de pagamento.

Este domínio está integrado ao Mercado Pago.

### 9.8 Fiscal

Módulos:

- perfis de operação fiscal;
- regras tributárias;
- readiness fiscal;
- documentos fiscais;
- tentativas;
- mensagens do provider;
- numeração;
- prévia fiscal;
- preparação de documento.

Estado atual:

- existe fluxo fiscal interno/manual robusto;
- **não há, neste retrato, emissão fiscal oficial integrada ativa**;
- a arquitetura já foi preparada para providers reais no futuro.

### 9.9 Contador

Módulos:

- escritórios contábeis;
- vínculos com empresas;
- pedidos de acesso;
- mensagens;
- relatórios do contador;
- TOTP e auth segregada.

### 9.10 PDV e balcão

Módulos:

- caixa;
- sessão de caixa;
- movimentações de caixa;
- PIN de operador;
- PDV;
- estações;
- mesas;
- comandas;
- itens de comanda;
- KDS/preparo.

### 9.11 Analytics, relatórios e BI operacional

Módulos:

- indicadores;
- gráficos;
- aging;
- ABC;
- RFM;
- recebíveis;
- resumo de recebíveis;
- exportação PDF;
- analytics de canais.

### 9.12 Integrações e ecossistema externo

Módulos:

- webhooks de pagamento;
- webhooks internos/publicáveis;
- subscriptions de webhook;
- integrações de marketplace;
- catálogo do marketplace;
- sincronização;
- health checks.

## 10. Arquitetura de dados

### 10.1 Panorama do banco ativo

Estado observado via `php artisan db:show`:

- banco: `u452434908_maskats_saas`
- engine: MariaDB 11.8.8
- conexão: `mysql`
- tabelas: **104**
- tamanho total: **109.08 MB**

### 10.2 Tabelas mais relevantes por domínio

Plataforma e acesso:

- `users`
- `groups`
- `functionalities`
- `actions`
- `group_permissions`
- `tenants`
- `tenant_roles`
- `tenant_role_permissions`
- `tenant_users`
- `tenant_settings`
- `audit_logs`

Comercial:

- `clients`
- `enderecos`
- `products`
- `product_categories`
- `product_types`
- `product_category_prices`

Operação:

- `orders`
- `order_items`
- `order_installments`
- `stock_locations`
- `stock_balances`
- `stock_movements`

Loja/portal:

- `final_customers`
- `final_customer_otps`
- `final_customer_tenant_links`
- `coupons`
- `coupon_redemptions`
- `product_promotions`
- `product_favorites`
- `order_ratings`
- `push_subscriptions`
- `cart_events`

Financeiro:

- `subscriptions`
- `plan_prices`
- `invoices`
- `payments`
- `refunds`
- `subscription_events`
- `payment_idempotency_keys`

Fiscal:

- `tax_rules`
- `fiscal_operation_profiles`
- `fiscal_documents`
- `fiscal_document_attempts`
- `fiscal_provider_messages`

Contador:

- `accounting_offices`
- `accounting_office_tenant`
- `accounting_request_messages`

Marketplace:

- `marketplace_integrations`
- `marketplace_merchants`
- `marketplace_events`
- `marketplace_orders`
- `marketplace_actions`
- `marketplace_catalog_mappings`
- `marketplace_catalog_syncs`
- `marketplace_catalog_sync_items`

PDV e balcão:

- `cash_registers`
- `cash_sessions`
- `cash_movements`
- `stations`
- `tables`
- `comandas`
- `comanda_items`

### 10.3 Maiores tabelas no ambiente atual

Hotspots atuais de volume:

- `orders`: **42.25 MB**
- `order_items`: **31.16 MB**
- `payments`: **20.64 MB**
- `clients`: **1.20 MB**
- `enderecos`: **0.94 MB**
- `tenant_role_permissions`: **1.52 MB**

Conclusão operacional:

- o centro de gravidade atual de volume está em **pedidos + itens + pagamentos**;
- qualquer iniciativa séria de performance deve continuar olhando primeiro para:
  - paginação;
  - índices compostos;
  - payload de listagem;
  - eager loading;
  - filtros/sorts server-side;
  - aggregates financeiros.

### 10.4 Convenções de modelagem

Padrões predominantes:

- PK interna incremental;
- `uuid` público para exposição externa;
- `softDeletes()` na maioria das tabelas de domínio;
- colunas de autoria `created_by`, `updated_by`, `deleted_by`;
- FKs explícitas;
- constraints compostas nomeadas;
- tenant como eixo de isolamento da maioria dos agregados.

## 11. Fluxos críticos de ponta a ponta

### 11.1 Administração da plataforma

Fluxo:

1. usuário staff autentica;
2. escolhe empresa quando necessário;
3. administra usuários, grupos, funcionalidades e planos;
4. cria ou gerencia empresas;
5. acompanha auditoria e governança.

### 11.2 Onboarding de empresa

Fluxos existentes:

- criação administrativa da empresa;
- self-signup público;
- provisionamento de owner;
- associação de plano;
- início de trial;
- sincronização de permissões do owner conforme plano.

### 11.3 Operação comercial interna

Fluxo típico:

1. cadastro/configuração de clientes e catálogo;
2. criação de pedido;
3. reserva de estoque;
4. faturamento/pagamento;
5. entrega;
6. baixa/reconciliação;
7. análise em relatórios.

### 11.4 Loja pública

Fluxo típico:

1. cliente acessa loja por slug;
2. consulta catálogo/categorias;
3. escolhe produtos;
4. calcula taxa;
5. aplica cupom, cashback e regras da empresa;
6. fecha pedido;
7. acompanha o pedido.

### 11.5 Portal do cliente final

Fluxo típico:

1. cliente autentica por OTP;
2. consulta pedidos agregados;
3. pede novamente;
4. mantém favoritos;
5. consulta vouchers/cashback;
6. atualiza endereços.

### 11.6 Assinatura e cobrança recorrente

Fluxo típico:

1. empresa escolhe plano/período;
2. sistema cria/gerencia assinatura;
3. pagamento é conciliado;
4. acesso segue o estado comercial;
5. eventos e invoices preservam histórico do ciclo.

### 11.7 Fiscal

Fluxo atual:

1. pedido recebe diagnóstico fiscal;
2. sistema valida pendências;
3. prepara documento fiscal interno;
4. gera snapshot estruturado;
5. reserva série e número;
6. acompanha status interno.

Importante:

- este fluxo **não equivale ainda a emissão oficial via SEFAZ/prefeitura**.

### 11.8 iFood / marketplace

Fluxo atual:

1. integração iFood é cadastrada;
2. merchants são sincronizados;
3. webhook/polling materializam eventos;
4. pedido externo é carregado;
5. operação acompanha fila em `Pedidos iFood`;
6. ações operacionais podem ser disparadas;
7. pedido pode ser importado para o fluxo interno.

Complementos já implementados:

- reprocessamento manual de evento;
- cancelamento com motivos;
- recovery automático de falhas;
- resumo operacional enriquecido.

## 12. Integrações externas

### 12.1 Mercado Pago

Estado:

- integração real em andamento/ativa no domínio financeiro;
- uso para pedidos e assinatura;
- forte endurecimento de segurança e idempotência já documentado na memória.

Pontos estruturais:

- cartão tokenizado no cliente;
- backend não recebe PAN/CVV bruto;
- webhooks com validação antes de escrita;
- chaves e segredos mantidos fora do versionamento;
- tratamento de divergência financeira já previsto.

### 12.2 Fiscal

Estado:

- arquitetura pronta para provider registry;
- provider manual/draft-only existente;
- sem homologação oficial ativa neste retrato.

### 12.3 iFood

Estado:

- módulo de integração já existe;
- operacional de pedidos já funciona no código;
- catálogo e disponibilidade também já possuem base;
- a homologação final depende da empresa configurar suas próprias credenciais e operação do parceiro.

### 12.4 Webhooks internos/publicáveis

O sistema já possui:

- catálogo de eventos;
- subscriptions;
- deliveries;
- API keys por tenant;
- estrutura para integração com consumidores externos.

### 12.5 Geocodificação e serviços de localização

Há evidência de serviços dedicados:

- `CepLookupService`
- `ReverseGeocodeService`
- `LocalAddressMatcher`

Esses fluxos apoiam endereços e loja/delivery.

## 13. Jobs, scheduler e automações

O backend usa padrões de automação por:

- scheduler;
- jobs;
- filas;
- listeners;
- polling de integrações.

Exemplos importantes já visíveis:

- polling e recovery de marketplace;
- eventos/listeners de auditoria;
- processos de integração de pagamento;
- geocodificação;
- processos de reativação;
- fluxos de webhook e dispatch.

Observação operacional:

- o projeto já depende de execução regular de scheduler/worker para alguns blocos;
- em hospedagem compartilhada, isso sempre precisa de atenção extra para não deixar rotinas “existirem no código, mas não rodarem”.

## 14. Segurança e compliance

### 14.1 Princípios já aplicados

- tenant nunca é confiado a partir do frontend;
- recursos tenant-scoped validam posse;
- JWT segregado por identidade;
- blacklist de tokens;
- segredos sensíveis protegidos;
- auditoria de mutações relevantes;
- respostas padronizadas;
- throttling em rotas sensíveis;
- CORS controlado;
- frontend sem vetores explícitos de XSS conhecidos.

### 14.2 Padrões sensíveis já consolidados

- `assertBelongsToCurrentTenant` ou equivalente;
- `checkSubjectModel` na resolução JWT;
- `encrypted` para segredos reversíveis;
- hash para senha;
- denylist de campos sensíveis na auditoria;
- validação específica de assinatura de webhook;
- idempotência financeira.

### 14.3 Riscos que continuam exigindo vigilância

- anexos públicos quando servidos por storage público;
- dependência de execução correta de worker/scheduler em infra limitada;
- crescimento de tabelas centrais de pedidos/pagamentos;
- futuras integrações fiscais oficiais;
- homologação externa de parceiros.

## 15. Observabilidade e auditoria

O projeto possui uma base boa de rastreabilidade:

- `audit_logs` como trilha estruturada;
- eventos e listeners por mutação;
- códigos de erro padronizados;
- `request_id` no envelope;
- estado operacional em integrações;
- tabelas de eventos para assinatura, pagamento e webhooks.

Pontos fortes:

- mudança relevante tende a ser auditável;
- integrações já armazenam histórico operacional suficiente para suporte;
- o sistema diferencia operação, comercial e governança de forma consistente.

## 16. Deploy e operação

### 16.1 Modelo observado

- deploy via GitHub Actions;
- backend e frontend publicados separadamente;
- segredos via Actions/ambiente;
- documentação própria para Hostinger compartilhada;
- `.env.example` preparado para replicação de ambiente.

### 16.2 Cuidados operacionais permanentes

- garantir `php artisan migrate` em produção de forma controlada;
- garantir que cron do scheduler exista;
- garantir worker/filas conforme módulos habilitados;
- validar CORS e variáveis públicas do frontend;
- validar segredos de pagamento, fiscal e marketplace por empresa/ambiente;
- monitorar crescimento das tabelas de pedidos e pagamentos.

## 17. Estado atual por maturidade de domínio

### 17.1 Domínios maduros

- auth principal;
- multi-tenancy;
- RBAC híbrido;
- clientes;
- produtos;
- pedidos;
- estoque;
- relatórios;
- loja;
- portal do cliente;
- assinatura e cobrança;
- administração da plataforma.

### 17.2 Domínios robustos, mas ainda com frente de consolidação externa

- fiscal;
- iFood/marketplace;
- contador;
- webhooks públicos.

### 17.3 Domínios ainda naturalmente sensíveis a rollout e homologação

- pagamentos em produção por empresa;
- emissão fiscal oficial;
- integrações múltiplas de delivery;
- automações mais profundas de operação.

## 18. Gaps e atenção arquitetural real

Principais pontos a observar a partir do estado atual:

### 18.1 Banco e performance

- `orders`, `order_items` e `payments` já concentram volume significativo;
- toda tela nova de listagem deve nascer paginada e com payload mínimo;
- qualquer regressão em eager loading nesses módulos pode ficar cara rapidamente.

### 18.2 Integrações externas

- iFood e Mercado Pago exigem disciplina de ambiente, credenciais e idempotência;
- o módulo fiscal ainda precisa da camada oficial para fechar o ciclo regulatório completo.

### 18.3 Operação em hospedagem compartilhada

- jobs, cron e rotinas recorrentes sempre são um ponto de fragilidade;
- migrations precisam continuar compatíveis com limites do ambiente MySQL/MariaDB compartilhado.

### 18.4 Complexidade crescente no frontend

`web/` já concentra muitas experiências diferentes:

- app interno;
- portal;
- loja;
- contador.

Isso é viável, mas exige manter:

- fronteiras de contexto claras;
- clients HTTP segregados;
- providers separados;
- rotas e guards bem delimitados;
- disciplina forte de componentização e permissão.

## 19. Conclusão arquitetural

O Maskats, no estado atual, já é um **SaaS operacional de alta amplitude funcional**, com uma base arquitetural suficientemente madura para continuar crescendo sem recomeçar.

Os pilares mais fortes hoje são:

- separação de contexto por domínio;
- isolamento multiempresa;
- RBAC híbrido;
- camada de serviços consistente;
- base de relatórios e operação já conectada ao negócio real;
- preparação arquitetural para integrações, fiscal, contador e marketplace.

Os maiores cuidados para a próxima etapa não são “inventar arquitetura nova”, e sim:

1. preservar o padrão já consolidado;
2. evitar regressão de performance nos domínios centrais;
3. homologar corretamente os módulos que dependem de terceiros;
4. manter a documentação e a memória técnica sincronizadas com a evolução do código.

## 20. Referências internas recomendadas

Para aprofundamento técnico, consultar em conjunto:

- [README.md](/home/mtsdrf/workspace/maskats-saas/README.md)
- [CLAUDE.md](/home/mtsdrf/workspace/maskats-saas/CLAUDE.md)
- [.claude/memory/project-summary.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/project-summary.md)
- [.claude/memory/architecture-decisions.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/architecture-decisions.md)
- [.claude/memory/access-model.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/access-model.md)
- [.claude/memory/security-standards.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/security-standards.md)
- [.claude/memory/api-patterns.md](/home/mtsdrf/workspace/maskats-saas/.claude/memory/api-patterns.md)
- [docs/first-rollout-playbook.md](/home/mtsdrf/workspace/maskats-saas/docs/first-rollout-playbook.md)
- [docs/self-service-tenant-onboarding.md](/home/mtsdrf/workspace/maskats-saas/docs/self-service-tenant-onboarding.md)
- [docs/hostinger-shared-deploy.md](/home/mtsdrf/workspace/maskats-saas/docs/hostinger-shared-deploy.md)
