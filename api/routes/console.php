<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Primeiro Schedule::command() do projeto (roadmap Delivery, Fase 5) — só
// tem efeito se o cron do servidor rodar `php artisan schedule:run` a cada
// minuto (padrão Laravel). Confirmar/configurar isso no ambiente de
// produção/staging é responsabilidade do deploy manual via SSH.
Schedule::command('cashback:process')->daily();

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

// Reconciliação ativa de cobranças Mercado Pago de pedidos. Não substitui
// o webhook; atua como rede de segurança para atraso/perda de notificação
// ou timeout ambíguo de criação. Rodar em intervalos curtos reduz a janela
// em que um pagamento já aprovado no PSP ainda aparece pendente aqui.
Schedule::command('payments:reconcile-mercadopago-orders --limit=100')->everyFifteenMinutes();

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

// Régua de reativação de cliente (roadmap A5, item 18) — mesma dependência
// do cron `schedule:run` acima.
Schedule::command('reactivation:process')->daily();

// iFood: a documentação pública recomenda polling frequente; em infra
// simples garantimos pelo menos 1 varredura por minuto via scheduler.
Schedule::command('marketplace:poll-ifood --limit=20')->everyMinute();

// Recuperação operacional do iFood: reprocessa falhas recentes e tenta
// reimportar pedidos externos ainda pendentes/erro sem depender de ação
// manual do operador.
Schedule::command('marketplace:recover-ifood --limit=20 --events=20 --orders=20')->everyFiveMinutes();
