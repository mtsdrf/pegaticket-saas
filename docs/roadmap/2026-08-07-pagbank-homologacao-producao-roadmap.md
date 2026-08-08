# PegaTicket — Roadmap: Fase do Repasse, Homologação PagBank e Produção

Data de referência: **7 de agosto de 2026**. Atualizado em **8 de agosto de 2026** (seção 9) para incorporar a especificação funcional completa de "Configuração de Recebimentos" (onboarding financeiro do tenant) recebida do usuário nessa data.
Baseado em `.claude/skills/pagbank-integration.md` (regras obrigatórias de arquitetura financeira PagBank) e no estado real do código levantado nesta data (não greenfield — ver seção 1).

## 0. Como usar este documento

Este roadmap **não reabre** decisões já fechadas em `2026-08-03-modelo-de-repasse-aprovado.md` e `2026-08-04-fase-5-plano-fechamento-execucao.md`. Ele:

1. Confirma o que a skill exige e já está implementado (não refazer).
2. Lista, gap a gap, o que a skill exige e **não existe ainda** — é o escopo real desta fase.
3. Reorganiza a Etapa D/E do plano de 4/08 em torno do objetivo concreto de **homologação PagBank + produção**, que não estava explícito como meta anterior.
4. Fecha as 3 decisões de produto pendentes (seção 5), pois elas bloqueiam parte do código de repasse com PagBank real.
5. **(8/08)** Incorpora a especificação funcional completa de "Configuração de Recebimentos" — onboarding financeiro do tenant como subdomínio próprio (UX guiado, caminho Connect + caminho Account/Cadastro PF-PJ, máquina de estados expandida, elegibilidade centralizada, jobs de sincronização, observabilidade, homologação) — ver seção 9. Isso **substitui e expande** o escopo original da Fase R2 (seção 6), que cobriu só a metade Connect em backend.

## 1. Estado atual — o que já está pronto (não refazer)

Confirmado por leitura direta do código em 2026-08-07:

- **Split FIXED com custódia** — `PagBankPaymentProvider::buildSplitPayload()` (`api/app/Services/Payment/PagBankPaymentProvider.php:725`), com `configurations.custody` + `release.scheduled` calculado a partir do fim do evento (`resolveSplitReleaseAt`, linha 841).
- **Taxa de serviço PegaTicket (10%/mínimo R$3, configurável pelo admin)** — `TicketFeeCalculator`, snapshot imutável em `sale_items`/`sales`, `fee_payer` por evento, já integrado ao split (`sale.platform_fee_payer_snapshot === 'producer'` soma ao valor retido, linha 741).
- **Order (cartão + Pix)** — `createChargeForOrder()`.
- **Webhook com validação de assinatura + idempotência real** (`WebhookEvent`, `PaymentWebhookController::handlePagBank`, linha 230) — nunca confia no corpo do webhook, sempre reconsulta via `getPaymentForTenant()`.
- **Ledger append-only** (`ledger_entries`) com tipos já cobrindo venda, taxa, liberação, reserva, refund, chargeback, ajuste manual.
- **Reembolso parcial por ingresso** (`SaleRefundService`, `sale_refunds`/`sale_refund_tickets`) com efeito financeiro auditável (`SaleRefundFinancialAdjustmentService`).
- **Reconciliação** em 3 camadas (settlement, payment individual, integridade interna) + 6 comandos artisan de reprocessamento/reconciliação.
- **Painéis tenant e admin** (recebíveis, settlements, fila de exceções, fechamento por evento, borderô CSV) — já em UI, mesmo que parcial.

Isto cobre boa parte das seções 1-9, 17-23 e 32 da skill. O trabalho daqui pra frente é **fechar os gaps específicos que a skill exige e que faltam**, não reconstruir o motor financeiro.

## 2. Gaps reais contra a skill (escopo desta fase)

### 2.1 Onboarding financeiro do tenant / API Connect — skill §11-15 — **PARCIAL (backend Connect entregue em 2026-08-07)**

~~Hoje o tenant só preenche manualmente `pagbank_receiver_account_id`~~ — **atualizado**: a Fase R2 original (seção 6) entregou o backend do caminho Connect: migration `tenant_pagbank_connections`, model `TenantPagBankConnection`, `PagBankConnectService` (`buildAuthorizationUrl`/`handleCallback`/`refreshTokenIfNeeded`/`disconnect`), controller + rotas (`tenant-tools/pagbank-connect/{authorize-url,status}`, `POST disconnect`, `GET /pagbank-connect/callback` público), bloqueio de `EventService::publish()` sem conexão `enabled`. 873 testes passando, 0 regressão, verificado por leitura direta de código (state validado contra CSRF, tokens com cast `encrypted`, refresh rotativo, disconnect best-effort). O fluxo manual antigo (`pagbank_receiver_account_id` em `tenant_settings`) continua existindo em paralelo, não foi removido.

