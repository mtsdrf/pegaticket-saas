# PegaTicket — CLAUDE.md

## Resumo
PegaTicket é um SaaS multi-tenant, monorepo com projetos irmãos na raiz (pasta do repositório permanece `pegaticket-saas/` por estabilidade de path/git, mas o nome final do produto é **PegaTicket**):
```
pegaticket-saas/
  api/    Backend Laravel 13 (existente)
  web/    Frontend React web (existente, criado 2026-07-05)
  site/   Landing pública/institucional para o domínio raiz pegaticket.com (existente, criado 2026-07-22) — sem autenticação, projeto Vite separado; diferente do app autenticado (web/) e do Portal do cliente final (/portal/* dentro de web/)
  app/    App mobile/nativo (futuro, ainda não iniciar)
```

## Stack
- Backend: Laravel 13, PHP 8.3+, MySQL/MariaDB, JWT (`php-open-source-saver/jwt-auth` ^2.9), l5-swagger ^11 (docs En/PtBR).
- Frontend web (`web/`): React 19 + TypeScript + Vite + react-router-dom v7 + axios. Projeto separado do `api/` (não usar `api/resources/js`).
- Banco: MySQL, migrations Laravel, UUID público + PK incremental interna.

## Arquitetura backend (`api/app/`)
Fluxo por feature: `Http/Requests` (validação) → `Http/Controllers` (fino, só orquestra) → `Services` (regra de negócio, transações, eventos) → `Repositories/Contracts` + `Repositories/Eloquent` (persistência) → `Http/Resources` (formato de saída). Entrada mutável em `DTOs/{Feature}/Create*DTO.php` e `Update*DTO.php` (`fromArray()` a partir de `$request->validated()`).

Auditoria: toda mutação relevante dispara um Event (`Events/{Feature}/{Feature}Created|Updated|Deleted`) consumido por um Listener em `Listeners/{Feature}/Audit*` que grava em `AuditLog` via `WriteAuditLog`/trait `Auditable`.

Multi-tenancy: middleware `tenant` (`ResolveTenant`) resolve o tenant ativo e popula os helpers globais `tenant()` / `tenant_id()` (`app/Support/tenant.php`). Rotas tenant-scoped (`tenant-roles`, `tenant-users`) exigem esse middleware.

Permissões: middleware `perm:{functionality_slug},{action_key}` → `CheckPermission` → `PermissionService::userCanViaGroups()`. Permissão é sempre via Group (usuário → Group → GroupPermission → Functionality+Action), não direto no usuário.

Modelos: `BaseModel` (abstract) já dá `SoftDeletes` + `HasUuid` + auto-preenchimento de `created_by`/`updated_by`/`deleted_by` via `Auth::id()`. Todo novo Model de domínio deve extender `BaseModel`, não `Model` puro.

## Padrão de API
RESTful sob `routes/api.php`, prefixo `v1`. Toda rota autenticada usa `throttle:{max},{minutes},{nome-unico}`. Rotas tenant-scoped levam `tenant` antes de `perm:`.

Resposta de sucesso (via `App\Services\APIResponse::success`):
```json
{ "success": true, "message": "...", "data": {}, "meta": { "request_id": "..." } }
```
Resposta de erro (via `APIResponse::error`):
```json
{ "success": false, "message": "...", "code": "ERROR", "errors": {}, "meta": {} }
```
Mensagens sempre via `__('messages.{feature}.{acao}')` (i18n, não hardcode string).

## Banco de dados
- PK `id` incremental interna + `uuid` público único indexado (rotas usam `uuid` via `HasUuid::getRouteKeyName`).
- `created_by`/`updated_by`/`deleted_by` (unsignedBigInteger nullable, indexado) em toda tabela de domínio.
- `timestamps()` + `softDeletes()` padrão.
- FKs com `constrained()->cascadeOnDelete()`; unique compostas nomeadas explicitamente (ex: `uniq_tenant_user`).
- Nome de tabela plural snake_case; pivots com nome descritivo (`group_user`, `group_permissions`, `tenant_role_permissions`).

