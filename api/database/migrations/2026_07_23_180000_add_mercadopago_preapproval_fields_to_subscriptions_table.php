<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança recorrente real de assinatura via Mercado Pago Preapproval
 * (roadmap Fase B, item 1 — assinatura). `preapproval_id` guarda o id
 * retornado pelo MP na criação do Preapproval (POST /preapproval); nulo
 * para assinaturas sem cobrança real (provider manual, plano gratuito).
 * `grace_period_ends_at` é setado quando o Mercado Pago avisa falha de
 * cobrança (início da tolerância de 7 dias); zerado quando a cobrança é
 * regularizada. `subscriptions:enforce-grace-period` suspende quem passar
 * dessa data ainda sem pagamento confirmado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('preapproval_id')->nullable()->unique()->after('plan_price_id');
            $table->timestamp('grace_period_ends_at')->nullable()->index()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['preapproval_id', 'grace_period_ends_at']);
        });
    }
};
