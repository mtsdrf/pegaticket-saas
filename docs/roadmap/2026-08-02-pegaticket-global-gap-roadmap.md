# PegaTicket — Mapeamento Global Atual e Roadmap de Desenvolvimento

Data de referência: **2 de agosto de 2026**

> **Nota de revisão — 3 de agosto de 2026:** documento revisado em sessão Claude Code, cruzando o texto abaixo com o código real (`api/app`, rotas ativas, suíte de testes). Vários itens listados como pendentes/parciais já estavam entregues e foram movidos para as seções de "entregue"; números de rotas e testes atualizados. Ver detalhes nas seções 3, 4 e 6.

> **Nota de revisão — 5 de agosto de 2026:** nova sessão longa de desenvolvimento fechou a **Fase 5 (financeiro)**, a **Fase 6 (growth)** e a **Fase 7 (risco/alta demanda)** por completo, além de entregas pontuais em locais/mapa, inventário e comunicação. Cada item abaixo foi conferido lendo o código real (`api/app/Services/Finance`, `Affiliate`, `VirtualQueueService`, `AntiBotGuardService`, `RiskEngineService`, `EventGate`, `TicketTypeWaitlistEntry`, `CommunicationLog`/`CommunicationDispatcherService`, `SeatMapViewer.tsx`), não apenas o resumo de sessão anterior — antes de marcar qualquer item como entregue. Rotas e testes recontados na hora (`php artisan route:list` e `composer test` em `api/`), não estimados. Resumo:
>
> - **Fase 5 (financeiro) fechada**: settlements, recebíveis/ledger e split+custódia PagBank consolidados em ~13 services em `api/app/Services/Finance/*` (`SettlementGenerationService`, `SettlementReleaseService`, `RiskReserveReleaseService` e demais).
> - **Fase 6 (growth) fechada**: afiliados/comissão (`Affiliate`/`AffiliateCommission`), UTM no `web/` e agora também no `site/` (`site/src/utils/marketingTracking.ts`, `web/src/utils/appMarketingTracking.ts`), pixels Meta/GA4 (`web/src/utils/marketingPixels.ts`), CRM/segmentação (`FinalCustomerTenantLinkRepository::crmSummaryForTenant`), recompra automática (`SendRecompraNudgeMailsCommand`).
> - **Fase 7 (risco/alta demanda) fechada**: fila virtual opt-in (`VirtualQueueService`), antibot (`AntiBotGuardService`), motor de antifraude com 6 heurísticas (`RiskEngineService`), observabilidade estendida (`OperationSnapshotService`).
> - **Portarias formais (`EventGate`)** integradas ao `CheckinService` — fecha parte da lacuna de portaria/capacidade citada em locais/mapa.
> - **Lista de espera de ingresso esgotado** (`TicketTypeWaitlistEntry`) — cadastro público + notificação automática quando volta a ter vaga.
> - **Hub de comunicação com tracking unificado** (`CommunicationLog`/`CommunicationDispatcherService`) — os 7 Mailables transacionais agora logam sucesso/falha; **não** inclui templates configuráveis nem fallback de provedor (continua fora de escopo, ver 4.2/4.3).
> - **Mapa interativo da loja pública** (`web/src/components/storefront/SeatMapViewer.tsx`) — SVG com zoom/pan/pinça, clique por assento, tokens de tema. Substitui a versão anterior em DOM absoluto. **Pendência honesta, não marcar como 100% testado**: pinch-zoom multitoque real em dispositivo físico e o fluxo ponta-a-ponta com evento real publicado não foram validados manualmente, só via preview mockado; não há teste e2e cobrindo o componente (`web/e2e/` não referencia `SeatMapViewer`).
> - **Correções de bug/segurança de auditoria**: bug crítico de checkout (antibot bloqueando criação automática de hold, corrigido em `StorefrontCheckoutPage.tsx`, commit `9d7ed0d`), 2 causas-raiz de CI (fixture e2e com data hardcoded expirada; `APP_LOCALE` não fixado em `phpunit.xml`, caindo no default `en` do Laravel quando o `.env` do CI está vazio) e achados de auditoria de risco/checkout (IP do comprador passado só via `$request->ip()` no controller, nunca do body, commit `cee4afa`).
> - **Contagens reconferidas nesta revisão**: **282 rotas** ativas em `api/v1` (era 260) e **720 testes passando (2638 assertions)** em `composer test` (era 612/2155).
> - Itens que **continuam pendentes** (confirmados ainda ausentes no código): wallet pass (sem credenciais Apple/Google), cotas de inventário por canal, payout real de comissão de afiliado, revenda oficial verificada, multi-gateway/roteamento inteligente de pagamento — decisão de produto pendente; NFe/ERP fiscal real, CAPTCHA de vendor real, rate limiting adaptativo, templates de e-mail configuráveis/fallback de provedor — fora de escopo declarado; Fase 8 não iniciada.

