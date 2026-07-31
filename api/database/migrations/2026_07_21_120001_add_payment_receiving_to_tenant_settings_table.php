<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recebimento de pagamento de pedido pelo tenant (roadmap 2A, Modelo A — sem
 * custódia, o dinheiro nunca passa pela Maskats). `payment_receiving_method`
 * = manual (conciliação manual, default) | pix_key (chave Pix própria do
 * tenant). `payment_pix_key` é criptografada em repouso (cast `encrypted` do
 * Eloquent, nativo) — por isso `text`: o ciphertext é bem maior que a chave
 * em claro (~77 chars viram algumas centenas).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('payment_receiving_method', 20)->default('manual')->after('accepted_payment_methods');
            $table->text('payment_pix_key')->nullable()->after('payment_receiving_method');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['payment_receiving_method', 'payment_pix_key']);
        });
    }
};
