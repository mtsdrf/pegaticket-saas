---
name: security-standards
description: Checklist de segurança obrigatório para módulos novos + vulnerabilidades reais achadas/corrigidas em auditorias. Consultar ANTES de implementar qualquer módulo novo.
metadata:
  type: project
---

## Checklist "sempre verificar" ao criar módulo novo

Consultar antes de considerar um módulo pronto. Cada item já tem precedente correto no código — replicar, não reinventar.

### 1. Autenticação / identidade JWT
- O projeto tem 3 identidades JWT MANUAIS distintas com o MESMO segredo: `User` (staff, `JwtAccessMiddleware`/`jwt`), `FinalCustomer` (portal, `customer.jwt`), `AccountingOffice` (contador, `accounting.jwt`). Guards do Laravel NÃO são usados.
- **Todo resolver de token DEVE chamar `$token->checkSubjectModel(X::class)`** (via claim `prv`, `lock_subject=true`). Sem isso, um token de uma identidade com `sub` colidente é aceito como outra (bypass real de identidade — PKs auto-increment começam em 1 nas 3 tabelas). Precedente: `CustomerTokenResolver`, `AccountingTokenResolver`, `JwtAccessMiddleware`.
- Blacklist por `jti` (`TokenBlacklist`, filtrar `expires_at > now()`), expiração via `config('jwt.ttl')`.
- Rota autenticada de uma identidade NUNCA leva `tenant`/`perm` se a identidade não pertence a tenant (portal/contador). Testar as duas direções de rejeição de token (staff em rota de portal E vice-versa) — ver `PortalIdentityBoundaryTest`.

### 2. Isolamento de tenant (P0)
- `tenant_id` SEMPRE vem do contexto resolvido (`app('tenant_id')` via `ResolveTenant`/`ResolveAccountingTenant`), NUNCA do corpo do request.
- Toda Service que resolve recurso por `uuid` (route-model-binding só filtra por uuid, não por tenant) precisa de guard de posse: `resolveOwned()`/`assertBelongsToCurrentTenant()`/`if ((int)$model->tenant_id !== (int)app('tenant_id')) abort(404)`. Não confiar só no `perm:` (valida permissão, não posse) nem só em Global Scope.
- Contador: acesso a `tenants/{tenant_uuid}/*` exige vínculo `approved` em `accounting_office_tenant` (validado em `ResolveAccountingTenant`) — nunca só a existência do tenant.
- Toda tabela de domínio: FK `tenant_id` `constrained()->cascadeOnDelete()` + índice; unique compostas incluem tenant.
- Testar IDOR: recurso da empresa B acessado/alterado/excluído/listado pela empresa A → 404.

### 3. Dados sensíveis
- Segredo reversível (semente TOTP, chave Pix) → cast `'campo' => 'encrypted'` no Model (criptografia em repouso). Senha → `Hash::make` + `Hash::check`, coluna `password_hash`, nunca cast encrypted.
- Todo campo secreto: incluir em `$hidden` do Model E na denylist `$sensitive` de `AuditLog::record()` (`app/Models/AuditLog.php`). **O filtro é por NOME EXATO de coluna, não por sufixo** — coluna nova de token/segredo precisa ser adicionada manualmente à lista (já mordeu com `pending_email_token_hash`, `password_reset_token_hash`, `totp_secret`, `payment_pix_key`).
- Resource nunca expõe hash/segredo. Expor a própria chave Pix do dono no settings dele é aceitável (recurso protegido por `perm`).

### 4. Rate limit
- Toda rota autenticada leva `throttle:{max},{min},{nome-unico}`. Login/OTP/TOTP/reset de senha/register/webhook: limites baixos (5-10/min). Ver `routes/api.php` como referência de faixas por tipo.
- OTP/TOTP: código de N dígitos precisa de throttle agressivo NO endpoint + contador de tentativas persistente (`attempts` em `final_customer_otps`) quando aplicável.

### 5. Validação de entrada / mass assignment
- Fluxo obrigatório: `FormRequest` (valida) → DTO `fromArray($request->validated())` → Service. NUNCA `$request->all()` em escrita, NUNCA Model recebendo array cru do request.
- `BaseModel` usa `$guarded = ['id']` — a proteção real contra mass assignment é o DTO/Request, não o `$fillable`. Campos derivados (`tenant_id`, `status`, `paid_at`, totais, flags) são setados pelo servidor no Service, nunca aceitos do request.
- FK cross-tabela tenant-scoped: `Rule::exists(...)->where(tenant_id)` (422, não 500/404).
- Upload: `mimes:` allowlist (bloqueia HTML/SVG → sem stored XSS) + `max:` tamanho. Ver `CreateAccountingMessageRequest`.