> **Nota de revisão — 5 de agosto de 2026 (fechamento do dia):** continuação da mesma sessão longa de desenvolvimento, na mesma data da nota acima — mais uma rodada de entregas foi commitada (`255820a Fechamento`) depois da nota anterior. Todos os itens abaixo foram conferidos lendo o código real (models, controllers, rotas, testes), não o resumo de sessão, e as contagens foram re-rodadas de verdade (`php artisan route:list` e `composer test` em `api/`, não estimadas). Também há decisões de produto do usuário que fecham de vez alguns itens que antes apareciam como "pendente/decisão em aberto":
>
> - **Decisões de produto confirmadas pelo usuário (fecham a discussão, não são mais lacunas técnicas em aberto)**:
>   - **Wallet pass (Apple/Google) — decidido NÃO fazer.** Sai da lista de "pendente adiado" e passa a "decidido não fazer". Não é falta de credencial que algum dia será resolvida; é escopo que não entra no produto.
>   - **Payout automático de comissão de afiliado — decidido NÃO fazer.** O repasse continua manual/staff (já era o comportamento entregue na Fase 6); a diferença é que agora está confirmado que isso é definitivo, não um "MVP incompleto" esperando uma Fase futura de automação.
>   - **NFe/ERP fiscal real — decidido NÃO fazer.** Deixa de ser tratado como "fora de escopo declarado" (que soa temporário) e passa a "decisão de produto definitiva": PegaTicket não vai emitir documento fiscal nem integrar ERP.
>   - **Multi-gateway de pagamento — decidido NÃO fazer.** A plataforma opera **somente com PagBank**, por decisão explícita do usuário. A expectativa de "múltiplos gateways com roteamento inteligente" sai do roadmap como lacuna técnica; onde o documento mencionava isso como algo a construir, passa a registrar que é decisão de produto (não ausência de trabalho).
> - **Cotas de inventário por canal — entregue**: `App\Models\Event\TicketTypeChannelQuota` (`api/app/Models/Event/TicketTypeChannelQuota.php`), com DTOs, Service, Repository, Controller/rotas (`tenant-events/{event}/ticket-types/{ticketType}/channel-quotas`, ver `routes/api.php`) e Events/Listeners de auditoria completos. Canal identificado pela origem da venda (storefront/staff/afiliado). Testes em `tests/Feature/Event/TicketTypeChannelQuotaTest.php`, passando.
> - **Revenda oficial verificada — entregue, com limitação conhecida**: `App\Models\Ticket\TicketResaleListing` (`api/app/Models/Ticket/TicketResaleListing.php`), fluxo completo de anúncio/compra no Portal (`Http/Controllers/Portal/TicketResaleController`), reaproveitando `TicketService::transfer()` para transferência de titularidade + rotação de QR (confirmado no docblock de `TicketResaleService`: "NÃO reimplementa transferência/rotação de QR — sempre delega pra `TicketService::transfer()`"). **Repasse ao vendedor NÃO é automático**: fica em `seller_payout_status=pendente_liberacao` até o staff do tenant liberar manualmente via `POST /resale-listings/{listing}/release-payout` (`TicketResalePayoutController::release`) — decisão documentada no próprio código porque não existe hoje nenhum rail financeiro (`LedgerEntry`/`Settlement`) que faça split automático para pessoa física. Isso é uma **limitação conhecida**, não "100% completo" — registrado assim neste documento.
> - **Turnstile (Cloudflare) + rate limiting adaptativo — entregue, DESABILITADO por decisão do usuário (2026-08-05)**: `App\Services\Security\TurnstileVerificationService` e `App\Http\Middleware\AdaptiveThrottleMiddleware` (`adaptive.throttle` em `bootstrap/app.php`) integrados nos formulários públicos sensíveis: hold de checkout (`storefront-holds-create`), resgate de convite de guest list (`guest-invite-redeem`) e cadastro em lista de espera (`storefront-ticket-waitlist-create`) — todos com `throttle:...` + `adaptive.throttle:3,60` no `routes/api.php`. Sem `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`/`VITE_TURNSTILE_SITE_KEY`, o serviço fica em modo desabilitado (`TurnstileVerificationService::isEnabled()` retorna `false`, `verify()` sempre aprova) — não quebra nada. **O usuário confirmou explicitamente que quer deixar assim por enquanto** (não é mais "aguardando credencial", é decisão consciente de não ativar agora); antibot básico (honeypot + tempo mínimo) e antifraude continuam ativos normalmente.
> - **Templates de e-mail configuráveis + fallback de provedor — entregue; fallback DESATIVADO por decisão do usuário (2026-08-05)**: `App\Models\EmailTemplate` permite customizar assunto/corpo por tenant/tipo de e-mail, com fallback silencioso pro texto hardcoded padrão quando não customizado (`EmailTemplateResolverService`, usado pelos 7 Mailables transacionais) — essa parte (templates) já é totalmente funcional, sem depender de nada externo. Fallback de mailer via `MAIL_MAILER_FALLBACK` também implementado, mas **o usuário confirmou que quer manter só um único provedor de e-mail** — sem `MAIL_MAILER_FALLBACK` configurado, o sistema usa exclusivamente o mailer padrão, por decisão, não por lacuna.
> - **Mapa interativo — validação real concluída** (deixa de ser pendência): a nota anterior deste mesmo dia registrava que a validação manual/pinch-zoom ainda não tinha sido feita. Nesta rodada, o fluxo foi validado ponta-a-ponta com dado real no banco (tenant/evento/mapa de assentos criado e depois limpo integralmente, confirmado sem resíduo), **incluindo pinch-zoom multitoque real simulado via Chrome DevTools Protocol** — contrariando a expectativa anterior de que não daria pra simular multitouch; deu certo e o zoom por pinça funcionou. **Uma ressalva real permanece e continua registrada**: desselecionar um assento por segundo toque não funcionou no teste automatizado mobile (funcionou normalmente no desktop) — recomendado teste manual num celular físico antes de considerar o componente 100% livre de bugs de touch. Não tratar como "100% perfeito sem ressalvas" — é "validado com uma ressalva conhecida de touch mobile".
> - **Achado operacional (fora do código, registrado por transparência)**: durante a validação do item acima, foi descoberto que uma tentativa anterior nesta mesma sessão/dia já tinha escrito dados de teste (tenant/venue/evento/clientes fictícios) diretamente no banco de produção compartilhado (não há `.env.testing` neste projeto — decisão já registrada anteriormente) sem limpar depois. Foi encontrado e limpo nesta rodada, com verificação de que não sobrou resíduo. Não é um item de roadmap de produto; fica registrado aqui só como nota de rastreabilidade operacional.
> - **Contagens re-rodadas ao final do dia**: **298 rotas** ativas em `api/v1` (era 282 na nota anterior deste mesmo dia) e **753 testes passando (2789 assertions)** em `composer test` (era 720/2638) — o aumento reflete as entregas de cotas por canal, revenda, Turnstile/throttle adaptativo e templates de e-mail desta rodada.

Documento-base analisado: [pegaticket_especificacao_completa.md](/home/mtsdrf/workspace/pegaticket-saas/pegaticket_especificacao_completa.md)

## 1. Objetivo deste diagnóstico

Este documento consolida:

- o estado real do produto hoje no repositório;
- o delta entre o código atual e a especificação completa;
- o que já está pronto;
- o que está parcial;
- o que ainda não existe;
- o que precisa ser corrigido ou removido por desalinhamento de contexto;
- a ordem recomendada de desenvolvimento para as próximas fases.

Ele substitui qualquer leitura otimista baseada apenas em documentação antiga ou memória operacional desatualizada.

## 2. Metodologia usada

O mapeamento foi feito cruzando:

- a especificação completa com 55 seções;
- o inventário real de backend em `api/app`;
- o inventário real de páginas em `web/src/pages`;
- as rotas Laravel ativas em `php artisan route:list`;
- a suíte de testes atual.

Estado validado nesta leitura (atualizado 2026-08-05, fechamento do dia):

- backend com **298 rotas** ativas em `api/v1` (era 282 mais cedo em 2026-08-05, 260 em 2026-08-03, 249 antes disso);
- backend com **753 testes passando** (**2789 assertions**) em `composer test`, incluindo os testes novos de cotas por canal, revenda, Turnstile e rate limiting adaptativo — era 720/2638 mais cedo em 2026-08-05, 612/2155 em 2026-08-03;
- frontend com páginas reais para administração, eventos, checkout, portal, tickets, analytics, reconciliação, suporte, operação, financeiro tenant-scoped e crescimento/afiliados;
- suíte E2E web já instalada e funcional, mas **sem cobertura automatizada ainda** do novo mapa interativo de assentos (`SeatMapViewer.tsx`) — validado manualmente nesta sessão (ver nota de revisão 2026-08-05, fechamento do dia), inclusive pinch-zoom real via CDP, mas não há teste e2e persistido cobrindo o componente.

## 3. Estado real do produto hoje

### 3.1 Núcleo já existente e utilizável

