<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Valor efetivamente pago — só diverge de total_amount em
            // pagamento PARCIAL de pedido não parcelado (paridade com o
            // legado, campo valor_pago). Pagamento total sempre grava
            // paid_amount = total_amount (ver OrderService::performPayment()).
            $table->decimal('paid_amount', 10, 2)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
