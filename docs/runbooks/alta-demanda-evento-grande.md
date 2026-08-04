# Runbook — Evento de alta demanda (venda crítica)

Checklist operacional para eventos com previsão de pico de acesso simultâneo (venda relâmpago de ingressos limitados, artista grande, etc). Complementa a Fase 7 do roadmap ("risco, antifraude e alta demanda").

Este runbook cobre operação. Não substitui os itens de código já implementados (fila virtual, honeypot, motor de risco, observabilidade) — ele descreve **quando e como usá-los**.

## Antes de abrir vendas (pré-evento)

1. **Decidir se o evento precisa de `high_demand_mode`.** Ative apenas para eventos com risco real de pico (lote limitado, artista de alta procura, histórico de sobrecarga em evento similar). Não é o padrão — é opt-in por evento (`events.high_demand_mode`, default `false`).
   - Ativação hoje é manual, direto no banco/tinker (`Event::find($id)->update(['high_demand_mode' => true])`) — **não existe tela de administração para isso ainda**. Se o volume de eventos em alta demanda crescer, vale criar um campo no formulário de evento (`EventFormPage.tsx` + `EventRequest`), fora de escopo desta rodada.
   - Ajustar `virtual_queue_admission_batch_size` (default técnico `50`, **não validado com o usuário**) para um valor compatível com a capacidade real de checkout simultâneo do tenant. Comece conservador; é mais fácil liberar mais gente depois do que reverter overselling.
2. **Confirmar que o comando de admissão está no scheduler.** `Schedule::command('storefront:admit-virtual-queue-entries')->everyMinute()` em `api/routes/console.php` — depende do cron `schedule:run` já configurado em produção (mesma dependência dos demais `Schedule::command(...)` do projeto).
3. **Confirmar que o worker de reconciliação de pagamento está saudável** (`payments:reconcile-pagbank-sales`, `payments:reconcile-mercadopago-sales`, ambos `everyFifteenMinutes()`) — evento de alta demanda gera pico de tentativas de pagamento, e é quando mais importa não perder confirmação de webhook.
4. **Revisar `finance:generate-settlements`/`finance:release-settlements`** não têm relação direta com pico de venda, mas confirme que não há falha acumulada nas últimas 24h antes de um evento grande (evita destacar atenção da equipe pra dois incêndios ao mesmo tempo).
5. **Comunicar internamente a janela de alta demanda** (data/hora de abertura de vendas) para a equipe de suporte — perguntas sobre "por que estou numa fila" vão aparecer, e hoje o texto de UI é genérico ("Este evento está com procura muito alta agora").

## Durante a janela de abertura de vendas

1. **Monitorar `GET /reports/operation-snapshot`** (`OperationSnapshotCard` no dashboard) — os dois campos novos relevantes:
   - `checkout.error_rate_percent` — taxa de holds que não viraram venda na janela de `checkout.window_hours` (default técnico 6h). Se subir muito acima do baseline do tenant, é sinal de fricção no checkout (aumentar `virtual_queue_admission_batch_size` pode ajudar, ou pode ser sintoma de queda do PSP — ver runbook de contingência de pagamento).
   - `virtual_queue.waiting`/`virtual_queue.admitted` — tamanho atual da fila. Crescimento sustentado de `waiting` sem `admitted` subir junto indica que o batch size está baixo demais para a demanda, ou que o comando agendado parou de rodar (checar `schedule:run`/log do cron).
2. **Não alterar `virtual_queue_admission_batch_size` no meio do pico sem necessidade.** Se precisar, prefira aumentar aos poucos (evita destravar a fila inteira de uma vez e sobrecarregar o checkout).
3. **Ficar de prontidão para o freeze de deploy** — ver `plano-de-freeze.md`. Não faça deploy de nada não relacionado a esse evento durante a janela crítica.
4. **Ficar de prontidão para o runbook de contingência de pagamento** se o volume de erro de checkout subir junto com falha visível do PSP.

## Depois do evento (pós-venda)

1. **Desativar `high_demand_mode`** do evento assim que a demanda inicial normalizar (a fila deixa de fazer sentido depois que a corrida inicial passa; manter ativo sem necessidade só adiciona uma etapa extra pro comprador).
2. **Revisar vendas com `risk_flagged=true`** geradas durante a janela (coluna "Risco" na listagem de vendas) — é só um alerta, decisão de cancelar/investigar é manual do staff.
3. **Registrar no changelog interno** o resultado real (pico de `waiting`, taxa de erro de checkout observada, se o batch size precisou de ajuste) — isso vira o baseline pro próximo evento grande, já que não existe teste de carga real rodado neste ambiente (ver `testes-de-carga.md`).

## O que este runbook NÃO cobre

- Regras adaptativas de throttle dinâmico por carga real — não implementado nesta rodada, decisão de arquitetura maior pendente.
- CAPTCHA/WAF de fornecedor externo — a proteção anti-bot atual é honeypot + tempo mínimo de preenchimento, sem terceiro. Se o volume de abuso automatizado justificar, é uma decisão de produto/custo do usuário, não uma implementação automática.