Os seguintes blocos já possuem implementação concreta no backend e/ou frontend:

- **Multiempresa básico**: tenant, tenant user, tenant role, overrides de feature, troca de contexto e isolamento por organização.
- **Autenticação base**: login por senha, recuperação de senha, sessão autenticada, portal do cliente com OTP.
- **RBAC base**: grupos globais, papéis do tenant e permissões por funcionalidade/ação.
- **Onboarding base**: cadastro self-service da empresa, aceite legal, ativação inicial.
- **Jurídico/LGPD operacional básico**: documentos legais, aceite versionado, exportação e solicitações de privacidade.
- **Eventos**: categorias, eventos, sessões, tipos de ingresso, lotes, adicionais simples (`event_products`), venues e assentos.
- **Storefront público**: catálogo de eventos, detalhe do evento, favoritos, carrinho, hold de inventário, checkout e rastreio público.
- **Alocação de assentos** *(entregue — atualizado 2026-08-03)*: melhor assento disponível e assentos contíguos automáticos na loja pública via `SeatAllocationService` (`api/app/Services/Storefront/SeatAllocationService.php`), respeitando contiguidade de fileira e expostos por setor+quantidade sem exigir escolha assento a assento; acessibilidade por assento (`is_accessible`) já indicada/filtrável no mapa da loja; bloqueio administrativo de assento via CRUD de `Seat` (`status: bloqueado/indisponivel`), inclusive edição em lote no editor de mapa.
- **Vendas**: criação manual, vendas da loja, parcelas, cancelamento, refund manual estruturado, timeline de workflow.
- **Promoções básicas** *(entregue — atualizado 2026-08-03)*: cupom, incluindo cortesia via cupom com `type: percentage, value: 100`.
- **Pagamentos base** *(atualizado 2026-08-03)*: Mercado Pago, Pix e **cartão de crédito/débito via PagBank** (tokenização `card.encrypted`, `createCardCharge()`, `CREDIT_CARD`/`DEBIT_CARD`, parcelamento via `installments`, validação de titular/CPF-CNPJ — interface própria, sem checkout transparente de terceiro, decisão confirmada com o usuário), webhooks, reconciliação e camada de payment issues.
- **Tickets**: emissão, listagem, QR/token, **transferência de titularidade** (`TicketService::transfer()`, rotaciona code/qr_token, endpoint no Portal do comprador), check-in básico e histórico de check-in.
- **Guest list / cortesias estruturadas** *(entregue — atualizado 2026-08-03)*: domínio `GuestList`/`GuestListEntry` (`api/app/Models/GuestList/`), com link individual de autoatendimento por convidado (rota pública `/convite-ingresso/:token`), reaproveitando o pipeline normal de Sale/Ticket.
- **Portal do comprador**: login OTP, perfil, favoritos, vouchers, minhas vendas.
- **Caixa / bilheteria presencial** *(entregue — atualizado 2026-08-03)*: domínio `CashSession` (`api/app/Models/CashSession/`), abertura/fechamento de caixa com reconciliação contra vendas em dinheiro; comprovante de venda imprimível.
- **Check-in** *(atualizado 2026-08-03)*: validação manual, leitura de QR, histórico, modo offline com fila local de sincronização, e sincronização entre dispositivos via polling compartilhado de 15s no resumo operacional (`checkinOperationalSummary`) — suficiente para o nível atual de operação, sem necessidade de WebSocket; dashboard operacional em tempo quase real.
- **Inventário — virada de lote** *(entregue — atualizado 2026-08-03)*: virada automática de lote (`auto_advance`) implementada e testada em `StorefrontHoldService`.
- **Analytics inicial**: overview, produtos/adicionais, locais, sazonalidade, clientes, atrasos.
- **Financeiro** *(entregue — atualizado 2026-08-05)*: Fase 5 fechada — settlements, recebíveis/ledger e split+custódia PagBank consolidados em ~13 services (`api/app/Services/Finance/*`: `SettlementGenerationService`, `SettlementReleaseService`, `RiskReserveReleaseService`, `ReceivableGenerationService`, `ReconciliationService`, `EventFinancialCloseoutService` e demais), painel financeiro tenant-scoped, superfície administrativa global e ajustes/exceções (`refund`/`chargeback`/`fraud review`) auditáveis.
- **Growth/afiliados/CRM/pixels** *(entregue — atualizado 2026-08-05)*: Fase 6 fechada — afiliados/comissão (`Affiliate`/`AffiliateCommission`), UTM capture no `web/` e no `site/`, pixels Meta/GA4 (`marketingPixels.ts`), CRM/segmentação (`crmSummaryForTenant`), recompra automática (`SendRecompraNudgeMailsCommand`). Falta apenas payout real de comissão (decisão de produto pendente).
- **Risco, antifraude, fila virtual e antibot** *(entregue — atualizado 2026-08-05)*: Fase 7 fechada — fila virtual opt-in (`VirtualQueueService`), antibot (`AntiBotGuardService`), motor de antifraude com 6 heurísticas (`RiskEngineService`), observabilidade estendida (`OperationSnapshotService`).
- **Portarias formais** *(entregue — atualizado 2026-08-05)*: `EventGate`, entidade opcional de controle de acesso por portão/tipo de ingresso, integrada ao `CheckinService`.
- **Lista de espera de ingresso esgotado** *(entregue — atualizado 2026-08-05)*: `TicketTypeWaitlistEntry`, cadastro público + notificação automática de vaga liberada.
- **Comunicação — tracking unificado + templates configuráveis** *(entregue — atualizado 2026-08-05, fechamento do dia)*: `CommunicationLog`/`CommunicationDispatcherService`, os 7 Mailables transacionais logados com status sucesso/falha; `EmailTemplate` já permite customizar assunto/corpo por tenant, e `MAIL_MAILER_FALLBACK` já existe — ambos aguardando credencial real de segundo provedor SMTP para ativar de fato (ver 3.1, item de templates de e-mail acima).
- **Mapa interativo da loja pública** *(entregue e validado — atualizado 2026-08-05, fechamento do dia)*: `SeatMapViewer.tsx`, SVG com zoom/pan/pinça, clique por assento, tokens de tema. Validado ponta-a-ponta com dado real no banco (criado e limpo) **incluindo pinch-zoom multitoque real via Chrome DevTools Protocol** — funcionou. Ressalva conhecida: desselecionar por segundo toque não funcionou no teste automatizado mobile (funcionou no desktop); recomendado teste manual em celular físico. Ainda não há teste e2e persistido cobrindo o componente.
- **Cotas de inventário por canal** *(entregue — 2026-08-05, fechamento do dia)*: `TicketTypeChannelQuota` (`api/app/Models/Event/TicketTypeChannelQuota.php`), canal identificado pela origem da venda (storefront/staff/afiliado), CRUD completo com auditoria e testes em `tests/Feature/Event/TicketTypeChannelQuotaTest.php`.
- **Revenda oficial verificada** *(entregue com limitação conhecida — 2026-08-05, fechamento do dia)*: `TicketResaleListing` (`api/app/Models/Ticket/TicketResaleListing.php`), anúncio/compra no Portal reaproveitando `TicketService::transfer()` para titularidade + rotação de QR. Repasse financeiro ao vendedor **não é automático** — fica pendente de liberação manual pelo staff do tenant (`release-payout`), por não existir mecanismo de repasse a pessoa física no domínio financeiro hoje.
- **Turnstile (Cloudflare) + rate limiting adaptativo** *(entregue, aguardando credencial real — 2026-08-05, fechamento do dia)*: `TurnstileVerificationService` + `AdaptiveThrottleMiddleware`, integrados em hold de checkout, resgate de convite e lista de espera. Sem `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`/`VITE_TURNSTILE_SITE_KEY` reais, fica em modo desabilitado (não quebra fluxo, mas proteção real não está ativa) — ação pendente do usuário, não gap de código.
- **Templates de e-mail configuráveis + fallback de provedor** *(entregue, aguardando credencial real — 2026-08-05, fechamento do dia)*: `EmailTemplate` permite customizar assunto/corpo por tenant/tipo com fallback pro texto padrão; `MAIL_MAILER_FALLBACK` implementado, mas precisa de credencial real de um segundo provedor SMTP pra ativar — ação pendente do usuário.
- **Administração global**: usuários, grupos, funcionalidades, planos, tenants, auditoria.
- **Assinatura SaaS**: planos, subscription, invoice, cobrança recorrente e telas administrativas/operacionais.
- **Suporte básico**: `help requests` e tela de tickets de suporte.

