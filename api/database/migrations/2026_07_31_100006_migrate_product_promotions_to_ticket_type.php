<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `product_promotions` mantido (tabela e classe `ProductPromotion` — bônus
 * de renomear classe não obrigatório, ver roadmap seção 4A) só repontado
 * pra `ticket_types`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_promotions', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->renameColumn('product_id', 'ticket_type_id');
        });

        Schema::table('product_promotions', function (Blueprint $table) {
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_promotions', function (Blueprint $table) {
            $table->dropForeign(['ticket_type_id']);
            $table->renameColumn('ticket_type_id', 'product_id');
        });
    }
};
