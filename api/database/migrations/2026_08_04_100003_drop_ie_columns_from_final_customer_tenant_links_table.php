<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscrição Estadual — conceito B2B fiscal sem uso no domínio de bilheteria
 * (cliente final é pessoa física comprando ingresso). Resíduo do produto
 * antigo "Maskats".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('final_customer_tenant_links', function (Blueprint $table) {
            foreach (['ie', 'ie_indicator'] as $column) {
                if (Schema::hasColumn('final_customer_tenant_links', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
