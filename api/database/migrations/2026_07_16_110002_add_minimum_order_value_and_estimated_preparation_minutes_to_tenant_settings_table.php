<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->decimal('minimum_order_value', 10, 2)->nullable()->after('block_order_without_stock');
            $table->unsignedSmallInteger('estimated_preparation_minutes')->nullable()->after('minimum_order_value');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['minimum_order_value', 'estimated_preparation_minutes']);
        });
    }
};
