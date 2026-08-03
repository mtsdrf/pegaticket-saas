<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * coupon_id usa nullOnDelete() (não cascade): venda histórico não pode
 * sumir se o cupom for removido depois — diferente de todas as outras FKs
 * de sales (client/stock_location), que são obrigatórias por natureza.
 * discount_amount default 0 preserva 100% o fluxo staff existente (nunca
 * usa cupom).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('client_id')
                ->constrained('coupons')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });
    }
};