## Como rodar
Backend:
```bash
cd api
composer setup   # install, .env, key:generate, migrate, npm install/build
php artisan jwt:secret   # necessário em .env novo, não é gerado pelo key:generate
composer dev     # serve + queue:listen + pail + vite, tudo junto
composer test
```
Frontend (`web/`):
```bash
cd web
npm install
cp .env.example .env   # VITE_API_BASE_URL=http://localhost:8000/api/v1
npm run dev
npm run build   # tsc -b && vite build
npm run lint    # oxlint
```

## Como o Claude deve trabalhar aqui
0. Tarefa de backend → agir como `.claude/agents/laravel-php-master.md`. Tarefa de frontend → agir como `.claude/agents/react-19-master.md`. São os dois agentes técnicos principais do projeto (ver "Agentes especialistas obrigatórios" abaixo).
1. Antes de alterar algo, ler `.claude/memory/project-summary.md`, `architecture-decisions.md`, `coding-standards.md`, `database-rules.md`, `api-patterns.md`. **Todo caminho `.claude/...` neste arquivo é relativo à raiz do projeto (`/home/mtsdrf/workspace/pegaticket-saas/`)** — em comandos de shell/briefs de agente, usar o caminho absoluto ou garantir que o cwd está na raiz antes de ler, nunca assumir que o cwd é a raiz por padrão. Tarefas de backend costumam fazer `cd api/` no meio da execução (não existe `api/.claude/`), e um `cat .claude/memory/x.md` depois disso falha silenciosamente com "arquivo inexistente" — já aconteceu (2026-07-09), sem perda de conteúdo, só leitura no lugar errado.
2. Seguir o fluxo Request→Controller→Service→Repository→Resource já estabelecido. Não pular camadas.
3. Toda mutação de domínio dispara Event + Listener de auditoria, seguindo o padrão existente em `Group`/`User`/`Tenant`.
4. Não reescrever arquivos inteiros — editar apenas o trecho necessário.
5. Atualizar o arquivo de memória relevante após decisão de arquitetura, regra de API ou de banco.
6. Frontend web vive em `web/` (React+Vite, projeto próprio, não em `api/resources/js`). `app/` é reservado para um app mobile/nativo futuro — não criar ainda.

## Economia de tokens
- Não reexplicar o que já está aqui ou na memória.
- Preferir diffs/patches a arquivos inteiros.
- Sem comentários redundantes no código gerado.
- Respostas curtas e diretas.

## Aviso de infraestrutura
Repositório git único na raiz de `pegaticket-saas/` (monorepo `api/`+`web/`), criado em 2026-07-08, remoto `git@github.com:mtsdrf/pegaticket-saas.git`. `api/` e `web/` **não** têm `.git` próprio (histórico anterior desta nota estava desatualizado). O diretório pai `/home/mtsdrf/workspace` tem um `.git` separado e acidental (sem commits, com centenas de arquivos de OUTROS projetos — `1Razzy`, `allugai`, `chronos-pomodoro`, `fundamentos-react`, incluindo `node_modules/` — já staged). Não é o repositório deste projeto — não commitar nada ali sem confirmar com o usuário antes.

## Agentes especialistas obrigatórios

Este projeto usa dois agentes técnicos principais:

```txt
.claude/agents/laravel-php-master.md
.claude/agents/react-19-master.md
```

Os agentes `backend-laravel.md`, `frontend-react.md`, `database-architect.md`, `code-reviewer.md` e `token-optimizer.md` (também em `.claude/agents/`) continuam disponíveis como apoio pontual (revisão, banco, economia de tokens), mas Laravel PHP Master e React 19 Master são a referência técnica principal para qualquer decisão de arquitetura de backend/frontend.

### Laravel PHP Master

Usar este agente para qualquer tarefa envolvendo:

* Laravel 13.
* PHP.
* API RESTful.
* Controllers.
* Requests.
* Resources.
* Services.
* Repositories.
* Actions.
* DTOs.
* Models.
* Migrations.
* Banco de dados.
* Queries.
* Performance backend.
* Segurança backend.
* Testes de API.
* Arquitetura backend.

