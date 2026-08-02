<div align="center">

<img src="web/public/logo.png" alt="PegaTicket" width="220" />

<br/>
<br/>

<strong>PegaTicket</strong><br/>
<sub>Plataforma SaaS de venda e gestão de ingressos.</sub>

<br/>
<br/>

[![Produto](https://img.shields.io/badge/Produto-ticketing%20multiempresa-0F3D5E?style=for-the-badge)](web/)
[![Checkout](https://img.shields.io/badge/Checkout-eventos%20e%20ingressos-1B4965?style=for-the-badge)](#modulos-atuais)
[![Operação](https://img.shields.io/badge/Operação-vendas%20%C2%B7%20tickets%20%C2%B7%20acesso-CF7A00?style=for-the-badge)](#modulos-atuais)
[![Status](https://img.shields.io/badge/Status-beta%20controlada-15803D?style=for-the-badge)](#estado-atual)

[![Backend](https://img.shields.io/badge/Backend-Laravel%2013-8B1E3F?style=for-the-badge&logo=laravel&logoColor=white)](api/)
[![Frontend](https://img.shields.io/badge/Frontend-React%2019-0E7490?style=for-the-badge&logo=react&logoColor=white)](web/)
[![PHP](https://img.shields.io/badge/PHP-8.3+-4C51BF?style=for-the-badge&logo=php&logoColor=white)](api/composer.json)
[![Vite](https://img.shields.io/badge/Vite-8-0F766E?style=for-the-badge&logo=vite&logoColor=white)](web/package.json)
[![Licença](https://img.shields.io/badge/Licen%C3%A7a-Propriet%C3%A1ria-475569?style=for-the-badge)](#licenca)

</div>

## Sobre

**PegaTicket** é uma plataforma SaaS multiempresa para **criação, venda, emissão e operação de ingressos**.

O foco atual do produto é cobrir o núcleo operacional de ticketing:

- cadastro de organizadores e equipes;
- criação de eventos, sessões, lotes e tipos de ingresso;
- catálogo público de eventos;
- carrinho, hold de inventário e checkout;
- pagamento, confirmação de venda e rastreio público;
- emissão de ingressos digitais;
- portal do comprador;
- check-in e operação de acesso;
- analytics, reconciliação e administração global.

Este repositório **não deve mais ser lido como um SaaS genérico de comércio/delivery**. Há histórico técnico legado de outras frentes reaproveitadas, mas o produto ativo hoje é PegaTicket.

## Estado Atual

Diagnóstico consolidado em **2 de agosto de 2026**:

- backend com base funcional madura para multiempresa, eventos, vendas, tickets e portal;
- frontend com operação autenticada, loja pública, checkout, tickets, analytics e administração;
- pagamentos reais via Mercado Pago;
- suíte backend verde e base E2E web já implantada;
- produto em **beta controlada**, ainda em fechamento do núcleo Must Have da plataforma de ticketing.

O mapeamento mais recente do estado do produto está em:

- [docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md](/home/mtsdrf/workspace/pegaticket-saas/docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md)
- [docs/product-current-map.md](/home/mtsdrf/workspace/pegaticket-saas/docs/product-current-map.md)

## Sumário

- [Arquitetura](#arquitetura)
- [Stack](#stack)
- [Módulos Atuais](#modulos-atuais)
- [Como rodar localmente](#como-rodar-localmente)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Multiempresa e permissões](#multiempresa-e-permissoes)
- [Pagamentos](#pagamentos)
- [Segurança e LGPD](#seguranca-e-lgpd)
- [Deploy](#deploy)
- [Documentação interna](#documentacao-interna)
- [Licença](#licenca)

## Arquitetura

Monorepo com dois projetos ativos:

```text
pegaticket-saas/
├── api/   Backend Laravel 13 — API REST versionada, regras de negócio e integrações
└── web/   Frontend React 19 — operação autenticada, storefront público e portal do comprador
```

Observações importantes:

- `web/` concentra tanto a área autenticada quanto as rotas públicas de evento, checkout e portal.
- `site/` existe no repositório, mas **não é o foco operacional atual do produto**.
- o contexto oficial do domínio é **ticketing**, não comércio genérico.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13 · PHP 8.3+ · MySQL/MariaDB |
| Autenticação | JWT (`php-open-source-saver/jwt-auth`) |
| Documentação de API | L5-Swagger |
| Frontend | React 19 · TypeScript · Vite · MUI · react-router-dom v7 · axios |
| Pagamentos | Mercado Pago |
| Armazenamento de mídia | Cloudflare R2 + storage público/privado híbrido |
| Infra atual | Hostinger · GitHub Actions · rsync/SSH |

## Módulos Atuais

Hoje o produto já possui base funcional para:

- **Multiempresa**: tenants, usuários da organização, papéis, permissões e contexto ativo.
- **Onboarding**: cadastro self-service de organização com aceite legal.
- **Eventos**: categorias, venues, assentos, sessões, lotes, tipos de ingresso e adicionais simples.
- **Storefront**: catálogo público de eventos, detalhes, favoritos, carrinho, hold e checkout.
- **Vendas**: vendas manuais, vendas online, parcelas, cancelamento e refund estruturado.
- **Tickets**: emissão, listagem, QR/token e ciclo básico de check-in.
- **Portal do comprador**: login OTP, perfil, favoritos, vouchers e minhas vendas.
- **Analytics**: visão geral, produtos/adicionais, locais, sazonalidade, clientes e atrasos.
- **Financeiro base**: reconciliação e gestão de pendências de pagamento.
- **Assinatura SaaS**: planos, invoices, cobrança recorrente e telas administrativas.
- **Administração global**: usuários, grupos, planos, funcionalidades, tenants e auditoria.
- **Privacidade e suporte**: documentos legais, solicitações de privacidade e tickets de ajuda.

## Como Rodar Localmente

### Backend

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
composer dev
```

Testes:

```bash
cd api
composer test
```

### Frontend

```bash
cd web
npm install
cp .env.example .env
npm run dev
```

Build, lint e E2E:

```bash
cd web
npm run build
npm run lint
npm run test:e2e
```

## Estrutura de Pastas

No backend, o fluxo principal segue:

```text
Http/Requests    -> validação de entrada
Http/Controllers -> orquestração fina
Services         -> regra de negócio e transações
Repositories     -> persistência
Http/Resources   -> saída de API
DTOs             -> payload tipado de entrada
Events/Listeners -> auditoria e efeitos assíncronos
Models           -> entidades de domínio
```

No frontend:

```text
pages/       -> telas e rotas
components/  -> blocos reutilizáveis de interface
services/    -> clientes HTTP e orquestração de dados
types/       -> contratos do frontend
hooks/       -> estado local e integração com serviços
layouts/     -> shells autenticado e público
```

## Multiempresa e Permissões

- o backend resolve a organização ativa por middleware de tenant;
- usuários podem participar de múltiplas organizações;
- permissões combinam funcionalidade, ação e contexto do usuário;
- o gate atual combina plano contratado e permissão operacional.

## Pagamentos

Integração principal atual:

- **Mercado Pago** para Pix e fluxos de pagamento online;
- webhooks assinados;
- reconciliação e tratamento de inconsistências;
- idempotência nas operações sensíveis.

O contrato `/v1/orders` citado em partes do projeto é do **provedor externo**, não do domínio interno do PegaTicket.

## Segurança e LGPD

- segredos não são expostos em logs ou respostas;
- isolamento de tenant é validado nas leituras e escritas;
- dados sensíveis usam hash ou criptografia conforme o caso;
- aceite legal é versionado;
- o produto já possui trilha operacional mínima para privacidade e auditoria.

## Deploy

O deploy atual é feito por GitHub Actions com publicação via SSH/rsync.

O ambiente ativo usa:

- `web/` publicado no subdomínio do frontend;
- `api/` publicado no subdomínio da API;
- segredos injetados por GitHub Secrets;
- smoke tests pós-deploy no pipeline.

## Documentação Interna

Os documentos mais importantes para continuidade do produto são:

- [pegaticket_especificacao_completa.md](/home/mtsdrf/workspace/pegaticket-saas/pegaticket_especificacao_completa.md)
- [docs/product-current-map.md](/home/mtsdrf/workspace/pegaticket-saas/docs/product-current-map.md)
- [docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md](/home/mtsdrf/workspace/pegaticket-saas/docs/roadmap/2026-08-02-pegaticket-global-gap-roadmap.md)

As memórias em `.claude/memory/` ainda incluem histórico técnico de fases anteriores. Elas devem ser lidas com contexto: nem tudo ali representa o produto ativo atual.

## Licenca

Software proprietário. Todos os direitos reservados © PegaTicket.
