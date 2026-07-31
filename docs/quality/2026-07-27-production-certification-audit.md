# Auditoria Global de QA e Certificação para Produção

Data: 2026-07-28  
Escopo: monorepo `maskats-saas` (`api/` Laravel + `web/` React/Vite)

## Objetivo

Consolidar uma passagem global de QA com base na skill `testing.md`, mapear a cobertura atual, levantar cenários de certificação e definir o que ainda falta para considerar o Maskats pronto para produção com risco controlado.

## Metodologia usada

- leitura do contexto técnico e arquitetural já versionado no projeto
- leitura da malha de agentes e especializações em `.claude/agents`
- inventário das rotas reais do backend
- inventário das telas reais do frontend
- revisão da suíte automatizada existente
- execução dos gates técnicos disponíveis no repositório
- consolidação por risco, fluxo de usuário e prontidão operacional

## Evidências executadas nesta auditoria

- `cd web && npm run lint`
  - resultado final em 2026-07-28: verde sem warnings
- `cd web && npm run build`
  - resultado final em 2026-07-28: verde
- `cd web && npm run test:e2e`
  - resultado final em 2026-07-28: `13 passed`, `1 skipped (credenciais reais opcionais)`, `31.8s`
  - cobertura E2E implantada nesta rodada:
    - redirecionamento de usuário não autenticado para `/login`
    - mensagem amigável de falha no login
    - ocultação de links sem permissão no shell autenticado
    - seleção obrigatória da empresa ativa antes de entrar no sistema
    - visibilidade de `Assinatura da empresa` para proprietário mesmo sem permissão explícita do grupo
    - bloqueio de acesso direto à assinatura para usuário comum da empresa
    - redirecionamento de rota inexistente para `/`
    - shell mobile com hamburger e menu da conta
    - tela de `Pedidos manuais` em estado vazio, sem herdar filtros/cards da central operacional
    - tela de `Pedidos manuais` com listagem populada
    - tela de `Pedidos da loja` com board operacional e grid da fila pública
    - tela de `Assinatura da empresa` no fluxo de contratação inicial, com seleção de plano/período e gate de aceite dos termos
    - smoke pós-deploy para URL publicada, com autenticação real opcional via ambiente
- `cd api && composer test`
  - resultado final em 2026-07-28: `1129 passed`, `4491 assertions`, `30.97s`
  - observação desta rodada:
    - a suíte revelou uma regressão real no versionamento da logo da empresa
    - o problema fazia a URL da logo mudar ao editar dados do cadastro mesmo sem trocar a imagem
    - a correção foi estrutural, com `logo_updated_at` dedicado no domínio de tenants
    - a suíte completa voltou a fechar verde depois do ajuste
- `cd api && php artisan test tests/Feature/Tenant/TenantLogoTest.php`
  - resultado: `5 passed`
- `cd api && php artisan test --parallel --recreate-databases --without-tty`
  - resultado: indisponível no ambiente atual por dependência ausente do `ParaTest`
- `cd api && php artisan route:list --json`
  - resultado: `409` rotas mapeadas
- `rg -n "#\\[Test\\]|function test_" api/tests | wc -l`
  - resultado: `1129` casos de teste identificados no backend
- busca por suíte automatizada de frontend
  - resultado original em 2026-07-27: não foram encontrados `playwright`, `vitest`, `*.spec.*` ou `*.test.*` no `web/`
  - atualização em 2026-07-28: `Playwright` foi implantado com os arquivos `web/playwright.config.ts`, `web/e2e/auth.spec.ts`, `web/e2e/app-shell.spec.ts`, `web/e2e/navigation.spec.ts` e `web/e2e/support/api.ts`
- `.github/workflows/deploy.yml`
  - atualização em 2026-07-28: o pipeline de deploy passou a bloquear publicação se `npm run lint`, `npm run build` ou `npm run test:e2e` falharem no `web/`
- `cd web && npm run test:e2e:smoke`
  - disponibilizado em 2026-07-28 para smoke pós-deploy
  - funciona com `PLAYWRIGHT_BASE_URL` apontando para a URL publicada
  - o login real é opcional via `SMOKE_LOGIN_EMAIL` e `SMOKE_LOGIN_PASSWORD`