Antes de alterar backend, consultar:

```txt
.claude/agents/laravel-php-master.md
.claude/memory/coding-standards.md
.claude/memory/database-rules.md
.claude/memory/api-patterns.md
.claude/memory/security-standards.md
```

### React 19 Master

Usar este agente para qualquer tarefa envolvendo:

* React 19.
* Componentes.
* Pages.
* Hooks.
* Services frontend.
* Estado.
* Formulários.
* Rotas.
* UI/UX.
* Acessibilidade.
* Performance frontend.
* Integração com API.
* Testes frontend.
* Arquitetura frontend.

Antes de alterar frontend, consultar:

```txt
.claude/agents/react-19-master.md
.claude/memory/coding-standards.md
.claude/memory/project-summary.md
.claude/memory/api-patterns.md
.claude/memory/security-standards.md
```

## Regra de atuação conjunta

Quando uma tarefa envolver backend e frontend, os dois agentes devem atuar em conjunto.

Fluxo obrigatório:

1. Laravel PHP Master define ou revisa a API.
2. React 19 Master define ou revisa o consumo da API e a interface.
3. Ambos verificam impacto de performance.
4. Ambos evitam duplicação e excesso de código.
5. Ambos atualizam a memória do projeto.
6. Ambos priorizam economia de tokens.

## Regra de documentação oficial

Quando houver dúvida sobre recurso específico de versão, comportamento novo, breaking change, pacote ou API do framework, consultar a documentação oficial antes de implementar.

Nunca inventar recursos inexistentes.

## Regra de não repetição de erro

Quando um erro for corrigido:

1. Registrar causa raiz de forma curta.
2. Ajustar o código no ponto correto.
3. Procurar padrões semelhantes.
4. Evitar repetir o mesmo padrão em novas implementações.
5. Criar teste ou checklist quando fizer sentido.

## Agente especialista em UI/UX

Este projeto usa um agente especialista em design de interface e experiência do usuário:

```txt
.claude/agents/ui-ux-master.md
```

### UI UX Master

Usar este agente para qualquer tarefa envolvendo:

* UI Design.
* UX Design.
* Design system.
* Paletas de cor.
* Tema claro.
* Tema escuro.
* Componentes visuais.
* Layouts.
* Responsividade.
* Acessibilidade.
* Microinterações.
* Redesign de telas.
* Conversão de HTML puro para React.
* Componentização visual.
* Melhoria de interfaces com aparência amadora.
* Criação de interfaces modernas inspiradas em produtos de alto padrão.

Antes de alterar qualquer interface, consultar:

```txt
.claude/agents/ui-ux-master.md
.claude/agents/react-19-master.md
.claude/memory/coding-standards.md
.claude/memory/project-summary.md
```

## Atuação conjunta dos agentes de interface

Quando a tarefa envolver telas React, os agentes devem atuar juntos:

1. UI UX Master define experiência, layout, hierarquia visual, paleta, responsividade e acessibilidade.
2. React 19 Master implementa a interface com componentização correta, performance e boas práticas React.
3. Laravel PHP Master é acionado quando a tela depender de alteração ou revisão de API.
4. Todos devem preservar funcionalidades existentes.
5. Todos devem evitar código duplicado.
6. Todos devem atualizar a memória do Claude quando houver mudança relevante.

## Regra para conversão de HTML para React

Quando receber um template HTML puro, o Claude deve usar obrigatoriamente o UI UX Master e o React 19 Master.

Fluxo:

1. Mapear o template.
2. Separar seções.
3. Identificar componentes reutilizáveis.
4. Converter HTML para JSX.
5. Transformar scripts em lógica React.
6. Separar dados repetidos em arrays.
7. Adaptar estilos ao padrão do projeto.
8. Garantir tema claro e escuro quando aplicável.
9. Melhorar acessibilidade.
10. Preservar ou melhorar o visual original.
11. Remover código morto.
12. Validar responsividade.

## Regra para paletas e temas

