<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o avatar direto no banco (LONGBLOB), não mais em disco — ver
 * architecture-decisions.md ("Upload de imagem passa a ser BLOB no banco").
 * `avatar_path` é mantido (aditivo, não usado mais) por reversibilidade.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->binary('avatar_data')->nullable()->after('avatar_path');
            $table->string('avatar_mime')->nullable()->after('avatar_data');
        });

        // $table->binary() sem tamanho vira BLOB (64KB) no MySQL — insuficiente
        // pro limite de upload validado (5MB). Força LONGBLOB via SQL bruto,
        // só quando o driver é MySQL (SQLite dos testes não tem tipos fixos
        // de tamanho, não precisa e não entende esse ALTER).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY avatar_data LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_data', 'avatar_mime']);
        });
    }
};