## Resumo executivo

O Maskats está em um estado bom de maturidade no backend e em um estado intermediário para avançado no frontend. A API possui cobertura automatizada extensa para regras críticas, multiempresa, permissões, assinatura, balcão, PDV, portal, fiscal parcial, marketplace e relatórios. O frontend saiu do estágio de validação apenas manual e agora já possui uma base E2E real em Playwright, incluindo shell, responsividade, governança de assinatura, pedidos manuais e smoke pós-deploy reaproveitável para URL publicada.

Conclusão objetiva desta auditoria:

- backend: pronto para rollout controlado
- frontend: funcional, com automação E2E inicial, mas ainda sem certificação robusta de regressão
- operação/deploy: aceitável para beta assistida
- certificação plena de produção: ainda não atingida

Status recomendado em 2026-07-28: `APTO PARA BETA CONTROLADA COM GATES AUTOMATIZADOS / AINDA NÃO CERTIFICADO PARA PRODUÇÃO PLENA IRRESTRITA`

## Inventário auditado

### Agentes e especialidades disponíveis

O projeto já opera com especialização distribuída em `.claude/agents`, incluindo frentes como:

- arquitetura de software e fluxo SaaS
- backend Laravel
- frontend React
- UI/UX
- QA
- fiscal
- pagamentos
- delivery/integrations

Na prática, isso já define um bom modelo de execução futura: cada bloco crítico deve sair com apoio do especialista funcional correspondente e validação de QA no fechamento.

### Backend

Base técnica observada:

- Laravel com rotas versionadas em `api/v1`
- autenticação JWT
- controle de acesso por permissões e perfil de empresa
- multi-tenancy por escopo de empresa
- módulos operacionais já extensos
- suíte Feature/Unit ampla

Principais grupos de rotas mapeados:

- `reports`: 31
- `orders`: 28
- `balcao`: 27
- `marketplace`: 23
- `accounting`: 19
- `auth`: 16
- `portal`: 16
- `loja`: 14
- `products`: 13
- `storefront-orders`: 13
- `subscription`: 13
- `stock`: 12
- `pdv`: 9

### Frontend

Base técnica observada:

- React + Vite
- roteamento central em `web/src/routes/AppRoutes.tsx`
- mapa de telas já documentado em `docs/frontend-screen-map.md`
- `92` rotas/telas finais mapeadas
- build atual está íntegro

Ponto estrutural crítico:

- existe agora uma suíte E2E inicial de frontend, mas ainda muito pequena para o tamanho do produto
- ainda não existe regressão ampla cobrindo os fluxos operacionais mais críticos
- não existe teste de componentes
- não existe teste de acessibilidade automatizado

### Banco de dados

Sinais positivos:

- evolução por migrations está madura
- seeders e planos já fazem parte do fluxo
- domínios relevantes já estão persistidos: assinatura, onboarding, marketplace, balcão, fila de espera, reserva, fiscal, contador, portal

Risco remanescente:

- falta um pacote formal de validações de integridade pós-migração para produção
- falta checklist automatizado de consistência de dados seedados x permissões x planos

## Cobertura funcional já perceptível no backend

A suíte de backend cobre, com boa profundidade, blocos como:

- autenticação, lockout, senha, reset, perfil, troca de email
- auto cadastro e aceite de termos
- multiempresa e vínculo de usuário
- permissões globais e por empresa
- assinatura, acesso por owner, trial e gates comerciais
- API keys
- auditoria
- balcão, comandas, reservas, fila de espera, KDS e snapshot offline
- PDV e sessões de operador
- pedidos internos e públicos
- estoque
- portal/loja
- fiscal e regras tributárias parciais
- contabilidade, mensagens e relatórios
- integrações e marketplace

Isso coloca o backend em um patamar acima do normal para um MVP SaaS operacional.

## Regressão corrigida durante esta auditoria

A suíte completa revelou inicialmente uma regressão no domínio de storefront, já corrigida nesta mesma auditoria.

Falha inicial:

- `BindingResolutionException: Target class [tenant_id] does not exist`

Origem:

