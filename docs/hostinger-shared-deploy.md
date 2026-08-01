# Deploy em Hostinger Compartilhada

Data de referência: 2026-08-01

> **Deploy automático disponível**: `.github/workflows/deploy.yml` builda `api/` + `web/` e publica em produção sozinho a cada push em `main` (ou disparo manual via `workflow_dispatch`), incluindo `php artisan migrate --force`. Neste cenário atual do PegaTicket, **não existe deploy do projeto `site/`**. Requer cadastrar os Secrets do GitHub uma única vez em Settings → Secrets and variables → Actions — ver a seção "GitHub Actions: secrets obrigatórios" abaixo.

## Cenário oficial atual

- Domínio base: `kleuza.com`
- Frontend web: `https://pegaticket.kleuza.com`
- API: `https://api-pegaticket.kleuza.com`

Paths já definidos no servidor:

- Web document root: `/home/u452434908/domains/kleuza.com/public_html/pegaticket/web`
- API document root do subdomínio: `/home/u452434908/domains/kleuza.com/public_html/pegaticket/api/public`

Ponto crítico:

- o workflow usa `DEPLOY_API_PATH` apontando para a **raiz** do Laravel:
  `/home/u452434908/domains/kleuza.com/public_html/pegaticket/api`
- o hPanel/Hostinger é que deve apontar o subdomínio
  `api-pegaticket.kleuza.com` para `${DEPLOY_API_PATH}/public`

## Objetivo

Publicar o PegaTicket em hospedagem compartilhada Hostinger com:

- frontend React em subdomínio dedicado
- backend Laravel em subdomínio dedicado
- fluxo de login e operação funcionando sem erro de CORS

## Arquitetura recomendada

### Domínios

- `https://pegaticket.kleuza.com` → frontend do `web/`
- `https://api-pegaticket.kleuza.com` → backend do `api/`

### Motivação

- em hospedagem compartilhada é mais simples servir o build estático do React no `public_html`
- o Laravel fica isolado no subdomínio da API, com `document root` apontando para `api/public`
- essa separação evita misturar o conteúdo do framework com os arquivos públicos do site

## Pré-requisitos

No hPanel da Hostinger:

1. criar o banco MySQL
2. ativar acesso SSH, se disponível no plano
3. criar o subdomínio `pegaticket.kleuza.com`
4. apontar `pegaticket.kleuza.com` para `/home/u452434908/domains/kleuza.com/public_html/pegaticket/web`
5. criar o subdomínio `api-pegaticket.kleuza.com`
6. apontar `api-pegaticket.kleuza.com` para `/home/u452434908/domains/kleuza.com/public_html/pegaticket/api/public`

## Variáveis de ambiente

### Backend `api/.env`

Usar [api/.env.example](/home/mtsdrf/workspace/pegaticket-saas/api/.env.example) como base e ajustar:

```env
APP_NAME=PegaTicket
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-pegaticket.kleuza.com
FRONTEND_URL=https://pegaticket.kleuza.com

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

CORS_ALLOWED_ORIGINS=https://pegaticket.kleuza.com

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
VITE_API_BASE_URL=https://api-pegaticket.kleuza.com/api/v1
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

- o deploy precisa ir para `/home/u452434908/domains/kleuza.com/public_html/pegaticket/api`
- o subdomínio da API precisa executar `api/public/index.php`

### Frontend

Enviar o conteúdo de `web/dist/` para:

`/home/u452434908/domains/kleuza.com/public_html/pegaticket/web`

Arquivos esperados na raiz pública:

- `index.html`
- pasta `assets/`

## Inicialização do banco

Se houver SSH disponível, rodar no servidor:

```bash
cd /home/u452434908/domains/kleuza.com/public_html/pegaticket/api
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

## GitHub Actions: secrets obrigatórios

Cadastrar em `GitHub > Settings > Secrets and variables > Actions > Repository secrets`:

- `DEPLOY_SSH_HOST`
- `DEPLOY_SSH_PORT`
- `DEPLOY_SSH_USER`
- `DEPLOY_SSH_PRIVATE_KEY`
- `DEPLOY_API_PATH`
- `DEPLOY_WEB_PATH`
- `VITE_API_BASE_URL`
- `FRONTEND_URL`
- `PAYMENT_PROVIDER`
- `R2_ENABLED`
- `MEDIA_AVATARS_DISK`
- `MEDIA_PRODUCTS_DISK`
- `MEDIA_TENANTS_DISK`
- `MEDIA_USE_DIRECT_PUBLIC_URLS`
- `MEDIA_PUBLIC_CACHE_SECONDS`
- `R2_ACCOUNT_ID`
- `R2_ACCESS_KEY_ID`
- `R2_SECRET_ACCESS_KEY`
- `R2_BUCKET_AVATARS`
- `R2_BUCKET_PRODUCTS`
- `R2_BUCKET_TENANTS`
- `R2_ENDPOINT`
- `R2_REGION`
- `R2_USE_PATH_STYLE_ENDPOINT`
- `VITE_VAPID_PUBLIC_KEY`
- `MERCADOPAGO_ENVIRONMENT`
- `MERCADOPAGO_ACCESS_TOKEN_TEST`
- `MERCADOPAGO_PUBLIC_KEY_TEST`
- `MERCADOPAGO_TEST_PAYER_EMAIL`
- `MERCADOPAGO_ACCESS_TOKEN_PROD`
- `MERCADOPAGO_PUBLIC_KEY_PROD`
- `MERCADOPAGO_WEBHOOK_SECRET`
- `VITE_MERCADOPAGO_ENVIRONMENT`
- `VITE_MERCADOPAGO_PUBLIC_KEY_TEST`
- `VITE_MERCADOPAGO_PUBLIC_KEY_PROD`

Opcional para smoke autenticado:

- `SMOKE_LOGIN_EMAIL`
- `SMOKE_LOGIN_PASSWORD`

Valores exatos deste cenário:

```txt
DEPLOY_API_PATH=/home/u452434908/domains/kleuza.com/public_html/pegaticket/api
DEPLOY_WEB_PATH=/home/u452434908/domains/kleuza.com/public_html/pegaticket/web
VITE_API_BASE_URL=https://api-pegaticket.kleuza.com/api/v1
FRONTEND_URL=https://pegaticket.kleuza.com
VITE_MERCADOPAGO_ENVIRONMENT=production
MERCADOPAGO_ENVIRONMENT=production
```

Se você ainda não tiver R2, VAPID ou Mercado Pago em produção, mantenha os
secrets correspondentes vazios ou com os valores já usados hoje no ambiente
real. O workflow não apaga valor existente no `.env` se o secret vier vazio.

## Checklist de validação pós-publicação

### API

1. abrir `https://api-pegaticket.kleuza.com/up`
2. abrir `https://api-pegaticket.kleuza.com/api/v1/auth/signup/plans`
3. confirmar resposta `200`

### Frontend

1. abrir `https://pegaticket.kleuza.com`
2. validar carregamento sem erro de CORS no console
3. validar login
4. validar troca de empresa
5. validar dashboard

### Fluxo mínimo de negócio

1. criar empresa
2. criar usuário da empresa
3. criar evento
4. criar lote/tipo de ingresso
5. realizar venda
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

- `FILESYSTEM_DISK=public`
- permissões de `storage/` e `bootstrap/cache/`

## Estratégia recomendada para o primeiro teste

1. publicar a API
2. validar `/up`
3. validar `/api/v1/auth/signup/plans`
4. publicar o frontend
5. testar login
6. só então iniciar o fluxo de cadastro de empresa e testes operacionais
