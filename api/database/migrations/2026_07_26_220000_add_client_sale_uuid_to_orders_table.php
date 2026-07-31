<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('client_sale_uuid')->nullable()->after('operated_by');
            $table->unique(['tenant_id', 'client_sale_uuid'], 'orders_tenant_client_sale_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_tenant_client_sale_uuid_unique');
            $table->dropColumn('client_sale_uuid');
        });
    }
};
