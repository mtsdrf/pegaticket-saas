# Configurar o deploy automático (CI/CD) — passo a passo do zero

Guia completo pra deixar `.github/workflows/deploy.yml` funcionando de ponta a ponta. Assume: instalação **nova**, banco de produção **vazio** (primeira vez subindo esse ambiente de verdade).

**Estrutura real do servidor (confirmada 2026-07-17)**:
- API: subdomínio `api.pegaticket.com`, arquivos em `/home/u452434908/domains/pegaticket.com/public_html/api`, document root do subdomínio = `.../public_html/api/public` (confirmado apontando certo, com `/public` no fim).
- Web: subdomínio `sistema.pegaticket.com`, arquivos em `/home/u452434908/domains/pegaticket.com/public_html/web`.

## Fase 1 — Pastas e subdomínios no hPanel

1. Via SSH (ou File Manager), criar as duas pastas base:
   ```bash
   mkdir -p /home/u452434908/domains/pegaticket.com/public_html/api/public
   mkdir -p /home/u452434908/domains/pegaticket.com/public_html/web
   ```
   (o `api/public` precisa existir já pra você conseguir apontar o document root pra ele no passo 2 — fica vazio até o primeiro deploy popular).

2. No hPanel → **Domínios** → **Subdomínios**, criar dois:
   - `api.pegaticket.com` — **Document Root**: `/home/u452434908/domains/pegaticket.com/public_html/api/public`
   - `sistema.pegaticket.com` — **Document Root**: `/home/u452434908/domains/pegaticket.com/public_html/web`

   ⚠️ **Crítico**: o document root da API tem que ser `api/public`, nunca `api/` sozinho — senão `.env`, `vendor/` e o código-fonte do Laravel ficam acessíveis publicamente pela internet.

## Fase 2 — Chave SSH dedicada ao deploy

No **seu computador** (não no servidor):

```bash
ssh-keygen -t ed25519 -C "deploy-pegaticket-github-actions" -f ~/.ssh/deploy_pegaticket -N ""
```
(`-N ""` = sem senha na chave — obrigatório, o GitHub Actions não tem como digitar senha interativamente).

Isso gera dois arquivos: `~/.ssh/deploy_pegaticket` (privada — **nunca compartilhar, nunca colar no chat comigo**) e `~/.ssh/deploy_pegaticket.pub` (pública).

Copiar a chave pública pro servidor:
```bash
ssh-copy-id -i ~/.ssh/deploy_pegaticket.pub -p <PORTA_SSH> <usuario_ssh>@<host_ssh>
```
Se `ssh-copy-id` não estiver disponível, alternativa manual: abrir `~/.ssh/deploy_pegaticket.pub`, copiar o conteúdo (uma linha só, começa com `ssh-ed25519`), colar no servidor no fim de `~/.ssh/authorized_keys` (criar o arquivo/pasta se não existir, `chmod 700 ~/.ssh` e `chmod 600 ~/.ssh/authorized_keys`).

Testar:
```bash
ssh -i ~/.ssh/deploy_pegaticket -p <PORTA_SSH> <usuario_ssh>@<host_ssh> "echo conectou"
```
Só avance se aparecer `conectou` sem pedir senha.

**Onde achar host/porta/usuário SSH**: hPanel → **Avançado** → **Acesso SSH**.

## Fase 3 — Chaves VAPID de produção (push notifications)

Produção precisa do próprio par de chaves VAPID, separado do seu `.env` local. Gerar agora (local, com o `api/` deste checkout):
```bash
cd api
php artisan tinker --execute="
\$k = \Minishlink\WebPush\VAPID::createVapidKeys();
echo 'PUBLIC: '.\$k['publicKey'].PHP_EOL;
echo 'PRIVATE: '.\$k['privateKey'].PHP_EOL;
"
```
Guarde os dois valores — vão pro `.env` do servidor (Fase 5) e o `PUBLIC` também vira um Secret do GitHub (Fase 4).

## Fase 4 — Secrets no GitHub

No repositório → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**, criar os 8 abaixo (a extensão do GitHub Actions que você instalou no VS Code também deve ter um painel pra isso, mas o caminho pelo site funciona sempre):

| Nome exato | Valor |
|---|---|
| `DEPLOY_SSH_PRIVATE_KEY` | `cat ~/.ssh/deploy_pegaticket` — cole o conteúdo INTEIRO, incluindo as linhas `-----BEGIN...-----`/`-----END...-----` |
| `DEPLOY_SSH_HOST` | Host SSH (hPanel → Acesso SSH) |
| `DEPLOY_SSH_PORT` | Porta SSH (hPanel → Acesso SSH, geralmente não é a 22 na Hostinger) |
| `DEPLOY_SSH_USER` | Usuário SSH (hPanel → Acesso SSH) |
| `DEPLOY_API_PATH` | `/home/u452434908/domains/pegaticket.com/public_html/api` |
| `DEPLOY_WEB_PATH` | `/home/u452434908/domains/pegaticket.com/public_html/web` |
| `VITE_API_BASE_URL` | `https://api.pegaticket.com/api/v1` |
| `VITE_VAPID_PUBLIC_KEY` | O `PUBLIC` gerado na Fase 3 |

