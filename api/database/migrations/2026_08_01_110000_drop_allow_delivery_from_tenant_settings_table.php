<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tenant_settings') && Schema::hasColumn('tenant_settings', 'allow_delivery')) {
            Schema::table('tenant_settings', function (Blueprint $table) {
                $table->dropColumn('allow_delivery');
            });
        }
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