### 3.2 Blocos existentes mas parciais

Estão implementados parcialmente, porém ainda abaixo da especificação:

- **Identidade**: existe cadastro/login/recuperação, mas faltam passkeys, MFA robusto, gestão de dispositivos e KYC/KYB real.
- **Organizações e permissões**: existe base forte de RBAC, mas faltam aprovações em múltiplas etapas, segregação de funções madura e escopo fino por evento/portaria.
- **Locais e mapa** *(reduzido — atualizado 2026-08-05, fechamento do dia)*: venues, seats e map versions existem; melhor assento, assentos contíguos, acessibilidade por assento na loja pública, bloqueio administrativo, portarias formais (`EventGate`) e mapa interativo com zoom/pan/pinça (`SeatMapViewer.tsx`, ver 3.1) já foram entregues **e validados ponta-a-ponta** (incluindo pinch-zoom real via CDP). Ainda falta amadurecer supervisão operacional de capacidade/portaria em cenários de grande porte; e resta a ressalva de desselecionar por segundo toque no mobile (ver nota de revisão 2026-08-05).
- **Eventos**: CRUD existe, mas fluxo editorial, aprovação, publicação programada, adiamento e operação documental ainda estão incompletos.
- **Inventário** *(reduzido — atualizado 2026-08-05, fechamento do dia)*: ticket types, batches e holds existem, incluindo virada automática de lote, lista de espera de ingresso esgotado (`TicketTypeWaitlistEntry`) e **cotas de inventário por canal** (`TicketTypeChannelQuota`, ver 3.1) já entregues; ainda faltam combos e upgrade real.
- **Checkout**: existe fluxo público consistente, mas faltam guest checkout maduro, formulários por ingresso, split de pagamento, retentativas completas e jornada de alta demanda.
- **Pagamentos** *(reduzido — atualizado 2026-08-05)*: Pix e cartão (crédito/débito, tokenização, parcelamento) via PagBank, antifraude (`RiskEngineService`, 6 heurísticas) e split/custódia financeiro real (Fase 5, ver 3.1) já existem. **Múltiplos gateways não estão nem serão implementados — decisão de produto do usuário de operar somente com PagBank**, não uma lacuna técnica a fechar.
- **Vendas/Vendas**: o domínio base existe, mas exportações, documentos operacionais, importação e busca operacional avançada ainda estão incompletos.
- **Tickets pós-compra** *(reduzido — atualizado 2026-08-05, fechamento do dia)*: emissão, check-in, transferência de titularidade e **revenda oficial verificada** (`TicketResaleListing`, ver 3.1) já existem, com repasse ao vendedor pendente de liberação manual (limitação conhecida, não bug). **Wallet pass decidido não fazer** (decisão de produto, não mais "adiado por falta de credencial"); ainda falta QR rotativo avançado.
- **Promoções** *(reduzido — atualizado 2026-08-05, fechamento do dia)*: cupom, cortesia via cupom 100%, guest list e afiliados/comissão (Fase 6, ver 3.1) já existem; **payout automático de comissão de afiliado decidido não fazer** (fica manual/staff, definitivo); ainda faltam links promocionais avançados e campanhas condicionais.
- **Comunicação** *(reduzido — atualizado 2026-08-05, fechamento do dia)*: tracking unificado de envios e **templates configuráveis + fallback de provedor** já existem (ver 3.1); ambos aguardando credencial real (Turnstile já cobre a parte antibot dos formulários públicos) para ativação completa em produção — ação pendente do usuário, não gap de código.
- **Check-in** *(reduzido — atualizado 2026-08-05)*: base funcional entregue, com offline forte, sincronização entre dispositivos via polling de 15s e portarias formais (`EventGate`, ver 3.1); ainda faltam reentrada, controle por zona mais fino e supervisão operacional avançada.
- **Financeiro/Fiscal** *(reduzido — atualizado 2026-08-05, fechamento do dia)*: a **Fase 5 foi fechada** (ver 3.1) — recebíveis/ledger, settlements, split+custódia PagBank, fechamento financeiro por evento, borderô, painel tenant-scoped e superfície administrativa global já implementados. **NFe/ERP fiscal real decidido não fazer** (decisão de produto definitiva, não "fora de escopo" temporário); resta refinamento contínuo de reconciliação/exportação.
- **API e integrações**: API versionada existe, assim como webhooks internos de pagamento, mas falta a superfície pública madura para terceiros.

## 4. Delta contra a especificação

### 4.1 Cobertura forte hoje

Estas áreas da especificação já têm fundação pronta suficiente para continuar evoluindo sem recomeçar:

- Seções **4, 6, 8, 9, 11, 12, 13, 14, 16, 17, 18, 19, 20, 25, 27, 29, 30, 31, 36, 37, 42, 43 e 44** em nível básico a intermediário a maduro.

Resumo:

