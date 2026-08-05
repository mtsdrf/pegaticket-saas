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

Estado validado nesta leitura (atualizado 2026-08-05):

- backend com **282 rotas** ativas em `api/v1` (era 260 em 2026-08-03, 249 antes disso);
- backend com **720 testes passando** (**2638 assertions**) em `composer test`, incluindo os testes novos de Fase 5/6/7 (risco, fila virtual, lista de espera) — era 612/2155 em 2026-08-03;
- frontend com páginas reais para administração, eventos, checkout, portal, tickets, analytics, reconciliação, suporte, operação, financeiro tenant-scoped e crescimento/afiliados;
- suíte E2E web já instalada e funcional, mas **sem cobertura ainda** do novo mapa interativo de assentos (`SeatMapViewer.tsx`).

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
- **Comunicação — tracking unificado** *(entregue — atualizado 2026-08-05)*: `CommunicationLog`/`CommunicationDispatcherService`, os 7 Mailables transacionais logados com status sucesso/falha. Continua sem templates configuráveis nem fallback de provedor (fora de escopo declarado).
- **Mapa interativo da loja pública** *(entregue com ressalva — atualizado 2026-08-05)*: `SeatMapViewer.tsx`, SVG com zoom/pan/pinça, clique por assento, tokens de tema. Pinch-zoom multitoque real em dispositivo físico e o fluxo ponta-a-ponta com evento real publicado **não foram validados manualmente** (só preview mockado), e não há teste e2e cobrindo o componente ainda.
- **Administração global**: usuários, grupos, funcionalidades, planos, tenants, auditoria.
- **Assinatura SaaS**: planos, subscription, invoice, cobrança recorrente e telas administrativas/operacionais.
- **Suporte básico**: `help requests` e tela de tickets de suporte.

### 3.2 Blocos existentes mas parciais

Estão implementados parcialmente, porém ainda abaixo da especificação:

- **Identidade**: existe cadastro/login/recuperação, mas faltam passkeys, MFA robusto, gestão de dispositivos e KYC/KYB real.
- **Organizações e permissões**: existe base forte de RBAC, mas faltam aprovações em múltiplas etapas, segregação de funções madura e escopo fino por evento/portaria.
- **Locais e mapa** *(reduzido — atualizado 2026-08-05)*: venues, seats e map versions existem; melhor assento, assentos contíguos, acessibilidade por assento na loja pública, bloqueio administrativo, portarias formais (`EventGate`) e mapa interativo com zoom/pan/pinça (`SeatMapViewer.tsx`, ver 3.1) já foram entregues. Ainda falta amadurecer supervisão operacional de capacidade/portaria em cenários de grande porte; e o mapa interativo tem pendência de validação manual/e2e (ver nota de revisão 2026-08-05).
- **Eventos**: CRUD existe, mas fluxo editorial, aprovação, publicação programada, adiamento e operação documental ainda estão incompletos.
- **Inventário** *(reduzido — atualizado 2026-08-05)*: ticket types, batches e holds existem, incluindo virada automática de lote e lista de espera de ingresso esgotado (`TicketTypeWaitlistEntry`, ver 3.1); ainda faltam cotas de inventário por canal (adiado, precisa de decisão de produto sobre o que define um "canal"), combos e upgrade real.
- **Checkout**: existe fluxo público consistente, mas faltam guest checkout maduro, formulários por ingresso, split de pagamento, retentativas completas e jornada de alta demanda.
- **Pagamentos** *(reduzido — atualizado 2026-08-05)*: Pix e cartão (crédito/débito, tokenização, parcelamento) via PagBank, antifraude (`RiskEngineService`, 6 heurísticas) e split/custódia financeiro real (Fase 5, ver 3.1) já existem; falta múltiplos gateways ativos e roteamento inteligente entre eles.
- **Vendas/Vendas**: o domínio base existe, mas exportações, documentos operacionais, importação e busca operacional avançada ainda estão incompletos.
- **Tickets pós-compra** *(reduzido — atualizado 2026-08-03)*: emissão, check-in e transferência de titularidade já existem (ver 3.1); ainda faltam wallet passes (adiado — sem credenciais Apple/Google ainda), revenda oficial e QR rotativo avançado.
- **Promoções** *(reduzido — atualizado 2026-08-05)*: cupom, cortesia via cupom 100%, guest list e afiliados/comissão (Fase 6, ver 3.1) já existem; ainda faltam links promocionais avançados, payout real de comissão de afiliado e campanhas condicionais.
- **Comunicação** *(reduzido — atualizado 2026-08-05)*: tracking unificado de envios já existe (`CommunicationLog`/`CommunicationDispatcherService`, ver 3.1); ainda falta hub transacional completo com templates configuráveis e fallback de provedor (fora de escopo declarado).
- **Check-in** *(reduzido — atualizado 2026-08-05)*: base funcional entregue, com offline forte, sincronização entre dispositivos via polling de 15s e portarias formais (`EventGate`, ver 3.1); ainda faltam reentrada, controle por zona mais fino e supervisão operacional avançada.
- **Financeiro/Fiscal** *(reduzido — atualizado 2026-08-05)*: a **Fase 5 foi fechada** (ver 3.1) — recebíveis/ledger, settlements, split+custódia PagBank, fechamento financeiro por evento, borderô, painel tenant-scoped e superfície administrativa global já implementados. Ainda faltam NFe/ERP fiscal real (fora de escopo declarado) e refinamento contínuo de reconciliação/exportação.
- **API e integrações**: API versionada existe, assim como webhooks internos de pagamento, mas falta a superfície pública madura para terceiros.