Toda nova paleta deve ter versão light e dark.

Toda nova interface deve evitar cores soltas e priorizar tokens.

Toda decisão visual importante deve ser registrada de forma curta em:

```txt
.claude/memory/coding-standards.md
```

ou, se existir, em um arquivo específico de design:

```txt
.claude/memory/design-system.md
```

Caso `design-system.md` não exista e o projeto passe a ter padrões visuais próprios, criar esse arquivo.

## Agentes especialistas de qualidade e revisão

Este projeto usa dois agentes especialistas para qualidade, testes e revisão técnica:

```txt
.claude/agents/qa-testing-master.md
.claude/agents/code-review-architect.md
```

### QA Testing Master

Usar este agente para qualquer tarefa envolvendo:

* Testes automatizados.
* Testes de API.
* Testes Feature Laravel.
* Testes Unit.
* Testes React.
* Testes de componentes.
* Testes end-to-end quando aplicável.
* Validação de regras de negócio.
* Validação de permissões.
* Validação de erros.
* Prevenção de regressões.
* Checklists de QA.
* Bugs corrigidos.
* Cenários críticos.

Antes de finalizar qualquer alteração relevante, consultar:

```txt
.claude/agents/qa-testing-master.md
.claude/memory/project-summary.md
.claude/memory/api-patterns.md
.claude/memory/coding-standards.md
```

### Code Review Architect

Usar este agente para qualquer tarefa envolvendo:

* Revisão de código.
* Revisão de arquitetura.
* Segurança.
* Performance.
* Padronização.
* Redução de complexidade.
* Refatoração.
* Separação de responsabilidades.
* Revisão de banco de dados.
* Revisão de API.
* Revisão frontend.
* Revisão UI/UX.
* Prevenção de dívida técnica.

Antes de aprovar qualquer implementação relevante, consultar:

```txt
.claude/agents/code-review-architect.md
.claude/agents/laravel-php-master.md
.claude/agents/react-19-master.md
.claude/agents/ui-ux-master.md
.claude/agents/qa-testing-master.md
.claude/memory/security-standards.md
```

### Security Specialist

Este projeto usa um agente especialista em segurança da informação:

```txt
.claude/agents/security-specialist.md
```

Usar este agente para qualquer tarefa envolvendo:

* Auditoria de segurança (backend, frontend, banco).
* Autenticação e JWT (staff, Portal, módulo do contador).
* Isolamento multi-tenant / prevenção de IDOR.
* Dados sensíveis (criptografia, mascaramento, denylist de auditoria).
* Rate limiting e proteção contra brute-force.
* Validação de entrada, mass assignment, SQL injection.
* Headers de segurança, CORS, CSP.
* Vulnerabilidades de dependências (`composer audit`/`npm audit`).
* LGPD aplicada a dado técnico (não substitui validação jurídica).

Antes de implementar qualquer módulo novo que toque autenticação, dado sensível, pagamento ou dado de outra empresa, consultar:

```txt
.claude/agents/security-specialist.md
.claude/memory/security-standards.md
```

`security-standards.md` traz o checklist "sempre verificar" (auth, isolamento de tenant, dado sensível, rate limit, validação) que todo módulo novo deve satisfazer antes de ser considerado pronto — mesma exigência que já vale pra `coding-standards.md`/`database-rules.md`/`api-patterns.md`.

## Fluxo obrigatório de entrega com agentes

Para qualquer alteração relevante no projeto, usar este fluxo:

1. Agente especialista da área propõe ou implementa a solução.
2. QA Testing Master identifica cenários de teste e regressão.
3. Code Review Architect revisa arquitetura, segurança, performance e padrão.
4. Ajustes são feitos apenas onde necessário.
5. Memória do Claude é atualizada de forma curta quando houver decisão relevante.
6. Entrega final inclui checklist objetivo.

## Regra para bugs

Quando um bug for corrigido:

1. Identificar causa raiz.
2. Corrigir no ponto certo.
3. Procurar ocorrências semelhantes.
4. Acionar QA Testing Master para teste de regressão.
5. Acionar Code Review Architect para revisar impacto.
6. Registrar aprendizado curto na memória do Claude.

