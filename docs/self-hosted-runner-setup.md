# Runner self-hosted do GitHub Actions (Linux)

Motivo: a Hostinger bloqueia conexão SSH vinda do IP dinâmico dos runners hospedados pelo GitHub (confirmado — teste manual do mesmo host/porta/chave funciona do computador do usuário, mas o runner do GitHub trava até timeout tentando `ssh-keyscan`, sem resposta nenhuma: bloqueio de firewall por origem de IP, não problema de credencial). `deploy.yml` já foi ajustado pra `runs-on: self-hosted` — falta instalar o runner numa máquina Linux com IP que a Hostinger já aceita (a mesma de onde o SSH manual já funcionou).

Rodar tudo isso na máquina Linux escolhida (pode ser esta mesma, se ela ficar sempre ligada — se for desligar/hibernar, os deploys automáticos ficam parados até ligar de novo).

## 1. Gerar o token de registro (no GitHub, uma vez por instalação)

No navegador: repositório → **Settings** → **Actions** → **Runners** → **New self-hosted runner** → escolher **Linux** / **x64**.

O GitHub mostra um bloco de comandos com um `--token` específico — **esse token expira em poucos minutos**, precisa ser usado na hora, não dá pra reaproveitar depois. Os comandos abaixo são o padrão que o GitHub gera (o token real fica só na tela dele, não é algo fixo que eu possa te dar aqui).

## 2. Instalar o runner

```bash
mkdir -p ~/actions-runner && cd ~/actions-runner

# versão exata que o GitHub mostrar na tela pode ser mais nova — usar a que aparecer lá
curl -o actions-runner-linux-x64.tar.gz -L \
  https://github.com/actions/runner/releases/download/v2.335.1/actions-runner-linux-x64-2.335.1.tar.gz

tar xzf ./actions-runner-linux-x64.tar.gz

./config.sh --url https://github.com/mtsdrf/pegaticket-saas --token <TOKEN_DA_TELA>
```

Durante o `config.sh`, aceitar os valores padrão é suficiente (nome do runner, labels, pasta de trabalho) — não precisa customizar nada pra este uso.

## 3. Instalar como serviço (systemd) — fica rodando sempre, sobrevive a reboot

```bash
sudo ./svc.sh install
sudo ./svc.sh start
sudo ./svc.sh status
```

`status` deve mostrar o serviço `active (running)`. Esse script já vem dentro do pacote do runner (`svc.sh`), cria e gerencia a unit do systemd sozinho — não precisa escrever unit file na mão.

## 4. Confirmar dependências na máquina do runner

O job builda PHP 8.5 (`composer install`) e Node 22 (`npm ci`/`npm run build`) e usa `rsync`/`ssh` pro deploy.

⚠️ **PHP precisa já estar instalado na máquina do runner, na versão certa** (o workflow só roda `php -v` pra confirmar, não instala nada). Diferente de runner hospedado pelo GitHub, `shivammathur/setup-php` em runner self-hosted não-Ubuntu cai num fallback que tenta instalar/gerenciar via `sudo` — e como o serviço do runner roda via systemd, sem TTY/sessão interativa, esse `sudo` nunca consegue autenticar (trava pedindo senha ou digital indefinidamente). Por isso o workflow não usa essa action aqui: confirmar manualmente antes do primeiro deploy —
```bash
php -v   # confirmar que é a versão exigida pelo composer.json de api/ (composer.json > require > php)
composer --version
php -m   # conferir que as extensões que api/composer.json pedir estão presentes (mbstring, xml, curl, pdo_mysql, gd, zip, intl etc.)
```
Se faltar algo, instalar direto pelo gerenciador de pacotes da distro (ex. `dnf install php php-cli php-mbstring ...` no Fedora), numa sessão interativa normal — não pelo runner.

`actions/setup-node` continua sendo usado normalmente (Node não tem essa limitação de sudo no self-hosted). Confirmar também que `rsync` existe na máquina:
```bash
which rsync || sudo apt install -y rsync   # Debian/Ubuntu; ajustar pro seu gerenciador de pacotes (dnf/pacman/etc.)
```

## 5. Confirmar no GitHub

Repositório → **Settings** → **Actions** → **Runners** — deve aparecer o runner novo com status verde ("Idle").

## 6. Disparar o deploy

Actions → **Deploy para produção** → **Run workflow** (ou dar um push em `main`). Agora ele roda na máquina com o IP que a Hostinger já aceita — o passo de `ssh-keyscan` deve passar limpo.

## Manutenção

- **Atualizar o runner**: o GitHub avisa na aba Runners quando sai versão nova — não é urgente, mas evitar deixar ficar muito desatualizado.
- **Parar/reiniciar**: `sudo ./svc.sh stop` / `sudo ./svc.sh start` dentro de `~/actions-runner`.
- **Remover** (se um dia trocar de máquina): `sudo ./svc.sh uninstall` e depois `./config.sh remove --token <novo token de remoção da tela de Runners>`.
- **Segurança**: como o runner roda com a chave SSH de deploy carregada durante o job, qualquer coisa que rode nesse job (inclusive de um push malicioso, se algum dia o repo aceitar PRs externos) tem acesso a essa chave — aceitável aqui porque só você tem permissão de push em `main`, mas vale lembrar se o repositório mudar de modelo de colaboração no futuro.