**O que ainda falta desse gap, per a especificação funcional completa da seção 9**: (a) caminho **Account/Cadastro** (criar SELLER novo quando o tenant não tem conta PagBank — PF/PJ) não foi implementado, só o caminho Connect (conta existente); (b) nenhuma UI/frontend — hoje é só API; (c) máquina de estados atual (7 estados) é mais enxuta que a que a especificação pede (13 estados, ver 9.4); (d) não existe `TenantReceivingEligibilityService` centralizado nem `connection_type` (`CONNECTED_EXISTING` vs `CREATED_BY_PLATFORM`); (e) sem jobs de sincronização, notificações, nem métricas dedicadas. Tudo isso vira as sub-fases R2.2 a R2.7 (seção 9.10).

Isso é bloqueante para homologação: o formulário PagBank (skill §45) pretende marcar `API Connect` e `API de Cadastro (Account)` como integradas — hoje só Connect é real, `API de Cadastro` ainda seria falso declarar.

### 2.2 Estado financeiro do tenant — skill §14 — **FECHADO em R2.3 (2026-08-08)**

`TenantPagBankConnection.status` já cobre `not_configured|pending_connection|pending_kyc|under_review|enabled|restricted|disabled` (os 7 estados literais da skill §14) mais os 6 do caminho Account/Cadastro (R2.2). `TenantReceivingEligibilityService` centraliza a leitura desse status (nenhum `if ($status === 'enabled')` espalhado) e é consultado tanto por `EventService::publish()` quanto por `PagBankPaymentProvider::resolveSplitSettings()`, que agora usa `TenantPagBankConnection.account_id` como fonte primária do split (fallback pro legado `tenant_settings.pagbank_receiver_account_id` quando não há conexão elegível) — "religar consumidores" concluído, ver detalhe em `.claude/memory/api-patterns.md` (entrada R2.3) e seção 9.10.

A especificação da seção 9 pede uma máquina mais rica (`STARTED`, `PENDING_SUBMISSION`, `SUBMITTED`, `VERIFIED`, `REJECTED`, `ERROR` adicionais — necessários principalmente para o caminho Account/Cadastro, que ainda não existe). Ver decisão de reconciliação em 9.4.

### 2.3 Chargeback no rail PagBank — skill §24-25 — **AUSENTE**

`registerExternalReview()` já existe e é usado pelo webhook do **Mercado Pago** (`handleChargeback`/`handleClaim`/`handleFraudAlert`). O ramo `handlePagBank()` do webhook **não trata chargeback** — não há evento de chargeback PagBank mapeado. Antes de implementar, confirmar na documentação vigente do PagBank: (a) se chargeback chega via webhook de `charge`/`order` com status específico ou via endpoint de Chargeback dedicado; (b) estrutura de `charge_transfer.percentage` (skill §24, "nunca adicionar esse campo baseado somente no documento — confirmar na doc vigente"). Só marcar `API de Chargeback` no formulário de homologação (§45) se isso for de fato implementado.

### 2.4 3 decisões de produto pendentes — bloqueiam código de recuperação — ver seção 5

### 2.5 reCAPTCHA / anti-bot no checkout — skill §31, §50 — **FECHADO em R4 (2026-08-08)**

Reaplicado o padrão já existente (`App\Services\Security\AntiBotGuardService` — honeypot + tempo mínimo de preenchimento + Cloudflare Turnstile, usado em hold/waitlist/convite) às 2 rotas públicas de cobrança: `POST /rastreio/{sale}/payment-charge` (`SaleTrackingController`) e `POST /portal/sales/{uuid}/payment-charge` (`PortalController`). Campos `website`/`form_rendered_at`/`turnstile_token` adicionados como opcionais em `App\Http\Requests\Sale\SalePaymentChargeRequest` (compartilhado pelas 3 rotas de charge); `assertHuman()` chamado no Controller (mesmo ponto de invocação do padrão existente), não na rota de staff (`SaleController::paymentCharge`, autenticado + `perm:sales,update` — anti-bot não se aplica a tráfego não-anônimo). Turnstile continua desabilitado automaticamente sem `TURNSTILE_SECRET_KEY` configurado. Testes em `tests/Feature/Security/PaymentChargeSecurityTest.php`.

### 2.6 Rate limiting / anti card-testing dedicado no checkout — skill §31 — **FECHADO em R4 (2026-08-08)**

Duas camadas, complementares ao `throttle:{max},{min}` fixo já existente nas 3 rotas: (1) `adaptive.throttle:3,60` (middleware já existente, `App\Http\Middleware\AdaptiveThrottleMiddleware`) adicionado às 2 rotas públicas — throttle agressivo extra só para IP já marcado suspeito pelo anti-bot; não aplicado à rota de staff (não faz sentido por IP para usuário autenticado). (2) `App\Services\Security\PaymentChargeAttemptLimiter` (novo, cache simples `payment-charge-attempts:{sale_uuid}`, TTL 10min, limite 5) aplicado às 3 rotas — protege a MESMA venda contra card-testing independente de IP/autenticação (um atacante rotaciona IP, não rotaciona a venda-alvo); conta só tentativas FRACASSADAS (`InvalidSaleStateException`/`PaymentOperationInProgressException`/`PaymentProviderException`), estourar o limite responde `429` via `abort()` (convertido pelo handler genérico `HttpException` de `bootstrap/app.php`, mesmo padrão de `AntiBotGuardService`). Mensagem `messages.security.payment_charge_attempts_exceeded`. Testes em `tests/Feature/Security/PaymentChargeSecurityTest.php` (429 nas 3 rotas após 5 tentativas fracassadas na mesma venda).

