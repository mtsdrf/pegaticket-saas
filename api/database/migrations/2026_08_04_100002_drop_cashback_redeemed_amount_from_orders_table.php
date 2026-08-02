<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nunca lida/escrita em nenhum lugar do backend (nem está no $fillable de
 * Sale) — resíduo do produto antigo "Maskats".
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'cashback_redeemed_amount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('cashback_redeemed_amount');
            });
        }
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
