<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Achado de code review da Fase 1 do storefront: a unique existente
 * (final_customer_id, client_id) não impede duas requisições concorrentes
 * de checkout criarem 2 Client diferentes (e portanto 2 links) para o
 * mesmo par (final_customer_id, tenant_id) na primeira compra desse
 * cliente numa loja — client_id é sempre novo nesse caso, então a unique
 * antiga é satisfeita trivialmente pelas duas. Esta unique adicional fecha
 * a corrida no banco; StorefrontCheckoutService::checkout() captura a
 * violação e refaz a busca do link (mesmo padrão de
 * TenantSettingsRepository::findOrCreateForTenant()). Confirmado antes de
 * criar esta migration: 0 pares duplicados existentes hoje.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('final_customer_tenant_links', function (Blueprint $table) {
            $table->unique(['final_customer_id', 'tenant_id'], 'uniq_final_customer_tenant_link');
        });
    }

    public function down(): void
    {
        Schema::table('final_customer_tenant_links', function (Blueprint $table) {
            $table->dropUnique('uniq_final_customer_tenant_link');
        });
    }
};
