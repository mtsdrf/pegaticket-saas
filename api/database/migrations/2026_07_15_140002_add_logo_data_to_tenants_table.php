<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ver 2026_07_15_140000_add_avatar_data_to_users_table.php — mesmo padrão
 * (LONGBLOB via SQL bruto, mysql only; logo_path mantido por reversibilidade).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->binary('logo_data')->nullable()->after('logo_path');
            $table->string('logo_mime')->nullable()->after('logo_data');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tenants MODIFY logo_data LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_data', 'logo_mime']);
        });
    }
};
