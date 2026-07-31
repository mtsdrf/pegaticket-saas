<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ver 2026_07_15_140000_add_avatar_data_to_users_table.php — mesmo padrão
 * (LONGBLOB via SQL bruto, mysql only; image_path mantido por reversibilidade).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->binary('image_data')->nullable()->after('image_path');
            $table->string('image_mime')->nullable()->after('image_data');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY image_data LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_data', 'image_mime']);
        });
    }
};
