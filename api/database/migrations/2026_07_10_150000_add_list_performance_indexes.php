<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at', 'name'], 'clients_tenant_deleted_name_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at', 'id'], 'sales_tenant_deleted_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_deleted_id_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_tenant_deleted_name_idx');
        });
    }
};