- multi-tenant;
- permissões;
- eventos;
- inventário base (incluindo virada automática de lote, lista de espera e **cotas por canal**);
- checkout base;
- pagamentos base (Pix e cartão via PagBank, com parcelamento e tokenização — único gateway, por decisão de produto);
- vendas/vendas;
- tickets (incluindo transferência de titularidade e **revenda oficial verificada**, com repasse manual);
- guest list / cortesias estruturadas;
- alocação de assentos (melhor assento, contíguos, acessibilidade na loja pública) e mapa interativo da loja pública (validado ponta-a-ponta, inclusive pinch-zoom real via CDP);
- caixa / bilheteria presencial (`CashSession`);
- check-in (incluindo offline, sincronização por polling entre dispositivos e portarias formais via `EventGate`);
- financeiro (recebíveis, settlements, split+custódia PagBank, fechamento por evento);
- growth (afiliados/comissão manual, UTM, pixels, CRM/segmentação, recompra automática);
- risco e alta demanda (fila virtual, antibot, motor de antifraude com 6 heurísticas, **Turnstile + rate limiting adaptativo** aguardando credencial);
- comunicação com tracking unificado de envios e **templates configuráveis + fallback de provedor** (aguardando credencial);
- suporte;
- analytics inicial;
- administração global;
- LGPD, segurança, CI/CD e testes iniciais.

*(atualizado 2026-08-05: Fases 5, 6 e 7 fechadas — financeiro/settlements/split-custódia, growth/afiliados/CRM/pixels e risco/fila virtual/antibot/antifraude confirmados como entregues e movidos para esta lista; também portarias formais (`EventGate`), lista de espera de ingresso e tracking unificado de comunicação. Ver nota de revisão 2026-08-05 no topo do documento.)*

*(atualizado 2026-08-05, fechamento do dia: mais uma rodada de entregas movidas pra esta lista — cotas de inventário por canal (`TicketTypeChannelQuota`), revenda oficial verificada (`TicketResaleListing`, repasse manual), Turnstile + rate limiting adaptativo (aguardando credencial real) e templates de e-mail configuráveis + fallback de provedor (aguardando credencial real). Mapa interativo passa de "entregue com ressalva de validação" para "validado ponta-a-ponta", mantendo só a ressalva de touch mobile. Ver nota de revisão 2026-08-05, fechamento do dia, no topo do documento.)*

### 4.2 Cobertura parcial relevante

Estas áreas já começaram, mas ainda não cumprem o nível esperado da especificação:

- **Seção 5**: identidade;
- **Seção 7**: locais, mapa e capacidade *(atualizado 2026-08-05, fechamento do dia: melhor assento, assentos contíguos, acessibilidade por assento, mapa interativo com zoom/pan/pinça (validado ponta-a-ponta, inclusive pinch-zoom via CDP) e portarias formais (`EventGate`) já entregues — ver 3.1/4.1; falta supervisão operacional de capacidade em cenários de grande porte; resta ressalva de touch mobile no mapa (desselecionar por segundo toque))*;
- **Seção 10**: marketplace e descoberta;
- **Seção 15**: promoções e cortesias *(atualizado 2026-08-05, fechamento do dia: cortesia via cupom 100%, guest list e afiliados/comissão (Fase 6) já entregues; payout automático de comissão decidido não fazer (definitivo, fica manual); faltam links promocionais avançados e campanhas condicionais)*;
- **Seção 18**: comunicação transacional *(atualizado 2026-08-05, fechamento do dia: tracking unificado de envios e templates configuráveis + fallback de provedor já entregues, aguardando credencial real para ativação completa)*;
- **Seção 20**: bilheteria/POS *(atualizado 2026-08-03: caixa presencial com `CashSession` e comprovante imprimível já entregues; falta portarias/zonas e supervisão operacional mais madura)*;
- **Seções 26 e 28**: cancelamentos e fiscal *(atualizado 2026-08-05, fechamento do dia: NFe/ERP fiscal real decidido não fazer — decisão de produto definitiva, não pendência técnica)*;
- **Seção 32**: API pública e ecossistema de integrações;
- **Seções 38 a 41**: resiliência, observabilidade, concorrência e alta demanda (fila virtual/antibot/antifraude entregues em 4.1, Turnstile + rate limiting adaptativo entregues aguardando credencial; falta CAPTCHA de vendor real além do Turnstile, testes de carga formais e runbooks).

### 4.3 Grandes blocos ainda ausentes

Os seguintes grupos estão essencialmente **não implementados** ou só aparecem como intenção futura:

- **Credenciamento corporativo completo** (seção 21) — Fase 8, não iniciada.
- **Eventos online e híbridos** (seção 22) — Fase 8, não iniciada.
- **Cashless/consumo interno** (seção 24).
- **Wallet pass (Apple/Google)** — *(atualizado 2026-08-05, fechamento do dia: decidido NÃO fazer, decisão de produto definitiva — não é mais "adiado por falta de credencial")*.
- **Múltiplos gateways com roteamento e redundância operacional** (seção 12 avançada) — *(atualizado 2026-08-05, fechamento do dia: decidido NÃO fazer, decisão de produto definitiva de operar somente com PagBank — não é lacuna técnica)*.
- **Payout real de comissão de afiliado** — *(atualizado 2026-08-05, fechamento do dia: decidido NÃO fazer, decisão de produto definitiva; fica manual/staff, como já documentado)*.
- **Documentos fiscais e integração ERP completas** (seção 28) — *(atualizado 2026-08-05, fechamento do dia: decidido NÃO fazer, decisão de produto definitiva)*.
- **Marketplace de integrações, portal do desenvolvedor e sandbox** (seção 32) — Fase 8.
- **Migração/importação estruturada de dados legados** (seção 33).
- **Internacionalização completa** (seção 34) — Fase 8.
- **BI / data warehouse / recomendações preditivas** (seção 29 avançada) — Fase 8.

## 5. Ajustes e remoções recomendados

### 5.1 Itens que precisam de correção imediata de contexto

- **README atual está desalinhado** com o produto:
  - ainda fala em clientes, produtos, estoque e operação comercial genérica;
  - ainda descreve `site/` e até `app/` como partes ativas do ecossistema;
  - precisa ser reescrito para o recorte real do PegaTicket.
- **Memórias internas em `.claude/memory` estão parcialmente desatualizadas**:
  - várias ainda descrevem PDV, balcão, iFood, delivery, estoque, catálogo alimentar e rotas antigas;
  - precisam ser separadas entre histórico legado e contexto ativo.
- **Roadmaps antigos ainda misturam migração de Maskats com produto final**:
  - hoje servem como histórico técnico;
  - não devem ser tratados como roadmap principal do PegaTicket.

### 5.2 Blocos candidatos a remoção de escopo do produto atual

Se o foco agora é uma plataforma de ingressos e eventos, os seguintes blocos devem ser tratados como **fora de fase** ou removidos do discurso ativo:

- referências a **iFood/marketplace de food**;
- referências a **PDV/Balcão/comandas/restaurante** se não forem reaproveitadas como bilheteria presencial — o domínio `CashSession` (caixa) já é a reaproveitação confirmada para bilheteria presencial, entregue (ver 3.1/4.1);
- resíduos de **estoque físico/logística de delivery**;
- materiais que descrevem o produto como SaaS de comércio geral em vez de ticketing.

### 5.3 Blocos que devem ser congelados, não expandidos

