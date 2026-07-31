<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restrição opcional de cupom por meio de pagamento — mesmo padrão de
 * tenant_settings.accepted_payment_methods (JSON nullable, lista fixa
 * cash/pix/credit_card/debit_card). null = sem restrição, o cupom vale
 * para qualquer meio (comportamento atual, 100% compatível).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('allowed_payment_methods')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('allowed_payment_methods');
        });
    }
};
