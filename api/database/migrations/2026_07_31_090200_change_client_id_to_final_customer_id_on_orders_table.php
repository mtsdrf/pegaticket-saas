<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FinalCustomer absorve Client (decisão 2026-07-31): sales passa a
 * referenciar final_customers diretamente. Banco de dev zerado, sem
 * migração de dado necessária.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('final_customer_id')
                ->after('tenant_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['final_customer_id']);
            $table->dropColumn('final_customer_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('client_id')->after('tenant_id')->constrained('clients')->cascadeOnDelete();
        });
    }
};
