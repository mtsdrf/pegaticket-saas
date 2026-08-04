<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_finance_settings', function (Blueprint $table) {
            $table->string('pagbank_primary_account_id', 80)
                ->nullable()
                ->after('extra_reserve_enabled');
        });

        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('pagbank_receiver_account_id', 80)
                ->nullable()
                ->after('pagbank_access_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('pagbank_receiver_account_id');
        });

        Schema::table('platform_finance_settings', function (Blueprint $table) {
            $table->dropColumn('pagbank_primary_account_id');
        });
    }
};
