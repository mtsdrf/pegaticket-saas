<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meio de pagamento + troco (roadmap Delivery, checkout público) —
 * `payment_method` já era validado no request/DTO desde a feature de cupom
 * por meio de pagamento, mas nunca era persistido no pedido, só usado
 * transitoriamente pra checar elegibilidade de cupom. `needs_change`/
 * `change_for_amount` são novos: informativo pro operador saber que o
 * cliente pagará em dinheiro e precisará de troco pra um valor específico.
 * Todos nullable/default preservam 100% os fluxos de pedido já existentes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('notes');
            $table->boolean('needs_change')->default(false)->after('payment_method');
            $table->decimal('change_for_amount', 10, 2)->nullable()->after('needs_change');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'needs_change', 'change_for_amount']);
        });
    }
};