## 4. Delta contra a especificação

### 4.1 Cobertura forte hoje

Estas áreas da especificação já têm fundação pronta suficiente para continuar evoluindo sem recomeçar:

- Seções **4, 6, 8, 9, 11, 12, 13, 14, 16, 17, 18, 19, 20, 25, 27, 29, 30, 31, 36, 37, 42, 43 e 44** em nível básico a intermediário a maduro.

Resumo:

- multi-tenant;
- permissões;
- eventos;
- inventário base (incluindo virada automática de lote e lista de espera);
- checkout base;
- pagamentos base (Pix e cartão via PagBank, com parcelamento e tokenização);
- vendas/vendas;
- tickets (incluindo transferência de titularidade);
- guest list / cortesias estruturadas;
- alocação de assentos (melhor assento, contíguos, acessibilidade na loja pública) e mapa interativo da loja pública;
- caixa / bilheteria presencial (`CashSession`);
- check-in (incluindo offline, sincronização por polling entre dispositivos e portarias formais via `EventGate`);
- financeiro (recebíveis, settlements, split+custódia PagBank, fechamento por evento);
- growth (afiliados/comissão, UTM, pixels, CRM/segmentação, recompra automática);
- risco e alta demanda (fila virtual, antibot, motor de antifraude com 6 heurísticas);
- comunicação com tracking unificado de envios;
- suporte;
- analytics inicial;
- administração global;
- LGPD, segurança, CI/CD e testes iniciais.

*(atualizado 2026-08-05: Fases 5, 6 e 7 fechadas — financeiro/settlements/split-custódia, growth/afiliados/CRM/pixels e risco/fila virtual/antibot/antifraude confirmados como entregues e movidos para esta lista; também portarias formais (`EventGate`), lista de espera de ingresso e tracking unificado de comunicação. Ver nota de revisão 2026-08-05 no topo do documento.)*

### 4.2 Cobertura parcial relevante

Estas áreas já começaram, mas ainda não cumprem o nível esperado da especificação:

