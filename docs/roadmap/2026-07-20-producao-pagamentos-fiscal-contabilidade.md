# PegaTicket — Roadmap Estratégico: Produção, Pagamentos, Fiscal e Contabilidade

> Documento de arquitetura e planejamento. **Não é implementação.** Levantado em 2026-07-20 a partir da leitura do código real de `api/` e `web/`, dos arquivos de memória do projeto e do `CLAUDE.md`.
>
> Restrição-guia de todas as recomendações: **orçamento baixo**. Prioriza-se sempre construir nativo, contratando terceiros só onde é técnica ou **legalmente** inevitável (processamento de cartão/Pix regulado pelo BACEN; comunicação com SEFAZ/prefeitura para documento fiscal). Todo o levantamento segue a legislação brasileira aplicável e trata LGPD com seriedade em qualquer ponto com dado pessoal, bancário ou financeiro.
>
> Onde a lei é ambígua ou depende de enquadramento específico, o ponto está marcado como **[requer validação jurídica]** em vez de afirmação categórica.

---

## Sumário executivo

O PegaTicket hoje é um SaaS multi-tenant de gestão comercial **funcionalmente maduro no núcleo operacional** (clientes, produtos, estoque, pedidos, crediário/parcelas, relatórios/analytics, portal do cliente final, delivery, cupons, cashback) mas **comercialmente e financeiramente "cru"**: os planos Prata/Ouro/Diamante existem apenas como **gate de funcionalidades** — não têm preço, ciclo de cobrança, fatura nem qualquer cobrança recorrente real. O cadastro self-service coloca o tenant em "trial", mas **nada expira e nada cobra**. Pagamento de pedido é baixa manual; não há gateway em lugar nenhum do sistema.

Consequência estratégica: **o produto não consegue faturar sozinho ainda.** As quatro grandes frentes pedidas (cobrança de planos, pagamento de pedidos, documento fiscal e módulo do contador) têm uma ordem natural de dependência forte, e tentar fazer tudo em paralelo com orçamento baixo é o principal risco do projeto.

Recomendação de sequência macro (detalhada no roadmap final):

1. **Endurecimento de produção** (segurança, observabilidade, LGPD, backup/DR) — bloqueante para cobrar qualquer centavo de cliente real. Esforço **M**.
2. **Cobrança de planos via Pix** (a PegaTicket cobrando os tenants) — menor superfície regulatória, Pix tem custo quase zero. Habilita o negócio a existir. Esforço **M/G**.
3. **Pagamento de pedidos via Pix** (cliente final pagando o tenant) — reaproveita a infra de Pix do item 2, mas exige decisão de arquitetura de recebimento (conta por tenant vs. marketplace). Esforço **G**.
4. **Documento fiscal (NF-e/NFC-e/NFS-e)** — a área mais complexa, arriscada e cara em esforço de todo o roadmap. NFS-e (para a plataforma emitir nota dos planos) primeiro; NF-e/NFC-e dos tenants depois. Esforço **GG**.
5. **Módulo do contador** — depende de dados fiscais e financeiros existirem para ter o que entregar. Esforço **G**.

Cartão de crédito e emissão fiscal são os dois pontos onde **não existe caminho 100% nativo legal**: cartão exige um PSP regulado; NF-e/NFC-e/NFS-e exigem comunicação homologada com SEFAZ/prefeitura via certificado digital. Em ambos, o documento indica a opção mais barata (idealmente sem mensalidade fixa, custo por transação/emissão) e justifica por que não dá para contornar.

---

## 0. Estado atual do sistema (linha de base verificada)

Levantado lendo o código, não presumido:

**Arquitetura e maturidade**
- Laravel 13 (`api/`) + React 19/Vite (`web/`), multi-tenant, JWT com refresh e troca de tenant, 84 migrations, 97 arquivos de teste (Feature + Unit cobrindo Auth, Orders, Stock, Reports, Permissions, Tenant, Portal, etc.).
- Autorização em camadas já robusta: JWT → tenant ativo → vínculo `TenantUser`/`TenantRole` → `perm` (GroupPermission **ou** TenantRolePermission) → **gate por plano** (`PLAN_UPGRADE_REQUIRED`). Ver `.claude/memory/access-model.md`.
- Auditoria: `AuditLog` gravado por Event/Listener em toda mutação de domínio. Base sólida para conformidade e para o módulo contábil.
- CI/CD **já existe**: `.github/workflows/deploy.yml`, push em `main` → **self-hosted runner** (necessário porque a Hostinger bloqueia o IP dos runners de nuvem do GitHub). Deploy não é mais "SSH manual puro".

**Planos / monetização (o gap central)**
- `plans` (id, uuid, name, slug, description, sort_order, is_active) + `plan_functionalities` (pivot) + `tenants.plan_id`. **O model `Plan` NÃO tem preço, moeda, ciclo nem trial.** É exclusivamente feature-gating.
- Signup self-service (`POST /auth/signup`) cria owner + tenant "em teste", mas **não há entidade de assinatura, fatura, ciclo, nem expiração de trial efetiva**. A própria memória registra "ativação de trial e billing real" como pendência não feita.

**Pagamento de pedidos**
- `orders` tem `is_paid`, `paid_amount`, `paid_at`, `due_date`, `is_installment` + `order_installments`. Baixa é **manual** (`pay`, `payInstallment`). `tenant_settings.accepted_payment_methods` é só uma lista (cash/pix/credit_card/debit_card) exibida no catálogo público — **nenhum processamento real de pagamento existe.**

