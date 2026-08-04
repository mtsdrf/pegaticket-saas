<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário do banco (roadmap 1A — endurecimento de produção). Mesma
// dependência do cron `schedule:run` acima. Ver BackupDatabaseCommand.
Schedule::command('backup:database')->daily();

// Cobrança de planos (roadmap 1B) — geração de fatura no fechamento de
// ciclo e expiração de trial. Mesma dependência do cron `schedule:run`.
Schedule::command('subscriptions:generate-invoices')->daily();
Schedule::command('subscriptions:process-trial')->daily();

// Suspensão por falha de cobrança real (roadmap Fase B, item 1 — Mercado
// Pago Preapproval). Tolerância de 7 dias já contada em
// subscriptions.grace_period_ends_at pelo webhook; este comando só age
// quando ela vence sem regularização. Mesma dependência do cron
// `schedule:run` acima.
Schedule::command('subscriptions:enforce-grace-period')->daily();

// Reconciliação ativa de cobranças Mercado Pago de vendas. Não substitui
// o webhook; atua como rede de segurança para atraso/perda de notificação
// ou timeout ambíguo de criação. Rodar em intervalos curtos reduz a janela
// em que um pagamento já aprovado no PSP ainda aparece pendente aqui.
Schedule::command('payments:reconcile-mercadopago-sales --limit=100')->everyFifteenMinutes();

// Mesma rede de segurança acima, para o rail comprador -> tenant via
// PagBank (venda de ingresso). Ver PagBankPaymentProvider/
// PaymentWebhookController::handlePagBank.
Schedule::command('payments:reconcile-pagbank-sales --limit=100')->everyFifteenMinutes();

// Agrupa recebíveis já elegíveis (D+1 pós-evento no desenho atual) em
// lotes locais de repasse. A liberação externa via PagBank vem na etapa
// seguinte; aqui fechamos o vínculo auditável entre receivable e
// settlement.
Schedule::command('finance:generate-settlements')->hourly();

// Tenta liberar settlements já vencidos. Quando houver split com
// custódia, chama a API do PagBank; fora disso, fecha a baixa localmente.
Schedule::command('finance:release-settlements')->hourly();

// Consolida settlements já marcados como release_requested, cobrindo o
// atraso entre a solicitação de liberação e a materialização do status
// RELEASED no PagBank.
Schedule::command('finance:reconcile-settlement-releases')->everyFifteenMinutes();

// Varredura de integridade financeira interna: destaca recebíveis sem
// agrupamento, settlements divergentes e ajustes ainda abertos.
Schedule::command('finance:reconcile-financial-integrity')->hourly();

// Libera reserva de risco retida sobre recebíveis (extra_reserve_enabled)
// quando o prazo configurado em extra_reserve_release_offset_days vence.
// Percentual/prazo default (5%, D+30) ainda não são decisão de negócio
// validada — ver PlatformFinanceSettingsService::getCurrent().
Schedule::command('finance:release-risk-reserves')->hourly();

// Varredura proativa de holds vencidos (spec 5.9) — StorefrontHoldService
// já expira "on read" por tenant+evento; isso só fecha o gap de holds de
// eventos que ninguém mais consulta, pra observabilidade/relatório não
// mostrar reserva vencida como ativa. Ver ExpireInventoryHoldsCommand.
Schedule::command('inventory:expire-holds')->everyFiveMinutes();

// Comunicação transacional mínima (roadmap Fase 1) — lembrete de evento por
// e-mail 24h antes, para vendas pagas ainda não lembradas. Ver
// SendEventReminderMailsCommand.
Schedule::command('sales:send-event-reminders --hours-ahead=24')->hourly();

// Reconciliação ativa das assinaturas/preapprovals no Mercado Pago. Não
// substitui o webhook de cobrança do ciclo; sincroniza o status estrutural
// do vínculo recorrente (`authorized`, `cancelled`, etc.) como rede de
// segurança operacional.
Schedule::command('subscriptions:reconcile-mercadopago --limit=100')->hourly();

// Fecha o loop do timeout ambíguo de criação (risco ALTO, 2026-07-24):
// resolve tentativas `payment_idempotency_keys` pending com lock expirado
// contra o Mercado Pago antes que um retry manual do usuário possa gerar
// uma cobrança/assinatura duplicada.
Schedule::command('payments:reconcile-idempotency --limit=100')->everyFiveMinutes();