- **Seção 5**: identidade;
- **Seção 7**: locais, mapa e capacidade *(atualizado 2026-08-05: melhor assento, assentos contíguos, acessibilidade por assento, mapa interativo com zoom/pan/pinça e portarias formais (`EventGate`) já entregues — ver 3.1/4.1; falta supervisão operacional de capacidade em cenários de grande porte; mapa interativo com pendência de validação manual/e2e)*;
- **Seção 10**: marketplace e descoberta;
- **Seção 15**: promoções e cortesias *(atualizado 2026-08-05: cortesia via cupom 100%, guest list e afiliados/comissão (Fase 6) já entregues; faltam links promocionais avançados, payout real de comissão e campanhas condicionais)*;
- **Seção 18**: comunicação transacional *(atualizado 2026-08-05: tracking unificado de envios já entregue; falta hub com templates configuráveis e fallback de provedor)*;
- **Seção 20**: bilheteria/POS *(atualizado 2026-08-03: caixa presencial com `CashSession` e comprovante imprimível já entregues; falta portarias/zonas e supervisão operacional mais madura)*;
- **Seções 26 e 28**: cancelamentos e fiscal;
- **Seção 32**: API pública e ecossistema de integrações;
- **Seções 38 a 41**: resiliência, observabilidade, concorrência e alta demanda (fila virtual/antibot/antifraude entregues em 4.1; falta testes de carga formais e runbooks).

### 4.3 Grandes blocos ainda ausentes

Os seguintes grupos estão essencialmente **não implementados** ou só aparecem como intenção futura:

- **Credenciamento corporativo completo** (seção 21) — Fase 8, não iniciada.
- **Eventos online e híbridos** (seção 22) — Fase 8, não iniciada.
- **Cashless/consumo interno** (seção 24).
- **Revenda oficial e cadeia de custódia avançada** (seção 14 avançada) — *(atualizado 2026-08-03: transferência básica de titularidade já existe via `TicketService::transfer()`; o que falta é especificamente revenda oficial verificada e QR rotativo avançado)*.
- **Wallet pass (Apple/Google)** — adiado por decisão do usuário, sem credenciais ainda.
- **Múltiplos gateways com roteamento e redundância operacional** (seção 12 avançada).
- **Payout real de comissão de afiliado** — Fase 6 entregue o essencial (atribuição/comissão calculada), mas o repasse financeiro em si depende de decisão de produto pendente.
- **Documentos fiscais e integração ERP completas** (seção 28) — fora de escopo declarado.
- **Marketplace de integrações, portal do desenvolvedor e sandbox** (seção 32) — Fase 8.
- **Migração/importação estruturada de dados legados** (seção 33).
- **Internacionalização completa** (seção 34) — Fase 8.
- **BI / data warehouse / recomendações preditivas** (seção 29 avançada) — Fase 8.
- **Cotas de inventário por canal** (loja online vs. presencial) — adiado, precisa de decisão de produto sobre o que define um "canal".

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
- o **núcleo de growth** já está consolidado (afiliados/comissão, CRM/segmentação, UTM/pixels, recompra automática) — falta apenas payout real de comissão (decisão de produto);
- o **núcleo de risco/alta demanda** já está consolidado (fila virtual, antibot, motor de antifraude com 6 heurísticas, observabilidade estendida);
- o sistema **ainda não é competitivo nacionalmente** contra plataformas maduras, mas fechou a maior parte do núcleo Must Have e boa parte do Should Have;
- o discurso comercial e documental **ainda precisa ser consolidado** para o domínio final;
- restam lacunas finas (mapa interativo com pendência de validação manual/e2e, portaria/capacidade madura para grandes eventos) e decisões de produto adiadas (wallet pass, payout de afiliado, revenda oficial, multi-gateway, cotas por canal) — ver nota de revisão 2026-08-05 e Fase 8.

Diagnóstico objetivo:

- **Pronto para continuar construção do produto**: sim.
- **Pronto para operar eventos pequenos e médios com bilheteria presencial, assentos e pós-compra básico**: sim.
- **Pronto para operar eventos com financeiro/repasse, growth/afiliados e proteção contra fraude/alta demanda**: sim, no nível atual do produto (sem testes de carga formais de Fase 7 ainda documentados).
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

