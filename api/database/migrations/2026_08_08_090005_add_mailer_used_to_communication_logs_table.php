<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            // Qual mailer nomeado (config/mail.php) efetivamente entregou
            // (ou tentou entregar) o e-mail — default quando enviado pelo
            // mailer padrão, fallback quando o padrão falhou e o
            // secundário (MAIL_MAILER_FALLBACK) foi usado. Nulo em
            // registros antigos (pré-fallback) e quando nem chega a tentar
            // enviar.
            $table->string('mailer_used', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->dropColumn('mailer_used');
        });
    }
};