## Fase 5 — Primeiro deploy (parcial, esperado falhar no último passo)

Disparar o workflow manualmente: aba **Actions** do repositório no GitHub (ou pela extensão do VS Code) → **Deploy para produção** → **Run workflow** → branch `main`.

Isso builda e envia `api/` e `web/dist/` pro servidor via rsync — mas o **último passo (`migrate --force`) vai falhar**, porque o `.env` do servidor ainda não existe. Esperado, é só isso mesmo — os arquivos já foram enviados com sucesso, que é o que importa agora.

## Fase 6 — Bootstrap manual do servidor (única vez)

Via SSH no servidor:
```bash
cd /home/u452434908/domains/pegaticket.com/public_html/api
cp .env.example .env
nano .env   # ou vi/editor de sua preferência
```

Preencher no `.env`:
```env
APP_NAME=PegaTicket
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.pegaticket.com

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

CORS_ALLOWED_ORIGINS=https://sistema.pegaticket.com

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

VAPID_PUBLIC_KEY=<o PUBLIC da Fase 3>
VAPID_PRIVATE_KEY=<o PRIVATE da Fase 3>
VAPID_SUBJECT=mailto:contato@pegaticket.com
```
(`MAIL_*` também, se for usar e-mail real — ver `api/.env.example` completo.)

Depois:
```bash
php artisan key:generate
php artisan jwt:secret
chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan db:seed --force
php artisan tenants:sync-permissions
php artisan optimize
```

`db:seed --force` só roda aqui, uma vez — não faz parte do pipeline automático (ver `docs/hostinger-shared-deploy.md`).

### Sincronização automática de variáveis novas no `.env`

A cada deploy, um passo do workflow lê `api/.env.example` e adiciona ao `.env` de produção qualquer chave que ainda não exista lá — nunca sobrescreve uma chave já configurada. Como `.env.example` nunca tem valor real de segredo (só nome de chave + placeholder vazio, disciplina já seguida no projeto), isso nunca expõe nada: se uma variável sensível nova for introduzida, ela entra no `.env` de produção **vazia**, e o valor real continua precisando ser preenchido manualmente via SSH. Pra qualquer variável nova não-sensível com default sensato já em `.env.example` (ex. `APP_LOCALE=pt_BR`), o valor certo já entra sozinho.

## Fase 7 — Confirmar o pipeline completo

Disparar o workflow de novo (Actions → Run workflow, ou só dar um push em `main`). Dessa vez o `.env` já existe, então `migrate --force`/`optimize` devem passar limpos — confirme que o job termina todo verde.

## Fase 8 — Validar

Checklist completo já existe em `docs/hostinger-shared-deploy.md` ("Checklist de validação pós-publicação") — resumo:
- `curl -I https://api.pegaticket.com/up` → 200
- `curl -I https://api.pegaticket.com/api/v1/auth/signup/plans` → 200
- `curl -I https://api.pegaticket.com/.env` → deve dar 404 (confirma que o document root está mesmo em `public/`, não expondo o `.env`)
- Abrir `https://sistema.pegaticket.com` no navegador, validar login, sem erro de CORS no console
- `curl -I https://sistema.pegaticket.com` → 200 (confirma que o `.htaccess` com `Header set` não deu 500 nesse domínio — ver achado documentado em `architecture-decisions.md` sobre o mesmo tipo de diretiva ter quebrado o subdomínio da API antes)

Dali em diante, todo push em `main` builda e publica sozinho.

## Regra obrigatória antes de concluir qualquer tarefa que impacte deploy

Antes de considerar uma tarefa como "finalizada", sempre executar uma validação mínima local compatível com a área alterada. Isso evita mandar para `main` erros só descobertos no GitHub Actions, como já aconteceu em **6 de agosto de 2026** em dois casos reais:

- teste backend ainda esperando alerta `low_stock`, embora o comportamento correto do PegaTicket já não expusesse mais esse alerta;
- E2E do admin ainda esperando filtro isolado antigo, depois da padronização das grids para filtro inline no ag-Grid.

Checklist obrigatório de encerramento:

1. Se houve mudança no backend, rodar `cd api && composer test`.
2. Se houve mudança no frontend, rodar `cd web && npm run build`.
3. Se houve mudança de tipagem/rotas/componentes do frontend, rodar `cd web && npx tsc --noEmit`.
4. Se houve mudança em fluxo coberto por Playwright, rodar pelo menos o spec ou grep afetado antes de encerrar.
5. Se a mudança alterar comportamento que aparece em CI/deploy, revisar se teste e implementação continuam alinhados ao padrão atual do produto.

Regra prática:

- não encerrar tarefa só porque o código "parece certo";
- encerrar tarefa apenas depois de validar o caminho impactado;
- se algum teste local não puder rodar por limitação de ambiente, registrar isso explicitamente junto com o risco residual.
