<div align="center">

<img src="web/public/logo.png" alt="PegaTicket" width="220" />

<br/>
<br/>

<strong>PegaTicket</strong><br/>
<sub>Gestão clara para empresas em movimento.</sub>

<br/>
<br/>

[![App](https://img.shields.io/badge/App-SaaS%20multiempresa-0F3D5E?style=for-the-badge)](web/)
[![Experiência](https://img.shields.io/badge/Experiência-mobile--first-1B4965?style=for-the-badge)](#sobre)
[![Plano](https://img.shields.io/badge/Plano-%C3%BAnico%20completo-2C7A7B?style=for-the-badge)](#funcionalidades)
[![Operação](https://img.shields.io/badge/Operação-Loja%20online%20%C2%B7%20Pedidos%20%C2%B7%20Estoque-CF7A00?style=for-the-badge)](#funcionalidades)

[![Backend](https://img.shields.io/badge/Backend-Laravel%2013-8B1E3F?style=for-the-badge&logo=laravel&logoColor=white)](api/)
[![Frontend](https://img.shields.io/badge/Frontend-React%2019-0E7490?style=for-the-badge&logo=react&logoColor=white)](web/)
[![PHP](https://img.shields.io/badge/PHP-8.3+-4C51BF?style=for-the-badge&logo=php&logoColor=white)](api/composer.json)
[![Vite](https://img.shields.io/badge/Vite-8-0F766E?style=for-the-badge&logo=vite&logoColor=white)](web/package.json)
[![Status](https://img.shields.io/badge/Status-beta%20controlada-15803D?style=for-the-badge)](#deploy)
[![Licença](https://img.shields.io/badge/Licença-Proprietária-475569?style=for-the-badge)](#licença)

</div>

<br/>

## Sobre

**PegaTicket** é uma plataforma de gestão comercial multiempresa pensada para operação real: pedidos, clientes, produtos, estoque, financeiro, loja online e visão analítica, tudo numa experiência clara, direta e mobile-first.

Cada empresa opera isolada dentro do SaaS, com:

- plano próprio
- permissões próprias
- módulos habilitados conforme a contratação
- contexto operacional separado por empresa

O foco do produto é servir desde negócios que precisam apenas vender online até operações mais completas, com atendimento interno, fluxo de caixa, contador externo, trilha fiscal e operação offline controlada.

> Gestão clara para empresas em movimento.

## Sumário

- [Arquitetura](#arquitetura)
- [Stack](#stack)
- [Funcionalidades](#funcionalidades)
- [Como rodar localmente](#como-rodar-localmente)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Multi-tenancy e permissões](#multi-tenancy-e-permissões)
- [Pagamentos](#pagamentos)
- [Segurança e LGPD](#segurança-e-lgpd)
- [Deploy](#deploy)
- [Documentação interna](#documentação-interna)
- [Licença](#licença)

## Arquitetura

Monorepo com projetos irmãos na raiz — cada um é uma aplicação própria, com seu próprio `package.json`/`composer.json`, publicada em um domínio/subdomínio separado da mesma origem:

```
pegaticket-saas/
├── api/     Backend Laravel 13 — API REST versionada (v1), fonte única de verdade
├── web/     App autenticado (React 19 + Vite) — sistema.pegaticket.com
├── site/    Landing institucional pública, sem autenticação — site.pegaticket.com
└── app/     App mobile/nativo — reservado para o futuro, ainda não iniciado
```

O **Portal do cliente final** e a **Loja online** vivem dentro de `web/`, sob rotas próprias. A estratégia atual privilegia um ecossistema enxuto, com menos superfícies independentes e mais reaproveitamento entre operação autenticada, storefront e portal.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13 · PHP 8.3+ · MySQL/MariaDB |
| Autenticação | JWT (`php-open-source-saver/jwt-auth`) |
| Documentação de API | L5-Swagger (En/PT-BR) |
| Frontend (`web/`) | React 19 · TypeScript · Vite · MUI · react-router-dom v7 · axios |
| Landing (`site/`) | React 19 · Vite (build multi-página) |
| Pagamentos | Mercado Pago (Orders API + Preapproval) |
| Infra | Hospedagem compartilhada (Hostinger) · deploy via GitHub Actions + rsync/SSH |

## Funcionalidades

- **Operação comercial** — pedidos internos, pedidos da loja, clientes, produtos, categorias, estoque, expedição e histórico completo.
- **Loja online** — catálogo público, checkout, acompanhamento do pedido, cashback, favoritos e portal do cliente final.
- **Financeiro e analytics** — conciliação, relatórios, indicadores, recebíveis e visão gerencial por canal, cliente, produto e local.
- **Assinatura e planos** — contratação, troca de plano, histórico de cobrança, Pix para faturas elegíveis, cancelamento, renovação e regras de consumidor.
- **Multiempresa** — isolamento por empresa, plano único `PegaTicket`, permissões e overrides por funcionalidade.
- **Contador externo** — acesso dedicado com TOTP, empresas aprovadas, relatórios, dados fiscais e canal de pendências.
- **Integrações** — Mercado Pago, webhooks assinados, chaves de API, operação de marketplace e base pronta para novas integrações.
- **Auditoria** — registro de mutações relevantes com trilha clara de responsável, horário e contexto.

## Como rodar localmente

### Backend (`api/`)

```bash
cd api
composer setup          # install, .env, key:generate, migrate, npm install/build
php artisan jwt:secret   # gera o segredo JWT (não é criado pelo key:generate)
composer dev             # serve + queue:listen + pail + vite, tudo junto
composer test
```

### Frontend (`web/`)

```bash
cd web
npm install
cp .env.example .env     # VITE_API_BASE_URL=http://localhost:8000/api/v1
npm run dev
npm run build             # tsc -b && vite build
npm run lint               # oxlint
```

### Landing institucional (`site/`)

```bash
cd site
npm install
npm run dev
```

## Estrutura de pastas

Backend segue um fluxo estrito por feature, sem pular camada:

```
Http/Requests        → validação de entrada
Http/Controllers     → fino, só orquestra
Services             → regra de negócio, transações, eventos
Repositories         → persistência (Contracts + Eloquent)
Http/Resources        → formato de saída
DTOs                  → entrada mutável (fromArray a partir do request validado)
Events/Listeners      → toda mutação relevante audita via Event → Listener
```

Todo Model de domínio estende `BaseModel` (UUID público + PK interna, soft delete, `created_by`/`updated_by`/`deleted_by` automáticos).

## Multi-tenancy e permissões

- Middleware `tenant` resolve a empresa ativa a partir do JWT e popula os helpers globais `tenant()`/`tenant_id()`.
- Permissão sempre via **Grupo** (usuário → Grupo → Permissão de Grupo → Funcionalidade + Ação) para staff interno, e via **Perfil do tenant** (usuário → Perfil → Permissão do Perfil) para usuários da empresa cliente.
- Gate em duas camadas: **plano** (o que a empresa contratou libera) e **perfil** (o que aquele usuário específico pode fazer dentro do que o plano permite) — com override individual por empresa para liberação ou bloqueio pontual.

## Pagamentos

Integração real com **Mercado Pago**:

- **Pedido/fatura avulsa** — API de Orders (`/v1/orders`), Pix com QR code + copia-e-cola, cartão tokenizado no navegador (PCI-safe, PAN/CVV nunca chegam ao backend).
- **Assinatura recorrente** — Preapproval, cobrança automática por ciclo, período de graça de 7 dias em caso de falha, suspensão de acesso após o prazo.
- **Webhooks** — assinatura HMAC-SHA256 validada antes de qualquer escrita, idempotência por tipo de evento, nunca confia no payload sem reconsultar a API oficial.

## Segurança e LGPD

- Nenhum segredo (token, chave de webhook) é logado ou exposto em resposta de API.
- Segredo irreversível → hash; segredo reversível (necessário ler de volta) → `encrypted` cast, nunca texto puro em banco.
- Isolamento de tenant validado em toda escrita/leitura de recurso (`assertBelongsToCurrentTenant`).
- Auditoria com denylist de campos sensíveis — dado de pagamento/senha nunca entra no log de auditoria em texto puro.

## Deploy

CI/CD via GitHub Actions: build de `api/`, `web/` e `site/`, publicação via `rsync`/SSH em hospedagem compartilhada. Segredos de produção (`MERCADOPAGO_*`, `FRONTEND_URL`, etc.) são aplicados automaticamente a cada deploy a partir dos *Secrets* do repositório — nenhum valor sensível trafega em texto puro no versionamento.

Hoje o projeto está operando em **beta controlada**, com:

- suíte backend consolidada
- suíte E2E web cobrindo fluxos críticos
- smoke pós-deploy público e autenticado

## Documentação interna

Decisões de arquitetura, padrões de código, regras de banco e histórico de aprendizado técnico vivem em `.claude/memory/` — consultada antes de qualquer mudança relevante no projeto.

## Licença

Software proprietário. Todos os direitos reservados © PegaTicket.