- dois testes chamavam `OrderService::reject()` diretamente, fora do middleware tenant
- em produção, esse fluxo já roda com contexto tenant ativo; o problema estava na forma como o cenário de teste simulava a operação

Correção aplicada:

- os testes de storefront passaram a executar a recusa dentro de `TenantExecutionContext`
- o guard de segurança tenant foi preservado
- o backend voltou a fechar `1129 passed`

## Lacunas de QA mais importantes

### P0

- ampliar a base Playwright atual para cobrir os fluxos críticos completos
- validar integrações externas críticas em homologação assistida

### P1

- execução paralela da suíte backend indisponível no ambiente atual
- ausência de evidência automatizada de acessibilidade e navegação mobile
- ausência de benchmark controlado para telas com grandes volumes de dados

### P2

- ausência de testes sintéticos de observabilidade/health-check externo
- ausência de roteiro formal de caos operacional para perda de conexão, webhooks atrasados e reprocessamento

## Higiene técnica do frontend

Os warnings reais de lint observados no início desta auditoria foram saneados em 2026-07-28. A rodada final ficou verde com:

- estabilização de `useMemo` e `useCallback` nas páginas de treinamento, marketplace e recebíveis
- separação dos helpers de tokenização de cartão para fora do componente React
- conversão do mapa de blocos de configurações para `registry.ts`, eliminando ruído de fast refresh

## Correções estruturais validadas nesta rodada

- a URL de logo da empresa deixou de depender de `tenants.updated_at`
- foi criada a coluna `logo_updated_at` em `tenants`, atualizada apenas quando a imagem muda
- os resources públicos e autenticados passaram a usar esse timestamp dedicado no cache-busting da logo
- o pipeline de deploy agora executa lint, build e E2E do frontend antes de publicar

## Fluxos obrigatórios para certificação

### 1. Fluxos de autenticação e identidade

- login válido
- login inválido
- lockout por tentativas
- refresh de token
- logout
- recuperação de senha
- troca de senha
- troca de email
- aceite de convite de usuário da empresa
- auto cadastro de nova empresa

### 2. Fluxos de empresa e assinatura

- criação da empresa no onboarding
- owner acessando `Assinatura`
- usuário comum sem acesso ao menu de assinatura
- trial iniciado corretamente
- troca de plano
- cancelamento
- retomada de contratação
- bloqueios por assinatura suspensa
- comportamento de tela quando a empresa perde acesso por plano

### 3. Fluxos de permissão e multiempresa

- usuário global admin com acesso total
- owner da empresa com acesso comercial e administrativo restrito ao seu escopo
- usuário da empresa sem ver recursos de outras empresas
- ocultação de links/botões sem permissão
- tentativa direta por URL sem permissão
- troca de empresa ativa
- consistência do `access-profile` após refresh e falhas transitórias

### 4. Fluxos de catálogo e cadastros base

- produtos
- categorias
- clientes
- endereços
- usuários da empresa
- perfis da empresa
- grupos globais
- planos

### 5. Fluxos de pedidos

- pedido manual
- pedido interno
- pedido da loja online
- evolução de status
- cancelamento com motivo
- cálculo total
- desconto
- parcelamento
- pagamento
- código do pedido
- filtros, paginação, ordenação e performance do grid

### 6. Fluxos operacionais

- PDV completo
- abertura e fechamento de caixa
- sessão do operador por PIN
- balcão
- comanda
- mesas
- reservas
- fila de espera
- produção/cozinha
- expedição
- entrega
- quadros com arrastar e soltar

### 7. Fluxos financeiros e assinatura

- contratação
- checkout de assinatura
- pagamento com cartão
- cenários de indisponibilidade do provedor
- cancelamento
- renovação
- arrependimento dentro da janela
- ocultação de CTA quando a ação não é mais válida

### 8. Fluxos de integração

- Mercado Pago
- webhooks de assinatura
- marketplace/iFood e eventos operacionais
- portal/loja pública
- integrações fiscais configuráveis

### 9. Fluxos offline

- perda de internet
- retorno da internet
- não mostrar banner de reconexão quando nunca houve queda
- snapshot local
- filas pendentes
- conflitos multi-dispositivo
- bloqueio de fechamento com pendências