### 2.7 Máquina de estados de pagamento formal — skill §61 — **AUSENTE (string livre hoje)**

`payments.status` é `string(20)` livre (`pending|paid|failed|refunded|cancelled|requested|not_applicable`), sem enum PHP nem mapeamento explícito e documentado dos estados reais do PagBank (`AUTHORIZED`, `PAID`, `DECLINED`, etc.) para os estados internos. Não é bloqueante para homologação, mas é dívida técnica que a skill pede para fechar antes de considerar a integração "pronta" (§61, §65). Baixo risco de regressão para formalizar (criar enum, mapear, sem mudar valores gravados).

### 2.8 Idempotência fora do webhook — skill §17 — **PARCIAL**

Webhook já é idempotente. Falta confirmar: criação de `Order`/charge tem `payment_attempt_uuid` reaproveitado em retry por timeout (não apenas idempotência de settlement/reconciliação, que já existe via os comandos artisan). Verificar `createChargeForOrder()`/`buildSplitPayload()` quanto a isso especificamente — a skill é explícita: "nunca gerar nova chave automaticamente apenas porque ocorreu timeout".

### 2.9 Observabilidade — skill §54-55 — **FECHADO em R5 (2026-08-08)**

`sales.pagbank_fee_actual`/`pagbank_fee_actual_captured_at` (migration `2026_08_16_090000_add_pagbank_actual_fee_to_sales_table`) armazenam o custo real do PSP por venda, capturado em `PagBankPaymentProvider::callPagBank()` (resposta de criação) e em `PaymentWebhookController::handlePagBank()` (reconsulta pós-webhook) — imutável após a 1ª captura. **Pendência confirmada, não implementável agora**: a doc oficial do PagBank (`GET /orders/{id}`, `GET /charges/{id}`, confirmado via WebFetch em 2026-08-08) não expõe hoje nenhum campo de fee/tarifa/MDR — `PagBankPaymentProvider::ACTUAL_FEE_CANDIDATE_PATHS` fica com paths candidatos e `// TODO: confirmar nome do campo na doc oficial antes de produção`; `pagbank_fee_actual` continua `null` até o PagBank expor o dado ou até confirmação manual do campo certo. `Sale::platformNetRevenue()` calcula `platform_fee_total_amount - pagbank_fee_actual` (só quando disponível, nunca estimado) e `PagBankPaymentProvider::checkMarginAlert()` loga `pagbank.margin.non_positive_net_revenue` via `PagBankTransactionLogger::warning()` quando `<= 0` (skill §6, proteção de margem — sem canal externo nesta fase). Métricas nomeadas pela skill §54 instrumentadas via `PagBankTransactionLogger::metric()`: `pagbank_orders_total`/`pagbank_orders_failed`/`pagbank_payment_approved`/`pagbank_payment_declined` (`callPagBank()`), `pagbank_split_failed` (`resolveSplitSettings()`), `pagbank_webhooks_received`/`pagbank_webhooks_failed` (`PaymentWebhookController::handlePagBank()`), `pagbank_refunds` (`SalePaymentService::createRefundForPaidCancellation()`), `pagbank_reconciliation_divergences` (`ReconcilePagBankSalePaymentsCommand` + `ReconcileFinancialIntegrityCommand`). `pagbank_chargebacks` **sem ponto de instrumentação real** — chargeback PagBank é gap 2.3/Fase R3, ainda bloqueada, não confundir com o chargeback do rail Mercado Pago (que já existe e é de outro provider). Ver `.claude/memory/api-patterns.md` para o detalhamento completo.

### 2.10 Ambiente sandbox vs produção — skill §33, §53 — **PARCIAL**

`.env.example` já separa `PAGBANK_TOKEN_SANDBOX`/`PAGBANK_TOKEN_PROD` por `PAGBANK_ENVIRONMENT`. Falta confirmar (fora do escopo de leitura de código): domínio/TLS de produção, `webhook URL` de produção cadastrada no PagBank, e que nenhuma credencial de sandbox vaze para produção via `.env` do servidor (mesma classe de bug já visto nesta sessão com `APP_URL` — ver memória do projeto).

### 2.11 Pacote de homologação — skill §36-52 — **AUSENTE**

Não existe `docs/pagbank/homologacao/`. Criado o scaffold nesta entrega (seção 6 abaixo) — precisa ser preenchido com evidências reais de sandbox à medida que os cenários forem executados, não retroativamente no fim.

## 3. O que a skill pede e que **não muda o já decidido**

