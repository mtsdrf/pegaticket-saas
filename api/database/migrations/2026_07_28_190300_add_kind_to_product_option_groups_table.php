<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingrediente removível vs. adicional (gap-analysis de catálogo, item 5) —
 * `kind='addon'` (default) preserva 100% o comportamento atual ("escolha
 * com preço"). `kind='ingredient_removal'` é só uma flag pro frontend
 * escolher o componente certo (checkbox "remover X" em vez de "+
 * adicionar X") — mesma estrutura de grupo/opção, sem tabela nova.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_option_groups', function (Blueprint $table) {
            $table->string('kind', 30)->default('addon')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_option_groups', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