**Status (2026-08-05): essencialmente fechada** — melhor assento disponível, assentos contíguos, acessibilidade por assento na loja pública, virada automática de lote, bloqueio administrativo de assento, mapa interativo (`SeatMapViewer.tsx`, zoom/pan/pinça) e lista de espera de ingresso esgotado (`TicketTypeWaitlistEntry`) já entregues (ver seção 3.1). Falta apenas cotas por canal (adiado, decisão de produto) e validação manual/e2e completa do mapa interativo em dispositivo físico.

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

**Status (2026-08-03): essencialmente fechada** — titularidade/transferência de ingresso e cortesias/convidados/convites (guest list + cupom 100%) já entregues (ver seção 3.1). Falta wallet pass (adiado, sem credenciais), reemissão assistida e revenda oficial.

Objetivo: diminuir atrito do comprador e reduzir dependência de atendimento manual.

Entregas:

- wallet pass;
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
- emissão fiscal e integração ERP em escopo viável.

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

Atualização em **5 de agosto de 2026**: Fase 5 fechada. Todos os ~13 services de `api/app/Services/Finance/*` (`SettlementGenerationService`, `SettlementReleaseService`, `RiskReserveReleaseService`, `ReceivableGenerationService`, `ReconciliationService`, `EventFinancialCloseoutService`, `AdminFinanceOperationsService`, `FinanceOperationsService`, `PlatformFinanceSettingsService`, `FinancialIntegrityReconciliationService`, `SaleRefundFinancialAdjustmentService`, `ExternalReviewFinancialAdjustmentService`, `SettlementAdjustmentWorkflowService`) confirmados no código. Principal lacuna restante: NFe/ERP fiscal real (fora de escopo declarado) e refinamento contínuo de reconciliação/exportação conforme o volume de operação crescer.

Critério de saída:

- o organizador entende claramente receita, taxas, saldo, repasse e pendências.

### Fase 6 — Growth, afiliados e CRM

**Status (2026-08-05): essencialmente fechada** — afiliados/comissão (`Affiliate`/`AffiliateCommission`), UTM capture no `web/` e no `site/`, pixels Meta/GA4 (`marketingPixels.ts`), CRM/segmentação (`FinalCustomerTenantLinkRepository::crmSummaryForTenant`) e recompra automática (`SendRecompraNudgeMailsCommand`) já entregues. Falta apenas payout real de comissão de afiliado (decisão de produto pendente) e campanhas condicionais mais avançadas.

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

**Status (2026-08-05): essencialmente fechada** — motor de risco com 6 heurísticas (`RiskEngineService`: recompra suspeita, pagamento recusado, múltiplos cartões, cartão compartilhado, IP compartilhado, abuso de reembolso), proteção anti-bot (`AntiBotGuardService`), fila virtual opt-in (`VirtualQueueService`) e observabilidade estendida (`OperationSnapshotService`) já entregues. Falta CAPTCHA de vendor real e rate limiting adaptativo (fora de escopo declarado), além de testes de carga formais e runbooks documentados.

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

**Status (2026-08-03): não iniciada.**

Objetivo: abrir as frentes de maior diferencial contra concorrentes.

Entregas:

- revenda oficial verificada;
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

A recomendação é:

1. **sanear contexto e escopo** (documentação/README/memória, ainda pendente);
2. **fechar o núcleo operacional de ticketing** — feito;
3. **concluir bilheteria/acesso/assentos** — feito, com lacuna fina de supervisão para eventos de grande porte;
4. **entrar em financeiro, CRM e antifraude só depois do núcleo estar firme** — feito: financeiro (Fase 5), growth (Fase 6) e risco/antifraude (Fase 7) estão fechados;
5. **avançar para diferenciação de plataforma (Fase 8) e decisões de produto adiadas** — próximo passo real.

Se seguirmos essa ordem, o roadmap sai de “documento aspiracional” e vira um plano executável, com risco controlado e evolução coerente do produto.