**Infra e conformidade**
- Hospedagem: Hostinger compartilhada (memória: desativa `symlink()`/`exec()` no PHP). Queue driver = `database`.
- **Sem** ferramenta de monitoramento/erro (nenhum Sentry/Bugsnag/Flare no `composer.json`/`package.json`).
- **Sem** Termos de Uso, Política de Privacidade ou aviso LGPD no produto (só menção em `docs/self-service-tenant-onboarding.md` como pendência).
- **Sem** nada fiscal e **sem** módulo de contador.

---

## Seção 1 — O que falta para produção real com clientes pagantes

Checklist crítico e realista. Classificação: **P0** = bloqueia cobrar cliente real; **P1** = risco alto pós-lançamento; **P2** = importante mas não bloqueante.

### 1.1 Segurança (P0 majoritário)

| Item | Estado | Prioridade | Ação |
|---|---|---|---|
| HTTPS/TLS obrigatório | Presumido via Hostinger, **não verificado** forçamento | P0 | Confirmar redirect 301 http→https e HSTS; certificado válido (Let's Encrypt grátis já disponível na Hostinger). |
| Secrets fora do repo | `.env` fora do git (ok), mas sem cofre | P0 | Garantir `.env` de produção nunca versionado; rotacionar `APP_KEY`/`JWT_SECRET` se algum já foi exposto em histórico. Certificados/tokens de pagamento e fiscal (futuros) **exigem** criptografia em repouso, nunca em `.env` texto plano nem em log. |
| Rate limiting | Existe (`throttle:` em rotas) | P1 | Revisar limites de endpoints sensíveis: login, OTP do portal, signup público, e (futuro) webhooks de pagamento. Login/OTP/signup são alvo de brute-force e abuso. |
| Headers de segurança | Não verificado | P1 | Adicionar CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy (via middleware ou `.htaccess`). |
| CORS | Configurado (`config/cors.php`) | ok | Manter `CORS_ALLOWED_ORIGINS` explícito, nunca `*` em produção. |
| Proteção de brute-force no OTP do portal | OTP de 6 dígitos por e-mail | P0 | 6 dígitos = 1M combinações. Exigir rate limit agressivo por e-mail+IP, expiração curta, e trava após N tentativas. Verificar se já está implementado; se não, é P0. |
| Exclusão física de dado financeiro/fiscal | `BaseModel` usa SoftDeletes (bom) | ok/P1 | Garantir que nada financeiro/fiscal futuro use `forceDelete`. Registros fiscais têm retenção legal (ver 1.5). |

### 1.2 Observabilidade (P0/P1)

- **Monitoramento de erro: ausente. Isto é P0 para cobrar cliente.** Sem isso, uma falha em cobrança/pagamento passa despercebida. Recomendação low-cost: **Sentry (free tier: 5k erros/mês, 1 projeto)** ou, 100% nativo/grátis, **canal de log estruturado + alerta**. Como a Hostinger compartilhada limita daemons, a opção nativa realista é: `LOG_CHANNEL` para arquivo rotacionado + um job que varre `ERROR/CRITICAL` e dispara e-mail/WhatsApp ao admin. Sentry free tier é mais barato em esforço e recomendado para o MVP.
- **Logs**: já existe `ApplicationLogger`/auditoria. Garantir rotação (Hostinger não roda logrotate do sistema por você — usar `daily` driver do Monolog com retenção).
- **Alertas**: mínimo viável = falha de job de cobrança, webhook de pagamento rejeitado, e erro 5xx em pico. 
- **Uptime**: monitor externo grátis (UptimeRobot free: 50 monitores) apontando para um endpoint `/health`. **Endpoint `/health` provavelmente não existe — criar (P1).**

### 1.3 Performance e escala (P1)

- Índices compostos já foram adicionados nas listagens pesadas (memória confirma `clients`/`orders`). Bom.
- Queue em `database` driver: funciona em shared hosting, mas **precisa de um worker rodando**. Em Hostinger compartilhada não há `supervisor`; a prática é **cron chamando `queue:work --stop-when-empty`** a cada minuto. **Verificar se esse cron existe** — hoje há dependência não confirmada de `schedule:run` para o `cashback:process` (memória registra isso como pendência real). **Sem cron de schedule + queue, jobs assíncronos não rodam em produção. P1 (P0 quando houver cobrança dependente de job).**
- Teste de carga: não crítico no volume inicial de poucos tenants, mas definir um teto conhecido (ex.: quando passar de ~50 tenants ativos, reavaliar sair de shared hosting).

### 1.4 Testes (P1)

- 97 arquivos de teste é uma base **boa** para o núcleo. Lacunas a cobrir **antes** de cada frente nova de dinheiro:
  - Isolamento multi-tenant sob as novas tabelas financeiras (teste que tenta acessar assinatura/fatura de outro tenant e **deve** falhar).
  - Idempotência de webhook (quando existir).
  - Máquina de estados de assinatura e de pagamento (transições inválidas devem ser rejeitadas).
- Não há E2E/Playwright no ambiente (memória confirma). Aceitável no MVP; validar fluxos de dinheiro com teste Feature de API é suficiente e mais barato.

### 1.5 Conformidade legal e LGPD (P0)

- **Termos de Uso + Política de Privacidade: ausentes. P0 para vender.** Um SaaS que trata dados de clientes finais dos tenants precisa disso. Redação **[requer validação jurídica]**, mas o produto precisa: (a) exibir e registrar aceite no signup (com data/hora/IP), (b) versionar os termos.
- **Papéis LGPD**: a PegaTicket é **operadora** dos dados que o tenant cadastra sobre os clientes finais dele (o tenant é o controlador), e **controladora** dos dados de cadastro do próprio tenant/usuário. Essa distinção precisa estar no contrato e na política. **[requer validação jurídica]**
- **DPA / Contrato de tratamento** entre PegaTicket e tenant: recomendável. **[requer validação jurídica]**
- **Direitos do titular**: já existe base técnica (dado por tenant, soft delete, auditoria). Falta o *processo* (canal para solicitação de acesso/exclusão/portabilidade) e a política de retenção (ver 1.6).
- **Encarregado (DPO)**: indicar um contato. Pode ser o próprio dono no início.

### 1.6 Retenção e backup/DR (P0)

- **Backup automático: verificar.** Havia um dump SQL de produção (23MB) circulando no contexto operacional do projeto, o que indica que o processo de backup ainda era **manual**. Isto é **P0**. Mínimo viável barato: cron `mysqldump` diário + `gzip` + cópia para storage fora do servidor (a própria Hostinger oferece backup, mas **não confie só no backup do provedor** — leve uma cópia para outro lugar, ex.: bucket barato ou até Google Drive via rclone). Definir **RPO** (perda máxima aceitável, ex.: 24h) e **RTO** (tempo de restauração, ex.: 4h) e **testar restauração** pelo menos uma vez.
- **Cuidado LGPD**: qualquer dump SQL de produção deixado na raiz do repo ou fora de um fluxo controlado é **risco de vazamento de dado pessoal** — garantir que está no `.gitignore` e removido de qualquer histórico público. **[verificar]**
- **Política de retenção por categoria**: dados fiscais têm prazo legal de guarda (em regra 5 anos, podendo variar) **[requer validação contábil/jurídica]**; não podem ser apagados por um pedido genérico de exclusão LGPD. Documentar.

### 1.7 Deploy, documentação operacional e suporte (P1/P2)

- CI/CD já existe (self-hosted runner). **Aceitável para produção.** Melhorias P2: rodar `composer test` no pipeline antes do deploy (gate de qualidade), e ter um passo de rollback documentado.
- Documentação operacional: já há `docs/hostinger-shared-deploy.md`, `first-rollout-playbook.md`, `ci-cd-setup.md`. Falta **runbook de incidente** (o que fazer quando cobrança falha, quando o site cai, quando a SEFAZ/gateway está fora).
- **Suporte ao cliente**: definir canal (e-mail/WhatsApp) e SLA mínimo. Página de status é P2.

### Resumo Seção 1 — o mínimo bloqueante (P0) antes de cobrar
1. HTTPS forçado + secrets protegidos.
2. Backup automático testado + cópia externa + `.sql` fora do repo.
3. Monitoramento de erro (Sentry free tier ou alerta nativo).
4. Cron de `schedule:run` + `queue:work` confirmado funcionando.
5. Termos de Uso + Política de Privacidade + aceite registrado.
6. Trava anti-brute-force no login/OTP/signup.

---

## Seção 2 — Cobrança dos PLANOS de assinatura (PegaTicket cobra os tenants)

### 2.1 O que existe e o que falta

Existe só o **gate de funcionalidade**. Falta **todo** o modelo financeiro: preço, ciclo, assinatura, fatura, pagamento, trial real. Isto é construção majoritariamente **nativa** — só o processamento do pagamento em si precisa de terceiro.

### 2.2 Modelo de dados nativo mínimo (proposta)

Separar conceitos — **não** jogar tudo numa tabela de "pagamentos":

- **`plan_prices`** — desacopla preço do plano (nunca sobrescrever preço já contratado):
  - `plan_id`, `billing_period` (`monthly`|`quarterly`|`yearly`), `amount_cents`, `currency` (`BRL`), `discount_percent`, `valid_from`, `valid_to`, `is_active`, versão.
- **`subscriptions`** — a assinatura do tenant:
  - `tenant_id`, `plan_id`, `plan_price_id` (preço congelado no ato), `billing_period`, `status` (ver máquina de estados), `trial_ends_at`, `current_period_start`, `current_period_end`, `next_charge_at`, `cancel_at`, `canceled_at`, `auto_renew`, `accepted_terms_version`, `accepted_at`, `accepted_ip`.
- **`invoices`** — fatura por ciclo:
  - `subscription_id`, `tenant_id`, `competence_period`, `due_date`, `amount_gross_cents`, `discount_cents`, `amount_net_cents`, `status` (`open`|`paid`|`overdue`|`canceled`|`refunded`), `fiscal_document_id` (nullable, liga à NFS-e da Seção 3).
- **`payments`** — tentativa/pagamento de uma fatura:
  - `invoice_id`, `provider`, `provider_charge_id`, `method` (`pix`|`credit_card`), `amount_cents`, `status`, `paid_at`, `idempotency_key`, dados mascarados (nunca CVV/número completo).
- **`refunds`** — reembolso/estorno com trilha própria:
  - `payment_id`, `reason`, `amount_cents`, `type` (`total`|`partial`), `requested_by`, `protocol`, `provider_refund_id`, `status`.
- **`subscription_events`** — trilha imutável de eventos (criada/ativada/renovada/cancelada/reembolsada) além do `AuditLog` genérico.

Reaproveitar o padrão do projeto: `BaseModel` (uuid + soft delete + auditoria), Event/Listener por mutação, tudo `tenant`-scoped onde couber (assinatura pertence a um tenant).

### 2.3 Máquina de estados da assinatura

`pending` → `trialing` → `active` ⇄ `past_due` → (`suspended` | `canceled`) ; `active`/`trialing` → `cancel_scheduled` → `canceled`; `paid`→`refunded`. Definir **explicitamente** quais transições são permitidas e recusar o resto (com teste). Nunca apagar dado do tenant por falha de cobrança — só suspender acesso.

### 2.4 Ciclos e descontos (faixas sugeridas)

Desconto crescente por compromisso maior (valores **sugeridos**, decisão comercial do dono):
- Mensal: preço cheio, 0%.
- Trimestral: **~10%** de desconto sobre o equivalente mensal.
- Anual: **~20%** (equivale a "~2 meses grátis").

Regra de negócio: o preço é **congelado** no `plan_price_id` da assinatura; reajuste futuro só vale na renovação, comunicado com antecedência.

### 2.5 Trial, régua de inadimplência

- **Trial real**: hoje o tenant entra "em teste" sem expiração. Implementar `trial_ends_at` (sugestão 7–14 dias) + job diário que, ao vencer, exige assinatura ou limita o acesso (não apaga dados).
- **Régua de falha de cobrança** (dunning): 1ª tentativa → retentativa em D+3 → aviso e-mail/in-app → período de tolerância (ex.: 5–7 dias) → limitação progressiva → suspensão. Reativação imediata ao pagar. **Nunca** excluir dados automaticamente.

### 2.6 Cancelamento, estorno, reembolso e **direito de arrependimento**

Usar os termos com precisão (glossário): **cancelamento** ≠ **estorno** (reversão feita pela adquirente/PSP) ≠ **reembolso** (devolução do valor ao pagador).

- **Cancelamento**: permitir imediato ou ao fim do ciclo; registrar responsável/motivo; encerrar cobranças futuras; manter dados conforme retenção; permitir exportação.
- **Direito de arrependimento (CDC art. 49)**: 7 dias para contratação **fora do estabelecimento** (aqui: online). **Ponto sensível [requer validação jurídica]:** o CDC protege a **relação de consumo** (destinatário final). Em uma venda **B2B** (empresa contratando ferramenta como insumo do negócio), o enquadramento como "consumidor" é discutível e a jurisprudência varia. **Recomendação prudente e barata em risco:** conceder o direito de arrependimento de 7 dias a **todos** os tenants por padrão (política comercial), com reembolso integral se solicitado dentro de 7 dias da contratação — é mais simples e seguro que classificar cada cliente. Implementar: botão acessível de solicitação, protocolo, **não exigir justificativa** como condição, cancelamento da renovação, disparo de reembolso, histórico imutável. A regra final de quem tem direito **[requer validação jurídica]**.

### 2.7 O que PRECISA de terceiro (e as opções mais baratas)

Processar dinheiro (Pix e cartão) **não pode ser 100% nativo** — exige instituição de pagamento regulada pelo BACEN. Você **não pode** "gerar Pix" sozinho sem uma conta em um PSP/banco: a chave e o QR Code precisam de uma instituição autorizada por trás.

**Estratégia recomendada:** criar uma **camada de abstração de pagamento** (`PaymentProvider` com `createPixCharge`, `createCardCharge`, `refund`, `validateWebhook`, `getPayment`) e um **adapter** por provedor, para não acoplar o domínio a um fornecedor. Isso é nativo e barato, e permite trocar de PSP sem reescrever regra de negócio.

**Comparativo de PSPs brasileiros (referência de estrutura de custo, sem se comprometer a nenhum — validar valores vigentes no momento da contratação):**

| Provedor | Pix | Cartão | Mensalidade fixa | Observação |
|---|---|---|---|---|
| **Mercado Pago** | taxa baixa por transação | taxa por transação (menor à vista) | Não | API madura, boa doc, Pix barato. Forte candidato ao MVP. |
| **Asaas** | Pix com taxa por cobrança baixa (há faixa de valor fixo baixo por Pix) | taxa por transação | Não (plano grátis) | Focado em cobrança recorrente/boleto/assinatura — **encaixa bem no caso de cobrar planos**. |
| **Pagar.me / Stripe** | Pix disponível | competitivo | Não | Stripe tem ótima DX mas Pix e suporte fiscal BR são menos nativos que os locais. |
| **Efí (Gerencianet)** | **Pix com custo por transação muito baixo / API Pix própria** | disponível | Planos com faixa gratuita de Pix | Historicamente uma das opções mais baratas para **Pix puro**. |

**Recomendação para cobrar planos com orçamento baixo:** começar com **Pix apenas** (custo por transação baixíssimo, sem taxa de adquirente de cartão), usando um provedor **sem mensalidade fixa** e com boa API de cobrança recorrente/Pix (Asaas ou Efí são candidatos naturais para recorrência/Pix; Mercado Pago se quiser cartão junto depois). Cartão recorrente entra numa fase posterior (tem taxa de adquirente inevitável e mais complexidade de retentativa/chargeback).

**Regra de ouro de segurança (LGPD + PCI):** **nunca** armazenar CVV, número completo de cartão, trilha ou senha. Guardar só **token do provedor + bandeira + 4 últimos dígitos + validade**. O cartão é tokenizado no ambiente do PSP (checkout/tokenização client-side), reduzindo drasticamente o escopo PCI — isso é o que torna cartão viável sem virar processadora.

### 2.8 Webhooks e conciliação (nativo)

- **Nunca** confirmar pagamento pelo redirect do navegador. A verdade vem do **webhook** do PSP: validar assinatura/segredo, **idempotência** (chave única, evento processado uma vez só), fila + dead-letter, atualização transacional da fatura.
- **Conciliação** periódica (job nativo): comparar cobranças internas × pagamentos confirmados pelo PSP, marcar divergências. Barato de construir e evita "fatura paga que o sistema acha aberta".

---

## Seção 3 — Nota Fiscal e Cupom Fiscal (NF-e / NFC-e / NFS-e)

> **Aviso de escopo e risco:** esta é a área **mais complexa, arriscada e cara em esforço** de todo o roadmap. Emissão fiscal é cheia de regras estaduais/municipais, muda com frequência e tem consequência legal para o tenant se sair errada. **[requer validação contábil/fiscal contínua]** por profissional habilitado. Não tratar como "mais um CRUD".

### 3.1 O que cada documento é e para quem serve

- **NF-e (modelo 55)** — nota de **mercadoria**, operações entre empresas (B2B), transporte de produto. Relevante para os tenants atacadistas/distribuidoras. Autorizada pela **SEFAZ do estado**.
- **NFC-e (modelo 65)** — **cupom fiscal eletrônico** ao **consumidor final** no varejo/PDV. Relevante para tenants que vendem no balcão. Autorizada pela SEFAZ, exige **CSC** (Código de Segurança do Contribuinte) e gera **QR Code**. Regras variam por UF; **"cupom fiscal" não é padrão nacional único** — algumas UFs usam SAT/MFE. **[requer validação por UF]**
- **NFS-e** — nota de **serviço**, competência **municipal**. **Interessa diretamente à plataforma**: para emitir nota dos planos SaaS cobrados dos tenants (Seção 2), a operação da PegaTicket precisa emitir NFS-e do serviço de software. Existe agora o **padrão nacional NFS-e** (via ambiente nacional/Sefin), mas muitos municípios ainda têm padrão próprio.

### 3.2 O que a emissão exige tecnicamente (comum a NF-e/NFC-e)

- **Certificado digital** do emissor: **A1** (arquivo `.pfx`, fica no servidor, renovação anual) ou **A3** (token/cartão físico — inviável para SaaS automatizado). **Para automação, A1 é obrigatório na prática.**
- Comunicação **SOAP/XML** com os webservices da SEFAZ da UF, **assinatura digital do XML**, validação por **XSD**, protocolo de autorização, geração do **DANFE/DANFE-NFC-e** (PDF), armazenamento do **XML autorizado**.
- **Contingência** (SEFAZ fora do ar): modos previstos em lei, numeração própria, reconciliação posterior.
- Eventos: **cancelamento**, **carta de correção (CC-e)**, **inutilização** de numeração, consulta de status.

### 3.3 Nativo vs. biblioteca open-source vs. serviço pago

Três caminhos, sob o filtro de orçamento baixo:

1. **Serviço terceiro (API de emissão: Focus NFe, NFe.io, PlugNotas, etc.)** — você manda um JSON, eles falam com a SEFAZ. **Menor esforço e menor risco técnico**, mas tem **custo por nota ou mensalidade** e cria dependência forte. Contra o filtro de orçamento em volume alto, mas **imbatível em time-to-market** e em blindar você da complexidade estadual.
2. **Biblioteca open-source PHP `nfephp-org/sped-nfe` (e `sped-nfse`, `sped-da` para DANFE)** — fala **direto com a SEFAZ**, **sem mensalidade de terceiro**. Alinha com o filtro de orçamento. **Mas**: assume **todo** o peso das regras estaduais, atualizações de layout, contingência, tratamento de rejeições, e **exige o certificado A1 no servidor** — o que **[risco de infra]** pode não ser trivial em **Hostinger compartilhada** (manipular `.pfx`, extensões `openssl`/`soap`/`curl`, e `exec()` desabilitado pode atrapalhar libs que dependem disso). **Requer validação de que o ambiente suporta.**
3. **100% do zero (montar SOAP/XML na mão)** — **não recomendado**. Reinventa o que a `sped-nfe` já resolve, com risco altíssimo. Descartar.

**Recomendação pragmática (orçamento baixo + risco controlado):**
- **NFS-e da plataforma (planos):** começar com a **API do padrão nacional NFS-e** se o município operacional da empresa já aderiu; se não, avaliar `sped-nfse` ou um serviço barato só para essa emissão de baixo volume. Volume baixo (uma nota por fatura de plano) → o custo por nota de um serviço pago é **irrelevante** aqui, e economiza MUITO esforço. **Recomendo serviço pago barato por nota para a NFS-e dos planos.**
- **NF-e/NFC-e dos tenants:** aqui o volume pode crescer e justificar o open-source `sped-nfe`. **Mas** dado o risco e a complexidade estadual, a decisão honesta é: **começar com uma API de emissão paga por nota** (Focus NFe / PlugNotas / NFe.io têm faixas por volume) para lançar rápido e validar demanda, e **só migrar para `sped-nfe` nativo quando o volume de notas justificar economicamente** o esforço de manutenção. Construir a **camada de abstração `FiscalProvider`** (adapter) desde o dia 1 para poder trocar serviço→nativo sem reescrever o domínio.

### 3.4 Motor tributário e cadastros (nativo, obrigatório em qualquer caminho)

Independente de quem "transmite" a nota, o PegaTicket precisa **calcular e parametrizar** o tributo — isso é nativo:
- Cadastro fiscal do **tenant**: CNPJ, IE, IM, CNAEs, **regime tributário/CRT** (Simples Nacional / Lucro Presumido / Real), endereço + código IBGE, séries, ambiente (homologação/produção), CSC (NFC-e).
- Cadastro fiscal do **produto**: **NCM**, CEST, origem, unidade tributável, GTIN, CFOP padrão, CSOSN/CST.
- Cadastro do **cliente**: CPF/CNPJ, IE, indicador de IE, consumidor final, contribuinte.
- **Regras tributárias parametrizadas e versionadas** (nunca hardcoded): ICMS, ICMS-ST, IPI, PIS, Cofins, ISS, por operação/origem/destino. Toda parametrização **deve ser aprovada pela contabilidade do tenant**.
- **Reforma Tributária [requer acompanhamento]:** CBS, IBS e Imposto Seletivo entram em transição nos próximos anos. Modelar o motor tributário **versionado por vigência** desde já, para absorver a mudança sem reescrita.

### 3.5 Armazenamento e estados fiscais

- Guardar **XML autorizado + protocolo + eventos + PDF + chave + hash + ambiente + versão de layout** com armazenamento **imutável ou fortemente controlado**. Retenção legal (em regra 5 anos) **[validar]**.
- Estados: `pendente` → `validando`/`assinando`/`enviando` → `autorizado` | `rejeitado` | `denegado`; + `cancelado`, `contingência`, `erro`.
- **Catálogo de rejeições** amigável (código SEFAZ → explicação → ação) — reduz enormemente o suporte.

---

## Seção 4 — Pagamento de PEDIDOS direto para as empresas (cliente final → tenant)

Diferente da Seção 2 (PegaTicket cobra o tenant). Aqui o **cliente final paga o pedido do tenant** via Pix/cartão, com confirmação automática. A infra técnica de Pix/webhook/idempotência é **a mesma** da Seção 2 — reaproveitar a camada de abstração de pagamento. O que muda, e é **a decisão de arquitetura mais importante**, é **quem recebe o dinheiro**.

### 4.1 Decisão de arquitetura: onde o dinheiro cai

**Modelo A — Pagamento direto (recomendado para orçamento baixo):** cada tenant conecta a **própria conta** num PSP (ou informa a própria chave Pix), e o dinheiro do pedido cai **direto na conta do tenant**. A PegaTicket **não** toca no dinheiro de terceiros.
- **Prós:** **menor responsabilidade e risco regulatório** (a PegaTicket não custodia recurso de terceiro), sem necessidade de virar/parecer instituição de pagamento, mais barato. **Alinhado ao orçamento e à lei.**
- **Contras:** cada tenant precisa configurar credencial própria; conciliação e experiência menos padronizadas.

**Modelo B — Marketplace / split de pagamento:** a PegaTicket intermedeia via um PSP que faz **split** (divide e repassa automaticamente ao tenant, retendo comissão da PegaTicket).
- **Prós:** experiência padronizada, comissão automática, gestão centralizada.
- **Contras:** **maior dependência do PSP, mais custo, mais obrigações (KYC dos tenants, risco de chargeback, regras de repasse)** e potencial implicação regulatória. **[requer validação jurídica/regulatória]**

**Diretriz:** para **orçamento baixo e risco baixo**, **Modelo A (pagamento direto)**, ou o recurso de **subconta/split oferecido por PSP autorizado** (ex.: Mercado Pago, Asaas e Pagar.me têm split nativo) **sem a PegaTicket manter saldo sacável de terceiros**. **Nunca** manter carteira/saldo interno sacável ou custodiar recursos de clientes sem estrutura jurídica e autorização do BACEN — isso configuraria arranjo de pagamento regulado. Regra inegociável.

### 4.2 Preferência por Pix (custo quase zero)

Pix é **muito** mais barato que cartão (que tem taxa de adquirente inevitável de ~2–4%). Para pagamento de pedido, **priorizar Pix**:
- Fluxo: pedido confirmado → valor travado → `createPixCharge` (QR + copia-e-cola, com expiração) → cliente paga → **webhook** confirma → validação de assinatura + idempotência → pedido marcado pago → baixa financeira → libera separação/entrega → comprovante.
- Reaproveita 100% da infra de webhook/idempotência/conciliação da Seção 2.
- Cartão do pedido: fase posterior, tokenizado no PSP, mesma camada de abstração.

### 4.3 Casos especiais a tratar (barato de errar, caro de ignorar)

Pagamento duplicado, Pix após expiração, Pix com valor divergente, cartão negado, webhook atrasado/duplicado, pedido cancelado **após** pagamento (→ vira reembolso, não cancelamento), reembolso parcial/total, chargeback. Cada um precisa de regra explícita e teste. **Idempotência** resolve a maioria dos webhooks duplicados.

---

## Seção 5 — Módulo do Contador / Escritório de Contabilidade

Módulo **novo e autônomo** (o dono comparou ao "módulo de loja": um subsistema dentro do PegaTicket), onde o contador/escritório **solicita acesso** aos dados do tenant pelo próprio sistema (fluxo de convite/aprovação, **não** acesso irrestrito automático) e passa a fazer a gestão contábil sem o tenant enviar planilhas manualmente.

### 5.1 Encaixe no modelo multi-tenant atual

O modelo de acesso do PegaTicket **já suporta a fundação disso** sem reinventar:
- Um **escritório de contabilidade** é uma entidade nova (`accounting_offices`) que **atende N tenants** (relação N:N via `accounting_office_tenant` com **status de autorização** `pending`/`approved`/`revoked` e **escopos** concedidos).
- Um contador é um `User`; o acesso dele a um tenant se dá por um vínculo análogo ao `TenantUser`, mas **originado de uma solicitação aprovada pelo tenant** e com **escopos granulares próprios** (ler fiscal, ler financeiro, exportar, etc.).
- Reaproveita `ResolveTenant` (o contador "entra" no tenant autorizado como faz um usuário ao trocar de empresa), `AuditLog` (toda consulta/exportação do contador **deve** ser auditada — requisito de segurança reforçado) e o gate de permissão.
- **Portal separado**: o contador **não** usa o painel operacional do tenant. Interface própria (como o portal do cliente final já é separado — `PortalAuthContext`/`portalApiClient` são o **precedente arquitetural** exato a seguir: guard/token/rotas isolados). **MFA obrigatório** para o contador (dado sensível de várias empresas num login só).

### 5.2 Fluxo de convite/autorização

1. Escritório cria conta (CNPJ + responsáveis). 2. Localiza/convida o tenant. 3. Solicita acesso escolhendo **escopos** e finalidade. 4. O **responsável do tenant aprova/limita/recusa** (consentimento registrado). 5. Acesso recebe **validade** e é **revogável a qualquer momento** pelo tenant. 6. Toda consulta/exportação **auditada**. Isto atende ao princípio LGPD de **minimização** e **base legal clara** para o compartilhamento tenant→contador.

### 5.3 Funcionalidades contábeis (o que entregar, em ordem de valor/custo)

Priorizando o que **sai barato dos dados que já existem**:

- **Já viável hoje (dados existentes):** relatório de vendas por período, movimentação financeira (recebíveis/parcelas já existem), livro-caixa simples (entradas de `orders`/pagamentos), DRE **simplificado** (receita de vendas − custos, se houver custo de produto cadastrado), exportação CSV/PDF. **Esforço M**, alto valor, **não depende de fiscal**.
- **Depende da Seção 3 (fiscal):** notas fiscais emitidas (XML/DANFE), impostos apurados, exportação de XML das notas para o sistema do contador. **Só existe depois que documento fiscal existir.**
- **Exportações que contadores usam:** XML de NF-e (padrão universal que todo sistema contábil importa) é o formato mais valioso e **evita** você ter que gerar SPED no MVP. Geração de SPED (ECD/ECF/EFD) é **GG** e **[requer validação contábil]** — deixar para o fim, ou nem fazer (o contador gera o SPED no sistema **dele** a partir do XML que você exporta).
- **Folha/DP:** fora de escopo inicial — só se algum tenant tiver funcionários e demanda real. Não construir especulativamente.

### 5.4 Comunicação tenant ↔ contador

Central de pendências nativa (o contador pede "classifique esta despesa", "envie o contrato", "produto X sem NCM") com anexos, prazo, status, notificação. Barato e resolve a dor real de "parar de trocar planilha por e-mail".

### 5.5 Limite de responsabilidade profissional

O sistema **organiza, valida, exporta e (quando autorizado) transmite** — **não** se faz passar por contador nem assina ato privativo. Toda ação relevante registra o contador responsável, o executor, data, e (se houver) uso de certificado/procuração. **[requer validação jurídica/CFC]**

---

## Roadmap consolidado em fases

Esforço: **P** = dias · **M** = 1–2 semanas · **G** = 3+ semanas · **GG** = mês(es), risco alto.

### Dependências-chave (ler antes das fases)
- Cobrar qualquer cliente **depende** do endurecimento de produção (Fase A).
- Pagamento de pedidos (Fase C) **reaproveita** a infra de Pix/webhook da cobrança de planos (Fase B) — fazer B antes de C.
- Emissão de NFS-e dos planos (Fase D1) **depende** de faturas de plano existirem (Fase B).
- NF-e/NFC-e dos tenants (Fase D2) é independente de B/C tecnicamente, mas **compete por recurso** e é a mais cara — não paralelizar com nada crítico.
- Módulo do contador (Fase E) entrega valor parcial **sem** fiscal (relatórios financeiros), mas a parte fiscal dele **depende** da Fase D.

---

### FASE A — Endurecimento para produção *(bloqueante de tudo que cobra)* — **M**
- P0: HTTPS forçado + secrets protegidos + `.sql` de prod fora do repo.
- P0: backup automático testado + cópia externa (RPO/RTO definidos).
- P0: monitoramento de erro (Sentry free tier) + endpoint `/health` + uptime externo.
- P0: confirmar cron `schedule:run` + `queue:work` rodando (sem isso, nada assíncrono funciona).
- P0: Termos de Uso + Política de Privacidade + aceite versionado no signup. **[requer validação jurídica]**
- P0: trava anti-brute-force login/OTP/signup.
- P1: rodar `composer test` no CI antes do deploy; runbook de incidente.

### FASE B — Cobrança de planos via Pix *(primeira fonte de receita)* — **M/G**
- Modelo de dados: `plan_prices`, `subscriptions`, `invoices`, `payments`, `refunds`, `subscription_events`.
- Camada de abstração `PaymentProvider` + adapter do PSP escolhido (Pix; Asaas/Efí/Mercado Pago — sem mensalidade fixa).
- Máquina de estados da assinatura + trial real (`trial_ends_at` + job de expiração).
- Fluxo Pix: cobrança → QR → webhook (assinatura + idempotência + fila + DLQ) → baixa de fatura → ativação.
- Régua de inadimplência (dunning) sem apagar dados.
- Cancelamento + direito de arrependimento 7 dias + reembolso. **[requer validação jurídica]**
- Conciliação periódica (job nativo).
- Descontos por ciclo (mensal/trimestral ~10%/anual ~20%).

### FASE C — Pagamento de pedidos via Pix *(cliente final → tenant)* — **G**
- **Decisão de arquitetura primeiro:** Modelo A (pagamento direto / subconta) — **sem custódia de saldo de terceiros**. **[requer validação jurídica/regulatória]**
- Config por tenant da credencial/chave de recebimento.
- Reuso da camada de Pix/webhook/idempotência da Fase B.
- Fluxo Pix do pedido → baixa financeira automática → libera separação/entrega → comprovante.
- Tratamento dos casos especiais (duplicado, expirado, valor divergente, cancelado-após-pago→reembolso, chargeback).
- (Posterior) Cartão do pedido tokenizado.

### FASE D — Fiscal — **GG (a mais arriscada)** · **[validação contábil/fiscal contínua]**
- **D0 (nativo, pré-requisito):** cadastro fiscal de tenant/produto/cliente + motor tributário parametrizado e **versionado por vigência** (pronto para CBS/IBS/IS da Reforma). Camada de abstração `FiscalProvider` (adapter).
- **D1 — NFS-e dos planos da PegaTicket (M/G):** emitir nota de serviço das faturas de plano. Volume baixo → **serviço pago barato por nota** ou padrão nacional NFS-e se o município aderiu. Liga `invoices.fiscal_document_id`. **Depende da Fase B.**
- **D2 — NF-e/NFC-e dos tenants (GG):** começar com **API de emissão paga por nota** (Focus NFe/PlugNotas/NFe.io) para lançar rápido; migrar para `sped-nfe` **nativo** só quando volume justificar. Certificado A1, contingência, eventos (cancelamento/CC-e/inutilização), DANFE, armazenamento imutável de XML, catálogo de rejeições. **[risco de infra: validar suporte da Hostinger a `.pfx`/soap/openssl; possivelmente exige sair de shared hosting].**

### FASE E — Módulo do contador — **G**
- **E1 (independe de fiscal):** escritório + fluxo convite/aprovação/revogação com escopos (reusa `ResolveTenant`/`AuditLog`), portal separado (padrão do portal do cliente final) + **MFA**. Relatórios financeiros/DRE simplificado/livro-caixa a partir de dados existentes. Central de pendências tenant↔contador.
- **E2 (depende da Fase D):** notas fiscais emitidas, impostos apurados, **exportação de XML** para o sistema do contador (evita gerar SPED). SPED/ECD/ECF fica fora do MVP ou por último. **[requer validação contábil/CFC]**

---

### Linha do tempo sugerida (orçamento baixo, um desenvolvedor por vez)

```
A ──▶ B ──▶ C
      │
      └──▶ D1 ──▶ ┐
           D0 ──▶ D2 ──▶ E2
                        E1 (pode começar em paralelo com D, entrega valor sem fiscal)
```

**Prioridade de execução recomendada:** A → B → (D1 junto/logo após B) → C → E1 → D0/D2 → E2.

---

## Riscos e alertas finais (não omitidos)

1. **Fiscal (Fase D) é o maior risco do projeto.** Complexidade estadual/municipal + Reforma Tributária + certificado em shared hosting. Subestimar isso é o erro clássico. Começar comprado (por nota) e internalizar só com volume.
2. **Custódia de dinheiro de terceiros = risco regulatório sério.** Jamais manter saldo sacável de tenants sem autorização BACEN. Pagamento direto/split evita isso.
3. **Cartão sempre tem custo e escopo PCI.** Pix primeiro em tudo. Cartão só tokenizado no PSP.
4. **Direito de arrependimento em B2B é juridicamente cinzento.** Conceder a todos por política é mais barato que litigar. **[validação jurídica]**
5. **Backup manual e `.sql` de produção na raiz do repo são risco atual concreto** (perda de dados + vazamento LGPD). Resolver na Fase A.
6. **Sem monitoramento de erro, uma falha de cobrança é invisível.** Sentry free tier resolve barato.
7. Vários pontos deste documento dependem de **enquadramento tributário/jurídico específico** do dono e de cada tenant — todos marcados **[requer validação]** não são detalhe: são onde o barato pode sair caro.
```