- `site/` institucional, se a decisão continuar sendo operar sem frente pública separada;
- qualquer camada antiga ligada a marketplace de delivery;
- módulos de CRM genérico que não estejam ligados diretamente a comprador, evento e recompra.

## 6. Leitura estratégica: em que fase estamos de verdade

**Atualizado 2026-08-05**: o PegaTicket avançou de "Fases 0 a 4 essencialmente fechadas, Fase 5 em kickoff" para **Fases 0 a 7 essencialmente fechadas**. Uma sessão longa de desenvolvimento fechou financeiro (Fase 5), growth/afiliados/CRM (Fase 6) e risco/antifraude/alta demanda (Fase 7) por completo, além de entregas pontuais (portarias formais, lista de espera, tracking de comunicação, mapa interativo).

Em termos práticos:

- o **núcleo transacional** já existe, incluindo pagamento com cartão parcelado via PagBank;
- o **núcleo operacional de ticketing** já está consolidado (transferência de titularidade, guest list/cortesias, alocação de assentos, caixa presencial, sincronização de check-in, portarias formais);
- o **núcleo financeiro** já está consolidado (recebíveis/ledger, settlements, split+custódia PagBank, fechamento por evento, painel tenant-scoped);
- o **núcleo de growth** já está consolidado (afiliados/comissão, CRM/segmentação, UTM/pixels, recompra automática) — payout automático de comissão **decidido não fazer** (definitivo, fica manual/staff);
- o **núcleo de risco/alta demanda** já está consolidado (fila virtual, antibot, motor de antifraude com 6 heurísticas, observabilidade estendida, Turnstile + rate limiting adaptativo aguardando credencial real);
- o **núcleo de inventário avançado e pós-compra** também fechou mais uma camada nesta mesma data: cotas de inventário por canal e revenda oficial verificada (repasse manual) — ver nota de revisão 2026-08-05, fechamento do dia;
- o sistema **ainda não é competitivo nacionalmente** contra plataformas maduras, mas fechou a maior parte do núcleo Must Have e boa parte do Should Have;
- o discurso comercial e documental **ainda precisa ser consolidado** para o domínio final;
- restam lacunas finas (portaria/capacidade madura para grandes eventos, ressalva de touch mobile no mapa interativo — segundo toque não desseleciona) — ver nota de revisão 2026-08-05, fechamento do dia;
- decisões de produto agora **fechadas em definitivo** (não são mais lacunas em aberto): wallet pass, payout automático de afiliado, multi-gateway de pagamento e NFe/ERP fiscal real — todas decididas como "não fazer" pelo usuário. **Turnstile e fallback de mailer também fecharam como decisão**: usuário confirmou manter CAPTCHA desabilitado e um único provedor de e-mail por enquanto — deixam de ser "aguardando credencial" e passam a ser configuração deliberada.

Diagnóstico objetivo:

- **Pronto para continuar construção do produto**: sim.
- **Pronto para operar eventos pequenos e médios com bilheteria presencial, assentos e pós-compra básico**: sim.
- **Pronto para operar eventos com financeiro/repasse, growth/afiliados e proteção contra fraude/alta demanda**: sim, no nível atual do produto (sem testes de carga formais de Fase 7 ainda documentados; Turnstile/rate limiting adaptativo precisam de credencial real pra proteção efetiva).
- **Pronto para ser tratado como plataforma enterprise de eventos**: ainda não.
- **Pronto para vendas críticas de grande porte**: não.

## 7. Roadmap recomendado

### Fase 0 — Saneamento e alinhamento do produto

**Status (2026-08-03): parte técnica essencialmente coberta pelo núcleo entregue; documentação/memória ainda pedem revisão pontual (fora do escopo desta atualização de roadmap).**

Objetivo: eliminar ambiguidade entre legado, documentação e escopo atual.

Entregas:

- reescrever `README.md` para o produto real;
- criar memória operacional nova do PegaTicket atual;
- classificar documentos antigos em `histórico` vs `ativo`;
- remover ou congelar referências de delivery/food/estoque antigo;
- revisar nomenclatura remanescente de domínio nas rotas, comentários e contratos.

Critério de saída:

- qualquer pessoa nova entende o sistema como plataforma de ingressos, não como fork de comércio genérico.

### Fase 1 — Fechamento do núcleo comercial do ticketing

**Status (2026-08-03): essencialmente fechada** — lifecycle de venda/pagamento (incluindo cartão PagBank), holds/lotes com virada automática, emissão e transferência de ticket, e reembolso/cancelamento básico já entregues (ver seção 3.1).

Objetivo: concluir o núcleo Must Have da especificação.

Entregas:

- completar lifecycle de evento e publicação;
- completar lifecycle de venda e pagamento;
- endurecer holds, lotes e regras de disponibilidade;
- fechar emissão de ticket, reemissão e rastreabilidade;
- ampliar check-in com zonas, reentrada, motivos e métricas básicas;
- fechar reembolso/cancelamento básico ponta a ponta;
- consolidar comunicação transacional mínima.

Critério de saída:

- evento presencial simples pode ser criado, vendido, operado e encerrado sem intervenção técnica manual.

### Fase 2 — Bilheteria presencial e operação de acesso robusta

**Status (2026-08-05): essencialmente fechada** — caixa presencial (`CashSession`), comprovante imprimível, modo offline de check-in, sincronização entre dispositivos (polling 15s), dashboard operacional em tempo quase real e portarias formais (`EventGate`, integrado ao `CheckinService`) já entregues (ver seção 3.1). Falta amadurecer zonas mais finas e supervisão operacional para eventos de grande porte.

Objetivo: transformar o núcleo atual em operação real de evento.

Entregas:

- bilheteria presencial real;
- caixa e estações de venda;
- impressão/comprovantes operacionais;
- modo offline controlado para acesso;
- sincronização entre dispositivos de check-in;
- portarias, zonas e supervisão operacional;
- dashboards operacionais em tempo quase real.

Critério de saída:

- evento pequeno e médio pode operar compra presencial e acesso com contingência.

### Fase 3 — Assentos, mapas e inventário avançado

**Status (2026-08-05, fechamento do dia): fechada.** Melhor assento disponível, assentos contíguos, acessibilidade por assento na loja pública, virada automática de lote, bloqueio administrativo de assento, mapa interativo (`SeatMapViewer.tsx`, zoom/pan/pinça, **validado ponta-a-ponta incluindo pinch-zoom real via CDP**), lista de espera de ingresso esgotado (`TicketTypeWaitlistEntry`) e **cotas de inventário por canal** (`TicketTypeChannelQuota`) já entregues (ver seção 3.1). Resta só a ressalva de touch mobile no mapa (desselecionar por segundo toque) e teste e2e automatizado persistido.

Objetivo: sair de evento simples e ir para evento com controle fino de ocupação.

Entregas:

- mapa interativo de assentos;
- melhor assento disponível;
- assentos contíguos;
- acessibilidade por assento;
- virada automática de lote;
- inventário compartilhado e cotas por canal;
- reservas administrativas e bloqueios operacionais.

