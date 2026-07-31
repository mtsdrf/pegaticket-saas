<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FinalCustomer absorve Client (decisão 2026-07-31): os campos por-tenant
 * que hoje vivem em `clients` passam a viver na própria linha do link
 * (final_customer_tenant_links), que já é o registro por-tenant do cliente
 * final. `client_id`/FK para `clients` deixa de existir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('final_customer_tenant_links', function (Blueprint $table) {
            $table->dropUnique('uniq_final_customer_client_link');
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');

            $table->foreignId('endereco_id')->nullable()->after('tenant_id')->constrained('enderecos')->nullOnDelete();
            $table->string('cpf_cnpj', 14)->nullable()->after('endereco_id');
            $table->string('ie')->nullable()->after('cpf_cnpj');
            $table->string('ie_indicator', 20)->nullable()->after('ie');
            $table->string('phone_primary')->nullable()->after('ie_indicator');
            $table->string('phone_secondary')->nullable()->after('phone_primary');
            $table->text('notes')->nullable()->after('phone_secondary');
            $table->boolean('is_trusted')->default(true)->after('notes');
            $table->boolean('is_active')->default(true)->index()->after('is_trusted');
        });
    }

    public function down(): void
    {
        Schema::table('final_customer_tenant_links', function (Blueprint $table) {
            $table->dropColumn([
                'endereco_id',
                'cpf_cnpj',
                'ie',
                'ie_indicator',
                'phone_primary',
                'phone_secondary',
                'notes',
                'is_trusted',
                'is_active',
            ]);

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unique(['final_customer_id', 'client_id'], 'uniq_final_customer_client_link');
        });
    }
};