## Regra para aprovação final

Nenhuma alteração relevante deve ser considerada finalizada sem:

```txt
- Revisão de segurança.
- Revisão de performance.
- Revisão de arquitetura.
- Cenários de teste considerados.
- Risco de regressão avaliado.
- Padrão do projeto preservado.
```

## Identidade visual oficial da PegaTicket

### Arquivos obrigatórios de referência

```txt
.claude/memory/brand-guidelines.md
.claude/memory/design-system.md
.claude/memory/ui-redesign-plan.md
.claude/skills/pegaticket-visual-identity.md
.claude/skills/pegaticket-theme-system.md
.claude/skills/html-to-react-rebrand.md
.claude/agents/ui-ux-master.md
```

Ler nessa ordem antes de qualquer trabalho visual: `brand-guidelines.md` (o que a marca é) → `design-system.md` (paleta/tokens/regras de componente) → `ui-redesign-plan.md` (o que já foi diagnosticado e a ordem de execução planejada).

### Direção de marca

Nome **PegaTicket** (fixo, não alterar). Tagline: "Gestão clara para empresas em movimento." Conceito: SaaS de gestão comercial — claro, moderno, produtivo, inteligente, levemente premium. Símbolo de marca: `M` geométrico com movimento sutil; nunca seta literal, gráfico de barras/financeiro ou ícone genérico de analytics.

### Regras visuais obrigatórias

- **Mobile-first é prioridade máxima**: o PegaTicket é usado majoritariamente no celular (confirmado pelo usuário em 2026-07-05). Toda tela é desenhada primeiro para mobile (base do CSS) e enriquecida via `min-width` para tablet/desktop — nunca o inverso. Alvo de toque ≥44px; nenhuma ação crítica depende só de `:hover`.
- Toda cor vem de tokens `--pt-*` (ver `pegaticket-theme-system.md`) — nunca hex hardcoded em componente.
- Paleta oficial light/dark é a única permitida (`design-system.md`).
- Tipografia: Inter na interface; Manrope/Geist/Inter SemiBold na marca.
- Um CTA primário por tela; cards como unidade padrão; sidebar com estado ativo sempre evidente (e vira drawer/bottom-nav em mobile, não só "colapsa").
- Todo estado (loading, vazio, erro, sucesso) tratado visualmente — nunca tela em branco ou mensagem técnica crua.

### Regras para login

Fundo com gradiente sofisticado + elementos abstratos sutis de movimento (nunca imagem financeira genérica). Card moderno, logo PegaTicket visível. Textos oficiais: headline "Bem-vindo ao PegaTicket", subheadline "Gestão clara para empresas em movimento.", botão "Entrar no painel", link secundário "Atualizar sistema".

### Regras para dashboard

Separar sempre cabeçalho da página → ações rápidas → métricas → gráfico → navegação. Título "Visão geral", subtítulo "Acompanhe os principais números da operação.". Ações rápidas: Novo pedido / Adicionar cliente / Cadastrar produto. Métricas: Pedidos entregues / Pedidos pendentes / Valor recebido. Evitar botões azuis gigantes, cards azuis pesados repetidos, azul chapado em todo bloco, gráfico sem contexto, sidebar sem estado ativo claro.

### Regras de tema

Tokens `--pt-*` em `:root` (claro) e `[data-theme='dark']` (escuro), com fallback por `prefers-color-scheme` quando não houver preferência salva. Preferência manual do usuário persiste em `localStorage`. Nunca inverter cor sem ajustar contraste/sombra — ver `pegaticket-theme-system.md`.

### Regra de segurança

Rebranding é **puramente visual**. Não altera autenticação, rotas, permissões, contratos de API ou banco de dados. Qualquer mudança que pareça exigir alteração de regra de negócio deve ser sinalizada e confirmada antes de prosseguir, não assumida.

### Fluxo obrigatório antes de aplicar rebranding

