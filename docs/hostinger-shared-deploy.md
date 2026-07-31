# Deploy em Hostinger Compartilhada

Data de referência: 2026-07-12 (atualizado 2026-07-17: deploy automático via CI/CD disponível)

> **Deploy automático disponível**: `.github/workflows/deploy.yml` builda `api/`+`web/`+`site/` e publica em produção sozinho a cada push em `main` (ou disparo manual via `workflow_dispatch`), incluindo `php artisan migrate --force`. Requer cadastrar os Secrets do GitHub (chave SSH dedicada, host, caminhos, `VITE_*`) uma única vez em Settings → Secrets and variables → Actions — ver comentários no próprio workflow para a lista completa. O passo a passo manual abaixo continua documentado como referência do que o workflow faz por baixo (e como fallback se o CI/CD não estiver disponível).

## Terceiro subdomínio: `site/` (landing institucional/vendas)

Além de `api/` e `web/`, o monorepo tem um terceiro projeto irmão, `site/` (React 19 + Vite, sem autenticação) — a landing de vendas pública, publicada em **`site.pegaticket.com`**, separada do app principal (`sistema.pegaticket.com`, servido por `web/`) e do domínio raiz.

Setup manual único (fora do repo, não versionado — mesmo padrão de `api`/`web`):

1. No hPanel Hostinger, criar o subdomínio `site.pegaticket.com` apontando o document root para uma pasta dedicada no servidor (ex. `~/site.pegaticket.com/`).
2. Cadastrar o Secret `DEPLOY_SITE_PATH` no GitHub com esse caminho (mesmo padrão de `DEPLOY_API_PATH`/`DEPLOY_WEB_PATH`).
3. Opcionalmente, cadastrar o Secret `VITE_APP_URL` (URL do app principal, usada no CTA da landing) — se omitido, o workflow usa o fallback `https://sistema.pegaticket.com`.

Depois disso, o workflow builda `site/` (`npm ci && npm run build`) e publica `site/dist/` via `rsync` a cada deploy, sem gate de teste (não há suíte automatizada em `site/`, é conteúdo estático).

## Objetivo

Publicar o PegaTicket em hospedagem compartilhada Hostinger para iniciar testes reais, com:

- frontend React no domínio principal
- backend Laravel em subdomínio dedicado
- banco de produção inicializado com os seeders atuais
- fluxo de login e operação funcionando sem erro de CORS

## Arquitetura recomendada

### Domínios

- `https://seudominio.com` → frontend do `web/`
- `https://api.seudominio.com` → backend do `api/`

### Motivação

- em hospedagem compartilhada é mais simples servir o build estático do React no `public_html`
- o Laravel fica isolado no subdomínio da API, com `document root` apontando para `api/public`
- essa separação evita misturar o conteúdo do framework com os arquivos públicos do site

## Pré-requisitos

No hPanel da Hostinger:

1. criar o banco MySQL
2. ativar acesso SSH, se disponível no plano
3. criar o subdomínio `api.seudominio.com`
4. apontar o document root do subdomínio para a pasta `api/public`

## Variáveis de ambiente

### Backend `api/.env`

Usar [api/.env.example](/home/mtsdrf/workspace/pegaticket-saas/api/.env.example) como base e ajustar:

```env
APP_NAME=PegaTicket
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.seudominio.com

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

CORS_ALLOWED_ORIGINS=https://seudominio.com

DB_CONNECTION=mysql
DB_HOST=SEU_HOST_MYSQL
DB_PORT=3306
DB_DATABASE=SEU_BANCO
DB_USERNAME=SEU_USUARIO
DB_PASSWORD=SUA_SENHA

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=public
```

Se for usar e-mail real, preencher também `MAIL_*`.

### Frontend `web/.env.production`

```env
VITE_API_BASE_URL=https://api.seudominio.com/api/v1
```

## Build local recomendado

### Backend

No seu computador:

```bash
cd api
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Depois ajuste o `.env` com os dados reais da Hostinger.

### Frontend

```bash
cd web
npm ci
npm run build
```

## Publicação

### Backend

Enviar a pasta `api/` para o servidor, preservando a estrutura do projeto.

Ponto crítico:

- o subdomínio da API precisa executar `api/public/index.php`

### Frontend

Enviar o conteúdo de `web/dist/` para o `public_html/` do domínio principal.

Arquivos esperados na raiz pública:

- `index.html`
- pasta `assets/`

## Inicialização do banco

Se houver SSH disponível, rodar no servidor:

```bash
cd ~/caminho-do-projeto/api
php artisan migrate --force
php artisan db:seed --force
php artisan tenants:sync-permissions
php artisan optimize
```

### Observações

- não usar `migrate:fresh` em produção
- não usar `db:wipe` em produção
- `db:seed --force` é esperado no primeiro bootstrap porque o sistema depende dos grupos, funcionalidades, ações e planos iniciais — **não** faz parte do deploy contínuo (o workflow de CI/CD não roda seed, só `migrate --force` + `optimize`), rodar manualmente via SSH só quando fizer sentido (ex. plano/functionality novo)
- `php artisan storage:link` **não é mais necessário** — uploads (avatar/produto/logo) são gravados direto no banco (BLOB) desde 2026-07-17, não dependem mais de link simbólico pro disco (que também não funcionaria aqui: a Hostinger desabilita `symlink()` no PHP deste plano)

## Permissões de pasta

As pastas abaixo precisam ser graváveis pelo PHP:

- `api/storage`
- `api/bootstrap/cache`

Se houver SSH:

```bash
chmod -R 775 storage bootstrap/cache
```

## Worker e limitações da hospedagem compartilhada

Hoje o projeto usa `QUEUE_CONNECTION=database`, mas no estado atual da aplicação os listeners implementados no repositório não estão em fila dedicada com `ShouldQueue`.

Consequência prática:

- a aplicação consegue operar para os testes iniciais sem um worker residente
- ainda assim, manter `QUEUE_CONNECTION=database` preserva compatibilidade com a configuração padrão do projeto

Se no futuro entrarem jobs/filas reais, o ideal deixa de ser hospedagem compartilhada.

## Checklist de validação pós-publicação

### API

1. abrir `https://api.seudominio.com/up`
2. abrir `https://api.seudominio.com/api/v1/auth/signup/plans`
3. confirmar resposta `200`

### Frontend

1. abrir `https://seudominio.com`
2. validar carregamento sem erro de CORS no console
3. validar login
4. validar troca de empresa
5. validar dashboard

### Fluxo mínimo de negócio

1. criar empresa
2. criar usuário da empresa
3. criar cliente
4. criar produto
5. criar pedido
6. consultar dashboard e relatórios

## Diagnóstico rápido

### Se o frontend abrir, mas o login falhar

Validar:

- `VITE_API_BASE_URL`
- `APP_URL`
- `CORS_ALLOWED_ORIGINS`
- `JWT_SECRET`

### Se a API responder 500

Verificar:

- `storage/logs/laravel.log`
- permissões de `storage/` e `bootstrap/cache/`
- dados do MySQL

### Se imagens/arquivos públicos falharem

Verificar:

- `php artisan storage:link`
- `FILESYSTEM_DISK=public`

## Estratégia recomendada para o primeiro teste

1. publicar a API
2. validar `/up`
3. validar `/api/v1/auth/signup/plans`
4. publicar o frontend
5. testar login
6. só então iniciar o fluxo de cadastro de empresa e testes operacionais
