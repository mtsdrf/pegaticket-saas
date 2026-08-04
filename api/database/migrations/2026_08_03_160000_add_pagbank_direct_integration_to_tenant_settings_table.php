<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('pagbank_integration_mode', 20)
                ->default('disabled')
                ->after('payment_pix_key');
            $table->string('pagbank_environment', 20)
                ->default('sandbox')
                ->after('pagbank_integration_mode');
            $table->text('pagbank_access_token')
                ->nullable()
                ->after('pagbank_environment');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pagbank_integration_mode',
                'pagbank_environment',
                'pagbank_access_token',
            ]);
        });
    }
};