1. Ler `brand-guidelines.md` + `design-system.md` + `ui-redesign-plan.md`.
2. UI UX Master define layout, paleta aplicada, hierarquia e responsividade da tela.
3. React 19 Master implementa com componentização e boas práticas React.
4. QA Testing Master valida que nada de comportamento/regra quebrou (login continua autenticando, troca de tenant continua funcionando etc.).
5. Code Review Architect revisa consistência visual, tokens, acessibilidade e ausência de regressão.
6. Memória (`design-system.md`/`brand-guidelines.md`/`ui-redesign-plan.md`) atualizada se alguma decisão visual mudar durante a implementação.

### Agentes envolvidos

```txt
.claude/agents/ui-ux-master.md          (dono da direção visual)
.claude/agents/react-19-master.md       (implementação React)
.claude/agents/qa-testing-master.md     (validação sem regressão)
.claude/agents/code-review-architect.md (revisão final)
```

## Agente especialista em engenharia reversa de banco de dados

Este projeto usa um agente especialista em interpretar dumps, backups e estruturas de banco de dados:

```txt
.claude/agents/database-reverse-engineer.md
```

### Database Reverse Engineer

Usar este agente para qualquer tarefa envolvendo:

- Análise de dump SQL.
- Análise de backup de banco.
- Engenharia reversa de schema.
- Identificação de tabelas.
- Identificação de primary keys.
- Identificação de foreign keys.
- Identificação de constraints.
- Identificação de índices.
- Identificação de views.
- Identificação de triggers.
- Identificação de functions/procedures.
- Mapeamento de entidades.
- Mapeamento de relacionamentos.
- Descoberta de módulos.
- Levantamento de funcionalidades.
- Planejamento de CRUDs.
- Planejamento de APIs.
- Planejamento de telas.
- Plano de implementação baseado no banco.

Antes de implementar funcionalidades a partir de um banco existente, consultar:

```txt
.claude/agents/database-reverse-engineer.md
.claude/agents/laravel-php-master.md
.claude/agents/react-19-master.md
.claude/agents/qa-testing-master.md
.claude/agents/code-review-architect.md
```

### Fluxo obrigatório para análise de backup

Quando o usuário fornecer um dump ou backup de banco, Claude deve:

1. Identificar a tecnologia do banco.
2. Extrair primeiro apenas a estrutura.
3. Mapear schemas, tabelas, colunas, PKs, FKs, constraints e índices.
4. Mapear views, triggers, functions e procedures.
5. Classificar tabelas por tipo.
6. Identificar entidades principais.
7. Identificar módulos funcionais.
8. Identificar CRUDs necessários.
9. Identificar fluxos de negócio prováveis.
10. Separar regras confirmadas de inferências.
11. Criar plano de implementação em fases.
12. Criar dúvidas para validação com o usuário.
13. Não implementar código antes do plano ser aprovado.

### Arquivos de análise recomendados

Para dumps grandes, salvar a análise em:

```txt
.claude/memory/database-analysis/
  01-schema-overview.md
  02-entities-map.md
  03-relationships-map.md
  04-modules-map.md
  05-crud-plan.md
  06-business-rules.md
  07-implementation-roadmap.md
```

### Regra de segurança para dumps

Dumps podem conter dados sensíveis.

Claude deve:

- Evitar expor dados reais desnecessariamente.
- Não copiar senhas, tokens, documentos, e-mails ou dados pessoais para documentação.
- Usar exemplos anonimizados.
- Não commitar dumps de produção.
- Não gerar logs com dados sensíveis.
- Não usar dados reais em testes sem anonimização.

### Atuação conjunta

Ao transformar análise do banco em sistema:

1. Database Reverse Engineer interpreta o banco.
2. Laravel PHP Master define arquitetura backend, models, requests, resources, services, controllers e rotas.
3. React 19 Master define telas, componentes, hooks, services e formulários.
4. UI UX Master define experiência visual e usabilidade.
5. QA Testing Master define testes e cenários de regressão.
6. Code Review Architect revisa arquitetura, segurança, performance e padronização.