### 6. SQL / raw
- Só `DB::raw`/`selectRaw`/`whereRaw` com expressão ESTÁTICA (nomes de coluna/constantes), nunca input de usuário concatenado. `sort_by`/filtros de grid sempre via whitelist `SORTABLE` interna.

### 7. Frontend
- NUNCA `dangerouslySetInnerHTML`/`innerHTML`/`eval`/`new Function`. Conteúdo do backend (ex.: `legal_documents.content`) renderiza como `{texto}` (React escapa) com `whiteSpace: pre-wrap`.
- Token em `localStorage` (3 chaves: staff/portal/contador) é tradeoff aceito — a defesa é a ausência total de vetor de XSS acima. Nunca logar token/senha em console. Nunca segredo hardcoded no bundle (tudo em `VITE_*` é público).

### 8. Webhook (quando plugar PSP real)
- Validar assinatura em tempo constante ANTES de qualquer escrita/idempotência (senão idempotency poisoning por `external_id` forjado). Validar timestamp/replay. Nunca confiar em IP nem no redirect do browser. Estrutura pronta em `PaymentWebhookController` (hoje 501).

## Vulnerabilidades reais achadas e corrigidas

### Auditoria global 2026-07-22
- **[MÉDIA] Semente TOTP do contador em texto puro** — `accounting_offices.totp_secret` gravado em plaintext (comprometer o DB = clonar o 2FA de todos os contadores). Causa raiz: cast `encrypted` esquecido no Model novo. Fix: `'totp_secret' => 'encrypted'` em `AccountingOffice` (leitura via atributo continua transparente; feature ainda não em produção, sem linha legada a migrar).
- **[BAIXA/defesa-em-profundidade] Segredos novos fora da denylist de auditoria** — `totp_secret`/`payment_pix_key` não estavam em `AuditLog::$sensitive`; se `AccountingOffice`/`TenantSettings` fossem auditados via trait `Auditable`, o valor entraria em `old_values`/`new_values`. Fix: ambos adicionados à lista. (Reforça a regra 3: filtro por nome exato.)
- **[MÉDIA] Deps vulneráveis** — `guzzlehttp/guzzle < 7.15.1` (3 advisories medium: referer leak, cookie scope, cookie DoS) e `fast-uri` (npm, 1 high, host confusion). Fix: `composer update guzzlehttp/*` + `npm audit fix`. Ambos audits limpos depois.

### Áreas auditadas e confirmadas CORRETAS (sem alteração)
Boundary de identidade JWT (`checkSubjectModel` nos 3 resolvers), isolamento de tenant nos módulos novos (subscription via `resolveOwned`/`findByUuidForTenant`, accounting reports escopados pelo tenant resolvido, `ResolveAccountingTenant` exige vínculo `approved`, mensagens do contador escopadas pelo link nos dois lados), rate limiting das rotas sensíveis novas, CORS (allowlist explícita, `supports_credentials:false`), CSP (`default-src 'none'`) + headers em `ApiHardening`, sem SQL injection (raw só estático), sem XSS no frontend (zero `dangerouslySetInnerHTML`, `legal_documents.content` renderizado escapado), sem segredo hardcoded/logado no client, FKs+índices+unique em todas as tabelas novas.