Critério de saída:

- o produto suporta teatro, cinema, arena setorizada e mesas/camarotes com coerência operacional.

### Fase 4 — Pós-compra competitivo

**Status (2026-08-05, fechamento do dia): essencialmente fechada** — titularidade/transferência de ingresso, cortesias/convidados/convites (guest list + cupom 100%) e **revenda oficial verificada** (`TicketResaleListing`, repasse ao vendedor manual/pendente de liberação) já entregues (ver seção 3.1). **Wallet pass decidido não fazer** (decisão de produto definitiva, sai da lista de pendências). Falta apenas reemissão assistida.

Objetivo: diminuir atrito do comprador e reduzir dependência de atendimento manual.

Entregas:

- ~~wallet pass~~ (decidido não fazer — decisão de produto, 2026-08-05);
- titularidade e transferência;
- reemissão assistida;
- central do comprador ampliada;
- autosserviço de cancelamento elegível;
- cupons, cortesias, convidados e convites bem estruturados;
- políticas versionadas por compra.

Critério de saída:

- comprador consegue administrar seu ingresso sem acionar suporte na maioria dos casos.

### Fase 5 — Financeiro, repasses e fiscal

**Status (2026-08-05): essencialmente fechada.** A decisão de produto foi:

- a taxa da plataforma será um **valor fixo em BRL configurado pelo administrador do sistema**, igual para todas as empresas, podendo ser `R$ 0,00` ou mais;
- o modelo principal aprovado para a Fase 5 é **PagBank Split com a plataforma como recebedor primário e o organizador como recebedor secundário**;
- a **taxa fixa global** será retida no próprio split;
- a parcela do organizador começará em **custódia**;
- o **repasse padrão será D+1 após o fim do evento**;
- **não haverá reserva extra no primeiro marco**, além da custódia.

Objetivo: fechar o ciclo financeiro do organizador.

Entregas:

- recebíveis;
- repasses e agenda de liquidação;
- reservas de risco;
- comissões;
- conciliação avançada;
- ledger/livro razão simplificado;
- fechamento financeiro por evento;
- ~~emissão fiscal e integração ERP em escopo viável~~ (decidido não fazer — decisão de produto, 2026-08-05).

Atualização em **4 de agosto de 2026**:

- configuração global da taxa fixa já implementada;
- `receivables`, `settlements` e baixa com custódia PagBank já implementados no primeiro nível operacional;
- exceções de `refund`, `chargeback` e `fraud review` já geram `settlement_adjustments` e `ledger_entries`;
- resolução operacional de `pending_recovery` e `pending_review` já entrou em implementação;
- ajustes manuais auditáveis já entraram em implementação;
- reconciliação estrutural inicial do financeiro já entrou em implementação;
- fechamento financeiro inicial por evento e borderô CSV inicial já entraram em implementação em 4 de agosto de 2026;
- painel financeiro tenant-scoped, lista de recebíveis e lista de repasses já entraram em implementação em 4 de agosto de 2026;
- superfície administrativa global inicial do financeiro também já entrou em implementação em 4 de agosto de 2026;
- UI operacional inicial do financeiro também já entrou em implementação em 4 de agosto de 2026.

Atualização em **5 de agosto de 2026**: Fase 5 fechada. Todos os ~13 services de `api/app/Services/Finance/*` (`SettlementGenerationService`, `SettlementReleaseService`, `RiskReserveReleaseService`, `ReceivableGenerationService`, `ReconciliationService`, `EventFinancialCloseoutService`, `AdminFinanceOperationsService`, `FinanceOperationsService`, `PlatformFinanceSettingsService`, `FinancialIntegrityReconciliationService`, `SaleRefundFinancialAdjustmentService`, `ExternalReviewFinancialAdjustmentService`, `SettlementAdjustmentWorkflowService`) confirmados no código. Principal lacuna restante: refinamento contínuo de reconciliação/exportação conforme o volume de operação crescer.

Atualização em **5 de agosto de 2026 (fechamento do dia)**: NFe/ERP fiscal real foi confirmado como **decisão de produto de não fazer** (não mais "fora de escopo declarado" temporário) — PegaTicket não vai emitir documento fiscal nem integrar ERP. Fase 5 permanece fechada sem essa entrega, por decisão, não por lacuna técnica.

Critério de saída:

- o organizador entende claramente receita, taxas, saldo, repasse e pendências.

### Fase 6 — Growth, afiliados e CRM

**Status (2026-08-05, fechamento do dia): fechada** — afiliados/comissão (`Affiliate`/`AffiliateCommission`), UTM capture no `web/` e no `site/`, pixels Meta/GA4 (`marketingPixels.ts`), CRM/segmentação (`FinalCustomerTenantLinkRepository::crmSummaryForTenant`) e recompra automática (`SendRecompraNudgeMailsCommand`) já entregues. **Payout automático de comissão de afiliado decidido não fazer** (decisão de produto definitiva, fica manual/staff) — não é mais uma pendência em aberto. Falta apenas campanhas condicionais mais avançadas.

Objetivo: tornar a plataforma mais competitiva comercialmente.

Entregas:

- afiliados/promotores;
- links e códigos rastreáveis;
- atribuição e comissão;
- CRM do comprador;
- segmentação e audiências;
- automações transacionais e de recompra;
- pixels, UTM e campanhas.

Critério de saída:

- o organizador consegue vender melhor e medir melhor sem depender de ferramentas externas em tudo.

### Fase 7 — Risco, antifraude e alta demanda

**Status (2026-08-05, fechamento do dia): essencialmente fechada** — motor de risco com 6 heurísticas (`RiskEngineService`: recompra suspeita, pagamento recusado, múltiplos cartões, cartão compartilhado, IP compartilhado, abuso de reembolso), proteção anti-bot (`AntiBotGuardService`), fila virtual opt-in (`VirtualQueueService`), observabilidade estendida (`OperationSnapshotService`) e agora também **CAPTCHA real via Cloudflare Turnstile** (`TurnstileVerificationService`) + **rate limiting adaptativo** (`AdaptiveThrottleMiddleware`) já entregues — integrados em hold de checkout, resgate de convite e lista de espera. Ambos ficam em modo desabilitado até o usuário preencher `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`/`VITE_TURNSTILE_SITE_KEY` reais — ação pendente do usuário, não gap de código. Falta apenas testes de carga formais e runbooks documentados.

Objetivo: preparar o produto para vendas críticas.

Entregas:

- motor de risco;
- proteção anti-bot;
- fila virtual;
- regras adaptativas;
- observabilidade operacional de venda crítica;
- testes de carga e runbooks;
- contingência de integrações e plano de freeze.

Critério de saída:

- o sistema deixa de ser “funcional” e passa a ser “resiliente para evento grande”.

### Fase 8 — Plataforma avançada e diferenciação

**Status (2026-08-03): não iniciada.** *(atualizado 2026-08-05, fechamento do dia: revenda oficial verificada saiu desta fase — já entregue, com repasse manual, ver Fase 4/seção 3.1. Fase 8 permanece não iniciada quanto ao restante.)*

