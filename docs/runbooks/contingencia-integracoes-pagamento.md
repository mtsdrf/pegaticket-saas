# Runbook — Contingência de integrações de pagamento (PagBank / Mercado Pago)

Descreve o que fazer se PagBank ou Mercado Pago ficarem instáveis/fora do ar, e como o sistema **já se comporta hoje segundo o código real** — não é uma proposta de comportamento novo.

## Como o sistema já se comporta hoje quando o PSP falha

- **Webhook é a via principal de confirmação de pagamento**, mas não é a única: existem comandos agendados de reconciliação ativa que reconsultam o PSP diretamente, sem depender do webhook chegar:
  - `payments:reconcile-pagbank-sales --limit=100` (`ReconcilePagBankSalePaymentsCommand`) — `everyFifteenMinutes()`.
  - `payments:reconcile-mercadopago-sales --limit=100` — mesmo espírito, mesma frequência.
  - Ambos reconsultam o pedido no PSP (`GET /orders/{id}` no PagBank) antes de aplicar qualquer efeito local — nunca confiam só em estado local.
- **Reconciliação de idempotência**: `payments:reconcile-idempotency --limit=100` (`everyFiveMinutes()`) — rede de segurança adicional sobre o registro de idempotência de webhook.
- **Health check manual do Mercado Pago**: `php artisan payments:mercadopago-health-check` (`MercadoPagoHealthCheckCommand`) — valida conectividade/autenticação com uma chamada read-only, retorna ambiente, status HTTP, se autenticou e quantos meios de pagamento voltaram. Não roda agendado — é uma ferramenta de diagnóstico manual para quando há suspeita de instabilidade.
- **Uma venda cria hold antes de gerar cobrança** (`StorefrontHoldService::createHold`) — se o PSP cair depois do hold criado mas antes da cobrança ser gerada, o hold expira normalmente pelo TTL configurado (`tenant_settings.hold_duration_minutes`, default 15min) e libera o inventário sozinho; não fica preso.
- **Não existe fallback automático entre provedores** (PagBank <-> Mercado Pago) — cada tenant/checkout usa o provedor configurado; se ele cai, não há troca automática para o outro.

## O que fazer quando um PSP está instável

1. **Confirmar a suspeita antes de agir** — não assuma queda pelo relato de um único comprador:
   - Rodar `php artisan payments:mercadopago-health-check` (Mercado Pago) para diagnóstico imediato.
   - Para PagBank, não existe comando equivalente de health-check dedicado hoje — a evidência prática é acúmulo de item `divergent`/`failed` não revisado nas próximas execuções de `payments:reconcile-pagbank-sales`, ou erro elevado reportado pela própria API do PagBank nos logs de aplicação (`ApplicationLogger`, já usado dentro de `ReconcilePagBankSalePaymentsCommand`).
   - Cruzar com `checkout.error_rate_percent` do `GET /reports/operation-snapshot` (ver `alta-demanda-evento-grande.md`) — alta simultânea nesse indicador e no PSP é o padrão mais confiável.
2. **Rodar a reconciliação manualmente com um `--limit` maior** enquanto o problema persiste, em vez de esperar o próximo ciclo agendado:
   ```
   php artisan payments:reconcile-pagbank-sales --limit=500
   php artisan payments:reconcile-mercadopago-sales --limit=500
   ```
3. **Comunicar aos compradores em andamento** (se o volume justificar) — hoje não existe banner de status de pagamento no storefront; a comunicação é manual (suporte, redes sociais do tenant). Registrar como gap conhecido, não implementar um banner especulativo sem pedido explícito.
4. **Não cancelar vendas pendentes automaticamente.** O sistema já trata pagamento pendente como estado normal transitório (hold expira, venda fica sem confirmação até reconciliar) — intervenção manual só quando a reconciliação já rodou e o item continua divergente/falho por tempo desproporcional.
5. **Depois que o PSP normalizar**, confirmar que a fila de itens pendentes de reconciliação volta a zero acompanhando as próximas execuções agendadas (`everyFifteenMinutes()`), sem precisar de ação manual adicional.

## O que este runbook NÃO cobre (fora de escopo, não inventado)

- Fallback automático entre provedores de pagamento — não existe no código atual; seria uma feature nova, não uma contingência operacional.
- Página pública de status de pagamento/incidente — não existe; comunicação hoje é manual.
- SLA formal com PagBank/Mercado Pago — depende de contrato comercial do usuário com os provedores, fora do escopo técnico deste runbook.
