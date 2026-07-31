<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->uuid('client_comanda_uuid')->nullable()->after('order_id');
            $table->unique(['tenant_id', 'client_comanda_uuid'], 'comandas_tenant_client_comanda_uuid_unique');
        });

        Schema::table('comanda_items', function (Blueprint $table) {
            $table->uuid('client_item_uuid')->nullable()->after('cancelled_reason');
            $table->unique(['tenant_id', 'client_item_uuid'], 'comanda_items_tenant_client_item_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('comanda_items', function (Blueprint $table) {
            $table->dropUnique('comanda_items_tenant_client_item_uuid_unique');
            $table->dropColumn('client_item_uuid');
        });

        Schema::table('comandas', function (Blueprint $table) {
            $table->dropUnique('comandas_tenant_client_comanda_uuid_unique');
            $table->dropColumn('client_comanda_uuid');
        });
    }
};
