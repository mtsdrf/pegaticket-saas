<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxa de serviço aplicada como acréscimo no fechamento da comanda, NÃO como
 * imposto — a parametrização trabalhista/fiscal da taxa de serviço requer
 * validação contábil do tenant. service_fee_mandatory=true impede que a
 * operação assistida recuse a taxa no fechamento.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->decimal('service_fee_percent', 5, 2)->nullable()->after('payment_pix_key');
            $table->boolean('service_fee_mandatory')->default(false)->after('service_fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['service_fee_percent', 'service_fee_mandatory']);
        });
    }
};