### Auditoria aprofundada da integração Mercado Pago (2026-07-23, segunda rodada)
- **[ALTA/bug real] `POST /v1/orders` sem `payer`** — confirmado contra a API real (não só `Http::fake`) que a criação de cobrança Pix de venda falhava sempre por faltar `payer`. Regra nova: toda vez que um payload de PSP exigir dado de "quem paga", resolver pela cadeia de posse real (`Client`→`FinalCustomerTenantLink` CONFIRMADO→`FinalCustomer`), nunca usar o e-mail do TENANT/loja como substituto do cliente final — são payers diferentes. Fallback final aceitável: dado real já cadastrado (nome, CPF/CNPJ), nunca inventado.
- **[ALTA] Mesmo padrão de "campo obrigatório pode vir nulo" no Preapproval** — `tenants.email` é nullable/opcional; `payer_email` do `POST /preapproval` não tinha fallback nem guard. Regra nova: quando o dado principal (contato do tenant) pode faltar mas o payer LEGITIMAMENTE é o próprio tenant, cair para o e-mail do usuário autenticado (`users.email`, sempre obrigatório) — nunca enviar `null` num campo obrigatório do PSP; se não houver nenhum dado real disponível, falhar explicitamente (exception) ANTES da chamada HTTP.
- **[MÉDIA] Idempotência de webhook por `(provider, external_id)` sem o `type`** — PSPs que reaproveitam namespaces de ID por tipo de recurso (Mercado Pago: order/authorized_payment/preapproval/chargeback/claim) podem colidir de id ENTRE tipos diferentes. Toda idempotência de webhook precisa incluir o tipo/tópico do evento na chave, não só o id do recurso — senão um evento de tipo B pode ser silenciosamente descartado por já existir um evento de tipo A com o mesmo id.
- **[MÉDIA] Endpoint de estorno/arrependimento sem guard de repetição** — `findCurrentForTenant`-style lookups que não filtram por status permitem reencontrar um recurso já em estado terminal (cancelado) e reprocessar uma ação de "primeira vez" (aqui: `Refund` + chamada real ao PSP) a cada replay/duplo-clique. Regra: toda ação financeira "única por recurso" precisa de lock (`lockForUpdate`) + reconferência do estado terminal DENTRO da transação, mesmo que o PSP já tenha idempotência própria (a idempotência do PSP protege o dinheiro, não a integridade do histórico/auditoria local).

### Auditoria da integração Mercado Pago (2026-07-23)
- **[MÉDIA] Webhook gravava `webhook_events` antes de validar `x-signature`** — violava a própria regra 8 abaixo. Fix: assinatura validada ANTES de qualquer `WebhookEvent::firstOrCreate` no `PaymentWebhookController` (branch `mercadopago`); request não assinado nunca mais escreve no banco.
- **[MÉDIA] Sem guard contra `live_mode=false` em produção** — MP não separa sandbox por URL (é o `access_token` que decide o ambiente); webhook agora rejeita evento de teste quando `app()->environment('production')`.
- **[ALTA] Cobrança de ciclo de Preapproval confirmada sem checar valor divergente** — `InvoiceService::markPaidFromWebhook` recebia o valor do webhook mas nunca comparava contra a fatura esperada (parâmetro morto). Corrigido pra espelhar a mesma regra que `SalePaymentService::reconcileWebhookPayment` já tinha (tolerância 0.001, `status=divergent` sem avançar o ciclo). Ver `architecture-decisions.md` pra detalhe completo.
- **[MÉDIA/preventiva] `refund()` do adapter MP sem `X-Idempotency-Key`** — única operação de mutação financeira do adapter sem chave; corrigido (chave derivada de `payment_uuid+amount`) mesmo estando hoje sem caller real, pra não virar bug de duplo-estorno quando for ligado.
- Detalhe completo, riscos NÃO corrigidos (documentados conscientemente) e resultado de teste: ver seção "Auditoria de segurança/consistência — integração Mercado Pago (2026-07-23)" em `architecture-decisions.md`.

## Recomendações que exigem decisão de produto/infra (não corrigidas em código)
- **Anexos do contador servidos do disco público** (`AccountingMessageResource` → `Storage::disk('public')->url()`). Nome aleatório de 40 chars torna enumeração impraticável e o `mimes:` bloqueia HTML/SVG (sem stored XSS), mas são documentos financeiros privados servidos sem autenticação (URL pode vazar por histórico/referer). Correção adequada = disco privado + endpoint de download autorizado (auth + escopo do vínculo/tenant) — exige mudança de frontend (hoje usa `attachment_url` direto em `<a href>`) e o storage symlink (Hostinger desabilita `symlink()`). Mesmo padrão de imagem de produto/avatar, mas sensibilidade maior.
- **Webhook de pagamento**: ao plugar PSP real, mover validação de assinatura para ANTES do `WebhookEvent::firstOrCreate` (ver regra 8).
- **Queue worker permanente** ausente em produção (supervisor/cron) — jobs (geocode etc.) acumulam. Já sinalizado antes.
- **Brute-force distribuído**: throttles de login/OTP são por IP; atacante com muitos IPs contorna. Mitigar em infra (WAF/Cloudflare) se virar problema real.
