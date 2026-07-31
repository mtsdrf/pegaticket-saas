<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layout do catálogo da loja pública (2026-07-28) — a empresa escolhe entre
 * o layout anterior (`grid`, cards com foto grande) e o novo (`list`, lista
 * com imagem à direita, padrão de mercado recomendado no gap-analysis de
 * catálogo). default 'list' preserva o comportamento atual (é o único
 * layout hoje implementado no storefront até esta migration).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('catalog_layout', 10)->default('list')->after('allow_store_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('catalog_layout');
        });
    }
};