- Split FIXED (não percentual) — já é assim.
- Ledger append-only — já é assim (nenhum `UPDATE` de valor histórico encontrado; ajustes viram nova entrada).
- Nunca confiar em redirect do navegador como confirmação — já é assim (webhook sempre reconsulta).
- Snapshot financeiro imutável por venda — já é assim, e mais completo que o mínimo da skill §19 (tem `fee_rule_version`, falta só nomear formalmente os campos `pagbank_fee_estimated`/`pagbank_fee_actual` — hoje o custo PSP real não é armazenado por venda, só estimado no simulador; ver decisão pendente #1 na seção 5).

## 4. Fluxo consolidado (mapeado ao pipeline da skill §1)

```
Tenant → Conta PagBank (2.1, GAP) → Evento → Ingresso (pronto) → Pedido (pronto)
  → Checkout (pronto, falta 2.5/2.6) → Pagamento (pronto) → Split (pronto)
  → Liberação/Custódia (pronto) → Webhook (pronto) → Conciliação (pronto)
  → Cancelamento (pronto) / Chargeback (2.3, GAP) → Relatórios (pronto)
```

O "repasse" em si (Split + Custódia + liberação + reconciliação) **já está implementado e é o núcleo mais maduro do sistema**. O que falta para chamar a fase de repasse de concluída e abrir homologação é fechar as pontas: onboarding do tenant (2.1/2.2), chargeback no rail certo (2.3), ~~segurança de checkout (2.5/2.6)~~ (fechado em R4, 2026-08-08) e o pacote de evidências (2.11).

## 5. Fechando as 3 decisões pendentes (herdadas de `2026-08-04-fase-5-plano-fechamento-execucao.md` §8)

A skill (§6, proteção de margem) força fechar isso agora, porque sem isso não dá para implementar alertas de `platform_net_revenue <= 0` nem decidir o payload de chargeback.

1. **Quem absorve custo PSP por padrão** → adotar a recomendação já registrada em 4/08: plataforma rastreia (`pagbank_fee_actual` por venda, alimentado pelo retorno real do PagBank, não só a estimativa do simulador), mas **não redistribui automaticamente** ainda. Isso é suficiente para a skill §6 (permite calcular `platform_net_revenue` e alertar) sem exigir mudança de contrato comercial com tenants agora.
2. **Quem absorve refund/chargeback por padrão** → manter a recomendação de 4/08: consome primeiro o líquido do organizador ainda não liberado; excedente vira exposição da plataforma em revisão (`pending_review`). Já é o comportamento implementado para refund (`SaleRefundFinancialAdjustmentService`) — só falta espelhar para chargeback PagBank (gap 2.3).
3. **Fechamento por evento vs consolidado por tenant** → manter por evento como padrão (já implementado), consolidado por tenant fica fora de escopo desta fase.

Estas são recomendações operacionais para não bloquear execução, replicando o que o documento de 4/08 já sugeria — sinalizando explicitamente que são decisões comerciais, não só técnicas (skill §25: "não resolver política comercial apenas por código").

## 6. Plano de execução (fases, em ordem)

### Fase R1 — Fechar retaguarda financeira já mapeada (retomar Etapa A/B/D de 4/08, itens ainda parciais)
Sem gap novo da skill aqui — é continuação do que já estava em andamento: enriquecer catálogo de `pending_recovery`/`pending_review`, ampliar reconciliação interna para `payments`/`refunds`/`sale_refunds`, consolidar painéis.

### Fase R2 — Onboarding financeiro do tenant (gap 2.1 + 2.2)

**R2.1 — CONCLUÍDA em 2026-08-07** (verificada, 873 testes, 0 regressão): migration `tenant_pagbank_connections`, `PagBankConnectService` (autorização, callback, refresh, disconnect), controller + rotas, bloqueio de `EventService::publish()` sem conexão `enabled`. Decisão de produto já validada com o usuário: publicação bloqueada, não só o checkout.

**R2.2 em diante** — expandidas e detalhadas na especificação funcional completa da seção 9 (recebida em 2026-08-08): Account/Cadastro PF-PJ, eligibility service, frontend de onboarding, jobs/notificações/observabilidade, snapshot por venda. Ver plano de sub-fases em 9.10 — substitui os itens 3/4 originais deste bloco (que ficam superados pelo detalhamento da seção 9).

### Fase R3 — Chargeback no rail PagBank (gap 2.3)
1. Consultar documentação oficial PagBank vigente antes de qualquer código (skill §8, mandatório).
2. Mapear evento de chargeback PagBank para `registerExternalReview()` (reaproveitar o domínio `Refund` já existente, mesmo padrão do Mercado Pago).
3. Testes: chargeback antes do repasse, chargeback depois do repasse (já listados no plano de 4/08, Etapa D).

### Fase R4 — Segurança de checkout para homologação (gaps 2.5, 2.6) — **CONCLUÍDA em 2026-08-08**
1. ~~reCAPTCHA (ou equivalente) no checkout de cartão.~~ Feito — Cloudflare Turnstile (`AntiBotGuardService`, já existente no projeto) reaplicado às 2 rotas públicas (rastreio, portal).
2. ~~Rate limiting dedicado + proteção contra card testing nas 3 rotas de charge.~~ Feito — `adaptive.throttle:3,60` nas 2 rotas públicas + `PaymentChargeAttemptLimiter` (por `sale_uuid`, 5 tentativas fracassadas/10min) nas 3 rotas.
3. Confirmado por leitura direta: `PagBankTransactionLogger::sanitize()` mascara `card.encrypted`/`card.security_code`/`holder.tax_id`/`customer.tax_id`/`customer.email`/`customer.phones` em toda chamada de log; nenhum `Log::`/`ApplicationLogger::` direto (sem sanitização) em `PagBankPaymentProvider`; `PaymentWebhookController` nunca loga `Authorization`/header de assinatura, só o payload do webhook (que o próprio PSP já não inclui PAN/CVV). Nenhum vazamento encontrado — nenhuma mudança necessária.

Suíte completa: 939 testes passando antes, sem regressão depois (9 testes novos em `tests/Feature/Security/PaymentChargeSecurityTest.php`).

### Fase R5 — Proteção de margem e observabilidade (gaps 2.9, e o custo PSP real de 2.1 da decisão #1) — **CONCLUÍDA em 2026-08-08**
1. ~~Armazenar `pagbank_fee_actual` por venda quando disponível na resposta do PagBank.~~ Feito — estrutura pronta (colunas + captura nos 2 pontos de entrada), mas o valor fica `null` até a doc oficial confirmar o nome do campo (não exposto hoje em `GET /orders/{id}`/`GET /charges/{id}`, confirmado via WebFetch).
2. ~~Calcular `platform_net_revenue = platform_fee - gateway_fee` e alerta quando `<= 0`.~~ Feito — `Sale::platformNetRevenue()` + `PagBankPaymentProvider::checkMarginAlert()`.
3. ~~Métricas nomeadas pela skill §54.~~ Feito — todas exceto `pagbank_chargebacks` (sem gatilho real possível antes de R3 existir).

Suíte completa: 948 testes antes, 954 depois (0 regressão — 10 testes novos: `PagBankSalePaymentTest.php` + `tests/Feature/Console/ReconcilePagBankSalePaymentsCommandTest.php`, novo arquivo).

### Fase R6 — Formalizar máquina de estados de pagamento (gap 2.7)
Baixo risco, pode ser feito em paralelo a qualquer fase acima. Enum PHP + mapeamento documentado, sem alterar valores já gravados.

### Fase R7 — Sandbox completo + pacote de homologação (gaps 2.10, 2.11)
1. Executar todos os cenários obrigatórios da skill §34 em sandbox.
2. Preencher `docs/pagbank/homologacao/` (scaffold criado nesta entrega, seção 7) com request/response reais sanitizados por cenário.
3. Preencher `homologacao-formulario.md` com os dados reais (skill §42-49) — campos que dependem de informação da empresa ficam como placeholder `[PENDENTE — preencher com o usuário]`, nunca inventados.
4. Rodar o checklist final da skill §63 antes de abrir o chamado.

### Fase R8 — Produção
Seguir exatamente o checklist da skill §53 (credenciais, public key, Connect application, seller accounts, webhook URL, TLS, domínio, recaptcha, rate limits, idempotência, logs, observabilidade, alertas, conciliação, chargeback, cancelamento, split) — nenhum item pulado, confirmado um a um antes do primeiro pedido real em produção.

## 7. Scaffold de homologação criado nesta entrega

```
docs/pagbank/homologacao/
  00-checklist.md   ← checklist vivo (skill §63), marcar conforme cada fase acima avança
```

**Atualização 8/08**: a especificação da seção 9 (§93-96 dela) pede uma estrutura por serviço (`connect/`, `account/`, `order/`, `split/`, `webhooks/`, `requests/`, `responses/`, `README.md`, `checklist.md`) em vez de pastas numeradas (`01-order/`, `02-split/`...). Adotar essa nomenclatura por serviço quando os subdiretórios forem criados na Fase R7 (superam a numeração `01-`/`02-` mencionada anteriormente neste documento, que fica descartada) — `00-checklist.md` já criado continua sendo o checklist vivo consolidado; pode conviver com um `checklist.md` específico de PF-PJ/Account se a Fase R2.7 (seção 9.10) achar necessário separar. Pastas vazias sem conteúdo real não agregam — só criar quando houver evidência real de sandbox para colocar.

## 8. O que fica fora de escopo (herdado + skill)

- Multi-gateway (skill implícito, e já decidido em 4/08: "não abrir múltiplos gateways agora").
- Antifraude enterprise, CRM/growth, ERP completo — já descartado em 4/08.
- `API transferência`/`API PIX dedicada` no formulário de homologação — a skill é explícita que usar Split/Pix-via-Order não justifica marcar essas caixas (§10, §45).
- Liable e 3DS — skill §29-30 pedem para não assumir necessidade; só entram em escopo se surgir exigência real de redução de fraude/chargeback observada em produção.
- Custódia habilitada automaticamente para todo tenant — skill §28 é explícita que não deve ser automática; manter critério de risco (tenant novo, evento de alto risco, volume elevado) — hoje parece estar ativada globalmente (`split_custody_enabled` em `platform_finance_settings`), **validar se isso é intencional ou se deveria ser por tenant/risco** — sinalizado aqui, não decidido unilateralmente.

## 9. Especificação funcional completa — "Configuração de Recebimentos" (recebida 2026-08-08)

Esta seção incorpora, reconcilia e substitui o detalhamento anterior da Fase R2 (seções 2.1/2.2/6). Trata a Fase R2 como um **subdomínio financeiro do tenant** (elegibilidade para vender pago), não como formulário de cadastro. R2.1 (Connect backend) já está entregue e é a base sobre a qual R2.2+ constrói.

### 9.1 Objetivo e o que muda em relação ao entregue em R2.1

R2.1 entregou só o caminho **Connect** (tenant já tem conta PagBank) em backend, sem UI. Esta especificação exige também:
- caminho **Account/Cadastro** (tenant sem conta PagBank, cria SELLER novo, PF ou PJ);
- frontend completo (wizard de onboarding, telas de status, retomada);
- serviço de elegibilidade centralizado, não condição espalhada;
- jobs de sincronização + notificações + observabilidade dedicada;
- domínio pensado como abstração provider-agnostic (`ReceivingAccount`/`Receiver`), com PagBank como a única implementação de infraestrutura hoje.

### 9.2 Modelo de negócio (reafirmado, sem mudança)

```
COMPRADOR → PAGBANK → Split → TENANT (produtor) + PEGATICKET (plataforma)
```

Nunca o modelo "PegaTicket recebe tudo e repassa depois por Pix" — já era a decisão vigente (skill §2, seção 3 deste roadmap).

### 9.3 Regra de bloqueio (reafirma decisão já validada com o usuário)

Não bloqueado: criação/edição de tenant, evento (pago ou gratuito), ingresso, lote, checkout, evento 100% gratuito.
Bloqueado: **publicação/venda de evento com ao menos um ingresso pago** quando `receiving_status != ENABLED` (já implementado em R2.1 via `EventService::publish()`; mudança de gratuito→pago em evento já publicado precisa do mesmo gate na hora de tornar o ingresso pago vendável — **gap novo**, não coberto por R2.1, entra em R2.3).

### 9.4 Reconciliação da máquina de estados

R2.1 entregou (`TenantPagBankConnection::STATUS_*`): `not_configured, pending_connection, pending_kyc, under_review, enabled, restricted, disabled`.

A especificação pede também: `started, pending_submission, submitted, verified, rejected, error`.

**Decisão de modelagem** (técnica, não comercial — resolvida aqui para não travar R2.2): os estados novos são majoritariamente do **caminho Account/Cadastro** (submissão de formulário PF/PJ, aprovação), que ainda não existe. Em vez de forçar os 13 estados na mesma coluna hoje usada só por Connect, **estender o enum existente** de `TenantPagBankConnection.status` com os 6 estados novos (mesma tabela, mesma coluna — não criar tabela paralela) quando R2.2 (Account) for implementada, e usar `connection_type` (`connected_existing` | `created_by_platform`, novo campo, gap 9.5) para diferenciar qual subconjunto de estados um registro específico deve transitar. Reaproveita a tabela/model/eligibility service já testados de R2.1 em vez de duplicar infraestrutura — alinhado com §105 da especificação ("não overengineer", "arquitetura deve evitar impedir [múltiplos providers], mas generalizar agora não é requisito").

### 9.5 Gaps novos identificados (não cobertos por R2.1)

1. **`connection_type`** (`connected_existing`/`created_by_platform`) — coluna nova em `tenant_pagbank_connections`, ausente hoje.
2. **Caminho Account/Cadastro completo**: Form Requests PF/PJ, normalização de CPF/CNPJ/telefone/CEP (reaproveitar componente/helper de endereço já existente no projeto — **verificar antes de criar novo**), chamada à API de Cadastro do PagBank (`type=SELLER`), idempotência de criação (double-submit não pode gerar 2 contas).
3. **`TenantReceivingEligibilityService`** (ou nome equivalente ao padrão do projeto) — `canReceivePayments()`, `canPublishPaidEvents()`, `needsReceivingSetup()`, `hasFinancialRestriction()` centralizados; hoje a única checagem existente está inline em `EventService::publish()` (`hasEnabledPagBankConnection()`) — **extrair para serviço de domínio reutilizável** antes de espalhar a mesma condição em checkout/Order/Split quando esses pontos forem religados (gap 2.2, `resolveSplitSettings()` ainda não consulta o novo status).
4. **Frontend inteiro** — nenhum componente existe: `ReceivingSetupCard`, `ReceivingSetupWizard`, `ReceivingStatusBadge`, `PagBankConnectStep`, `PagBankAccountTypeStep`, `PagBankPersonForm`/`PagBankBusinessForm`, `PagBankAddressForm`, `ReceivingReviewStep`, `ReceivingSuccessState`. Ver textos de UX oficiais em 9.6.
5. **Job de sincronização** (`SyncPagBankTenantAccountJob` ou equivalente) — hoje o status só muda via callback do Connect; não há reconciliação periódica/consulta pós-conexão para mover `pending_kyc`→`enabled` automaticamente.
6. **Notificações** de mudança de status (habilitado / pendência) — não existem hoje.
7. **Métricas dedicadas** (seção 9.9) — distintas das métricas gerais de pagamento já mapeadas no gap 2.9.
8. **Snapshot por venda do receptor usado** (`tenant_receiver_provider`/`tenant_receiver_account_id` no momento da venda) — hoje `buildSplitPayload()` resolve o destino do split em tempo real a partir da config atual, não grava snapshot; é o mesmo princípio já aplicado à taxa de serviço (`platform_fee_*_snapshot` em `sale_items`) — replicar o padrão quando o Split passar a usar `TenantPagBankConnection` como fonte (hoje ainda usa `tenant_settings.pagbank_receiver_account_id`, gap 2.2).
9. **Permissões dedicadas**: R2.1 reaproveitou `perm:tenant_settings,{read,update}`. A especificação (§5) pede permissão própria — avaliar em R2.2 se `tenant_settings` continua adequada (dado que agora cobre onboarding financeiro completo, não só configuração simples) ou se compensa criar `Functionality` própria (`financial_receiving` com ações `read`/`manage`) seguindo o padrão de permissão via Group já existente no projeto — **decisão técnica pequena, resolver no início de R2.2, não bloqueia o resto**.

### 9.6 Textos de UX oficiais (preservar literalmente na implementação frontend)

CTA quando não configurado: *"Configure seus recebimentos — Para vender ingressos pagos, precisamos configurar a conta que receberá suas vendas. [Configurar agora]"*.

Escolha de caminho: *"Se você já possui uma conta PagBank, poderá conectá-la. Caso ainda não possua, ajudaremos você a criar sua conta de recebimento."* — nunca expor termos técnicos (`Split`, `seller`, `secondary receiver`, `account.id`, `OAuth`, `API Account`) ao usuário final.

Estados visuais (status badge/página "Recebimentos"): não configurado, em andamento ("Continuar configuração" retoma exatamente a etapa pendente, nunca reinicia), em análise (nunca prometer prazo não garantido pelo PagBank), habilitado, pendência, restrito. Mensagens completas na especificação original (seções 28-33 do prompt do usuário) — reproduzir literalmente na implementação frontend, não parafrasear.

Erro de publicação de evento pago sem conta habilitada, formato de API: `{"success": false, "code": "RECEIVING_ACCOUNT_REQUIRED", "message": "Configure seus recebimentos para publicar eventos pagos."}` — adaptar ao envelope real de `APIResponse::error` já usado no projeto (não criar formato paralelo); hoje R2.1 já retorna 422 com `messages.pagbank_connect.account_not_enabled` — **avaliar se o `code` da resposta de erro atual já é semanticamente equivalente a `RECEIVING_ACCOUNT_REQUIRED` ou se precisa ajuste no controller/exception para expor esse código específico**.

### 9.7 Segurança — itens que já valem hoje (confirmados em R2.1) e o que falta

Já confirmado em R2.1 (revisão de código feita em 2026-08-07): `state` nunca é `tenant_id` puro, é `Str::random(64)` correlacionado via tabela; `state` de uso único (`findPendingByState` filtra por `status=pending_connection`, é limpo após uso); tokens nunca em texto puro (cast `encrypted`) nem retornados pela API (`$hidden`); callback nunca retorna JSON/token ao frontend; tenant sempre resolvido via `app('tenant_id')`, nunca do body.

Falta, per checklist §98 da especificação: teste anti-IDOR explícito (tenant A tentando ler/alterar conexão de tenant B — hoje a proteção existe por design de query (`findForTenant(tenantId)` sempre escopado), mas não há teste dedicado provando isso); rate limit já existe nas rotas tenant-scoped e no callback público (`throttle` configurado), mas falta confirmar proteção contra duplo-submit no futuro endpoint de criação de conta Account (R2.2, ainda não existe); mascaramento de documento (CPF/CNPJ) na resposta da API (`TenantPagBankConnectionResource` hoje não expõe esse dado porque Account ainda não existe — vira requisito quando R2.2 for implementada).

### 9.8 Separação de responsabilidades para R2.2+ (Account/Cadastro)

Seguir o mesmo padrão já usado em R2.1: Controller fino → Service → Repository, DTO de entrada. Não criar uma `PagBankService` monolítica. Sugestão de nomenclatura alinhada ao que já existe (`PagBankConnectService` já criado): `PagBankAccountService` (chamadas à API de Cadastro), reaproveitando o mesmo HTTP client/resolução de ambiente já centralizado em `PagBankEnvironment` (`app/Support/PagBank/`, criado em R2.1) em vez de duplicar constantes de BASE_URL de novo.

### 9.9 Observabilidade específica deste subdomínio

Distinta das métricas de pagamento gerais (gap 2.9): `pagbank_receiving_setup_started_total`, `pagbank_connect_started_total`, `pagbank_connect_success_total`, `pagbank_connect_failed_total`, `pagbank_accounts_created_total`, `pagbank_account_creation_failed_total`, `pagbank_account_status_sync_failed_total`, `tenant_receiving_enabled_total`, `tenant_receiving_restricted_total`. Adaptar ao mecanismo de observabilidade que o projeto já tiver (não introduzir ferramenta nova só para isso).

### 9.10 Plano de sub-fases (substitui os itens 3/4 do bloco "Fase R2" original na seção 6)

- **R2.1 — CONCLUÍDA (2026-08-07)**: backend Connect, ver seção 2.1.
- **R2.2 — Account/Cadastro PF-PJ (backend)**: consultar documentação oficial PagBank vigente (objeto Account, criar conta, tipos aceitos) antes de codar formulário — não assumir campos deste documento como definitivos (skill §8, especificação §16/§97); `connection_type`; Form Requests PF/PJ; normalização; idempotência de criação; `PagBankAccountService`.
- **R2.3 — CONCLUÍDA (2026-08-08)**: `TenantReceivingEligibilityService` extraído (`canReceivePayments`/`canPublishPaidEvents`/`needsReceivingSetup`/`hasFinancialRestriction`, `enabled`+`verified` tratados como elegíveis, decisão documentada no código); `EventService::publish()` e `PagBankPaymentProvider::resolveSplitSettings()` religados para essa fonte única (split usa `TenantPagBankConnection.account_id` como primário, fallback legado preservado); gate de "evento gratuito virando pago" implementado em `TicketTypeService` (item 9.3); permissão dedicada `financial_receiving` criada e aplicada nas 4 rotas `tenant-tools/pagbank-connect/*` (item 9.5.9), substituindo `tenant_settings,{read,update}`. 28 testes novos (914 passando, baseline 886, 0 regressão). Ver `.claude/memory/api-patterns.md` para detalhe completo.
- **R2.4 — CONCLUÍDA (2026-08-08)**: frontend de onboarding completo (React 19 + MUI). Rota `/configuracoes/pagbank-connect` (`ReceivingSetupPage`), wizard do caminho Account/Cadastro (`ReceivingSetupWizard` + `PagBankConnectStep`/`PagBankAccountTypeStep`/`PagBankPersonForm`/`PagBankBusinessForm`/`PagBankAddressForm`/`ReceivingReviewStep`/`ReceivingSuccessState` em `web/src/components/receiving/`), card reutilizável de entrada (`ReceivingSetupCard`, self-contido, não integrado em outros pontos de entrada ainda), badge de status (`ReceivingStatusBadge`, agrupa os 13 status em 6 grupos visuais). Textos oficiais da seção 9.6 preservados literalmente. Permissão dedicada `financial_receiving` (criada em R2.3) aplicada no frontend via `ACCESS.financialReceivingRead/Update`. Limitação documentada: sem retomada de formulário parcial (backend não persiste rascunho) — status "em andamento" reabre o wizard do zero, não é regressão. Ver `.claude/memory/api-patterns.md` (entrada R2.4) para detalhe completo de arquivos/decisões.
- **R2.5 — Jobs, notificações, observabilidade**: `SyncPagBankTenantAccountJob`, notificações de mudança de status, métricas da seção 9.9.
- **R2.6 — Snapshot por venda**: gravar receptor usado por venda quando o Split passar a consultar `TenantPagBankConnection` (depende de R2.3).
- **R2.7 — Evidências de homologação de Connect + Account**: **SCAFFOLD CRIADO em 2026-08-08, BLOQUEADO por falta de credenciais de sandbox reais.** Criados `docs/pagbank/homologacao/README.md`, `connect/README.md`, `account/README.md` — descrevem os cenários exatos a capturar (autorização/token/refresh/revoke para Connect; criar PF/criar PJ/consultar conta para Account), o formato de cada arquivo de evidência (skill §39) e a regra de sanitização (skill §40). **Nenhum arquivo é uma captura real** — a skill exige explicitamente evidência real, nunca inventada (§39-40, §51), e este ambiente não tem `PAGBANK_TOKEN_SANDBOX`/`PAGBANK_CONNECT_CLIENT_ID`/`PAGBANK_CONNECT_CLIENT_SECRET` configurados. Usuário confirmou em 2026-08-08 que não tem essas credenciais ainda — quando tiver, seguir o procedimento documentado em `docs/pagbank/homologacao/README.md` (rodar o fluxo real, capturar, sanitizar, substituir os templates, marcar os itens em `00-checklist.md`). Esta sub-fase permanece **não concluída** até isso acontecer — não é um item que o código sozinho resolve.

Ordem recomendada: R2.2 → R2.3 → R2.4 → R2.5 → R2.6 → R2.7, com R2.3 podendo começar em paralelo a R2.2 assim que `connection_type` existir (schema mínimo). Cada sub-fase deve fechar com bateria de testes própria (unitário de máquina de estados/elegibilidade, feature de API, anti-IDOR) antes de avançar para a próxima — não acumular dívida de teste entre sub-fases.

### 9.11 Formato de relatório de entrega exigido para cada sub-fase

Ao final de cada sub-fase (R2.2 em diante), o relatório de entrega deve conter, no mínimo: resumo do que foi implementado; arquivos criados/alterados; migrations; models/enums; Services/Actions/Adapters; endpoints; permissões; componentes frontend (quando aplicável); estados suportados; jobs; Events/Listeners; testes criados e cenários cobertos; pendências de configuração externa PagBank (mesmo padrão do `TODO` já deixado em `PagBankConnectService::client()` sobre o "token da aplicação"); variáveis de ambiente novas; itens do checklist de homologação (`docs/pagbank/homologacao/00-checklist.md`) que passaram a poder ser marcados. Não aceitar "implementado com sucesso" sem essa lista — mesmo padrão de verificação independente já aplicado em R2.1 (nunca aceitar autorrelato de agente sem checar `git status`/rodar suíte/ler o código).