## Matriz de risco por área

| Área | Situação atual | Risco | Leitura |
| --- | --- | --- | --- |
| Auth/JWT | madura | baixo | backend com boa cobertura |
| Permissões | madura | médio | precisa E2E para ocultação visual e deep-link |
| Multiempresa | madura | médio | bom backend, ainda depende de prova visual completa |
| Assinatura | intermediária | médio/alto | integrações externas e UX de bloqueios exigem smoke real |
| Pedidos | intermediária/alta | médio | regras fortes no backend, grids e fluxos visuais precisam regressão |
| PDV/Balcão | intermediária | alto | fluxo operacional exige testes de campo e offline |
| Marketplace | intermediária | alto | depende de credenciais reais e homologação externa |
| Fiscal | parcial | alto | sem integração externa ativa, não certificar como módulo concluído |
| Frontend geral | funcional | alto | sem suíte automatizada |
| Deploy/rollback | intermediário | médio | existe pipeline, faltam smoke checks automáticos |

## Certificação por perfil de usuário

### Administrador global

Cobertura atual: boa  
Pendência para certificar: smoke visual e permissão ponta a ponta

### Proprietário da empresa

Cobertura atual: boa na API, média na interface  
Pendência para certificar: assinatura, onboarding, plano, checkout, menus condicionais

### Operador/atendente

Cobertura atual: média  
Pendência para certificar: pedidos, balcão, PDV, status, cancelamentos e mobile

### Cozinha/produção

Cobertura atual: média  
Pendência para certificar: fila, atualização visual em tempo real, drag and drop, tempos e auditoria

### Expedição/entrega

Cobertura atual: média/baixa  
Pendência para certificar: fluxo integral com transições e exceções

### Cliente final

Cobertura atual: média  
Pendência para certificar: loja pública, reservas, checkout e comunicação de erros

## Comparação de prontidão

### O que já sustenta produção assistida

- backend testado em profundidade
- build de frontend saudável
- arquitetura modular já bem distribuída
- controle de acesso já estruturado
- multiempresa já incorporado ao domínio
- fluxo comercial de assinatura já existe

### O que ainda impede certificação plena

- falta automação de UI
- falta automação E2E
- falta smoke pós-deploy
- falta bateria formal de testes mobile
- falta homologação formal dos fluxos externos mais sensíveis

## Quality gates recomendados

### Gate P0 obrigatório para certificar

- `php artisan test` totalmente verde
- `npm run lint` sem warnings críticos
- `npm run build` verde
- suíte E2E mínima cobrindo login, troca de empresa, pedido, PDV/balcão básico, assinatura e logout
- smoke pós-deploy em staging ou produção controlada
- checklist manual mobile concluído

### Gate P1 recomendado logo em seguida

- testes de componentes dos blocos críticos
- teste automatizado de acessibilidade
- cenários de erro de API padronizados
- teste de volume para grids e listagens principais

## Backlog priorizado de certificação

### P0

- implantar Playwright no `web/`
- criar suíte E2E dos fluxos críticos
- criar smoke pós-deploy
- corrigir warnings de lint do frontend
- criar massa seedada de QA previsível para ambiente de homologação

### P1

- adicionar Vitest/RTL para componentes críticos
- adicionar testes de acessibilidade por rota principal
- validar performance de `clientes`, `pedidos`, `grids` e painéis operacionais
- formalizar checklist de homologação de integrações externas

### P2

- health-checks sintéticos externos
- testes de resiliência de webhooks
- matrizes de compatibilidade por navegador/dispositivo

## Recomendação final

O sistema não deve ser tratado hoje como “certificado para produção plena irrestrita”. A auditoria desta segunda-feira, **27 de julho de 2026**, fechou o backend totalmente verde, mas o frontend ainda não possui automação suficiente para certificação plena. O caminho seguro é:

1. manter backend como base estável
2. fechar o pacote P0 de QA no frontend
3. executar homologação assistida por perfil
4. publicar primeiro em rollout controlado
5. só então promover para certificação plena

Se for necessário subir antes disso, a recomendação é operar como `beta assistida`, com monitoramento próximo, massa de teste controlada e janela de rollback definida.