Objetivo: abrir as frentes de maior diferencial contra concorrentes.

Entregas:

- ~~revenda oficial verificada~~ (entregue em 2026-08-05, ver Fase 4);
- eventos online/híbridos;
- credenciamento corporativo;
- APIs públicas maduras;
- sandbox e portal do desenvolvedor;
- integrações nativas;
- BI e data warehouse;
- internacionalização e white-label enterprise.

Critério de saída:

- PegaTicket passa a competir como plataforma completa, não só como emissor.

## 8. Priorização executiva recomendada

**Nota (2026-08-05): como as Fases 0-7 estão essencialmente fechadas, a prioridade máxima real hoje é a Fase 8 (diferenciação de plataforma) e as decisões de produto adiadas (wallet pass, payout de afiliado, revenda oficial, multi-gateway, cotas por canal), além de amadurecer lacunas finas remanescentes (portaria/capacidade para grandes eventos, validação manual/e2e do mapa interativo) — a priorização abaixo reflete o plano original e permanece válida como ordem de fundo.**

**Nota (2026-08-05, fechamento do dia)**: das decisões de produto citadas acima, quatro já **fecharam em definitivo** nesta mesma data — wallet pass, payout automático de afiliado, multi-gateway e NFe/ERP fiscal real foram todos decididos como "não fazer" pelo usuário (deixam de ser "adiados" e não voltam ao roadmap). Cotas por canal e revenda oficial verificada **saíram da lista de pendências**: ambas foram entregues (`TicketTypeChannelQuota`, `TicketResaleListing`, ver seção 3.1). Também foram entregues Turnstile + rate limiting adaptativo e templates de e-mail configuráveis + fallback de provedor, ambos aguardando só credencial real para ativação. A prioridade máxima real agora fica ainda mais concentrada na Fase 8 e nas lacunas finas restantes (touch mobile do mapa, portaria/capacidade de grande porte, credenciais pendentes).

### Prioridade máxima agora (histórico — já concluída)

- Fase 0
- Fase 1
- Fase 2

Essas fases fecham o núcleo real do produto.

### Prioridade alta na sequência (histórico — já concluída)

- Fase 3
- Fase 4
- Fase 5

Essas fases tornam o produto comercializável com muito mais força.

### Prioridade posterior

- Fase 6 — **concluída (2026-08-05)**
- Fase 7 — **concluída (2026-08-05)**
- Fase 8 — não iniciada, próxima fronteira real

Essas fases elevam competitividade e escala.

## 9. Backlog de remoção e alinhamento documental

Antes de expandir features, vale abrir um épico só de alinhamento:

1. Atualizar `README.md`.
2. Criar um “mapa do produto atual” canônico em `docs/`.
3. Arquivar roadmaps de migração e memórias de delivery fora do fluxo principal.
4. Revisar `.claude/memory/project-summary.md`.
5. Revisar documentação comercial e de arquitetura para vocabulário consistente de ticketing.

## 10. Conclusão executiva

O projeto **não está no zero**. A base transacional é boa e a arquitetura já tem bastante material reaproveitável.

**Atualizado 2026-08-03**: as Fases 0 a 4 estão essencialmente fechadas (núcleo transacional, ticketing, bilheteria presencial, assentos/inventário e pós-compra). A especificação completa ainda descreve uma plataforma significativamente maior do que o produto entregue hoje, mas o gap relevante agora está concentrado em Fase 5 em diante — financeiro/repasses (agora com modelo aprovado de split custodial), CRM/growth, antifraude/alta demanda e diferenciação de plataforma.

**Atualizado 2026-08-05**: as Fases 0 a 7 estão essencialmente fechadas. Financeiro (Fase 5 — recebíveis/ledger/settlements/split+custódia PagBank), growth (Fase 6 — afiliados/comissão/CRM/pixels/UTM/recompra) e risco/alta demanda (Fase 7 — motor de risco de 6 heurísticas/antibot/fila virtual) foram confirmados no código, não apenas anunciados. Restam: decisões de produto adiadas (wallet pass, payout de afiliado, revenda oficial, multi-gateway, cotas por canal), itens fora de escopo declarado (NFe/ERP fiscal real, CAPTCHA de vendor real, rate limiting adaptativo, templates de e-mail configuráveis), a pendência honesta de validação manual/e2e completa do mapa interativo de assentos, e a Fase 8 inteira (revenda oficial madura, eventos online/híbridos, credenciamento corporativo, API pública/sandbox, BI, internacionalização) — não iniciada.

**Atualizado 2026-08-05 (fechamento do dia)**: mais uma rodada de entregas fechou boa parte do que a nota anterior deste mesmo dia ainda listava como pendente. **Decisões de produto agora definitivas** (não mais "adiadas"): wallet pass, payout automático de comissão de afiliado, multi-gateway de pagamento e NFe/ERP fiscal real — todos confirmados pelo usuário como "não fazer", não lacunas técnicas em aberto. **Entregues nesta rodada**: cotas de inventário por canal (`TicketTypeChannelQuota`), revenda oficial verificada com repasse manual (`TicketResaleListing`), CAPTCHA real via Turnstile + rate limiting adaptativo (`TurnstileVerificationService`/`AdaptiveThrottleMiddleware`) e templates de e-mail configuráveis + fallback de provedor (`EmailTemplate`/`MAIL_MAILER_FALLBACK`) — os dois últimos aguardando só credencial real do usuário para ativação completa, não código faltando. **Mapa interativo**: deixa de ser "pendência honesta de validação" e passa a "validado ponta-a-ponta, incluindo pinch-zoom real via CDP", com uma ressalva única remanescente (desselecionar por segundo toque não funcionou no teste automatizado mobile). Contagens: 298 rotas (era 282) e 753 testes/2789 assertions (era 720/2638). O que resta de fato em aberto agora é: a Fase 8 inteira (eventos online/híbridos, credenciamento corporativo, API pública/sandbox, BI, internacionalização), supervisão operacional de capacidade/portaria para eventos de grande porte, a ressalva de touch mobile do mapa, e a ativação das duas credenciais pendentes (Turnstile, segundo provedor SMTP).

A recomendação é:

1. **sanear contexto e escopo** (documentação/README/memória, ainda pendente);
2. **fechar o núcleo operacional de ticketing** — feito;
3. **concluir bilheteria/acesso/assentos** — feito, com lacuna fina de supervisão para eventos de grande porte;
4. **entrar em financeiro, CRM e antifraude só depois do núcleo estar firme** — feito: financeiro (Fase 5), growth (Fase 6) e risco/antifraude (Fase 7) estão fechados;
5. **avançar para diferenciação de plataforma (Fase 8) e decisões de produto adiadas** — próximo passo real.

Se seguirmos essa ordem, o roadmap sai de “documento aspiracional” e vira um plano executável, com risco controlado e evolução coerente do produto.
