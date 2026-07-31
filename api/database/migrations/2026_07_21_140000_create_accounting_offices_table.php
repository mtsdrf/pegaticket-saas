<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade GLOBAL do escritório de contabilidade (roadmap 2C — Módulo do
 * contador), no mesmo espírito de `final_customers`: existe ACIMA de qualquer
 * tenant (um escritório atende N empresas). Sem tenant_id/created_by/soft
 * delete de propósito — não há "ator staff" que a cria, é auto-cadastro do
 * próprio contador. TOTP é obrigatório: login só é liberado depois de
 * `totp_enabled_at` preenchido. Ver App\Models\Accounting\AccountingOffice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_offices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->string('cnpj', 14)->index();
            $table->string('company_name');
            $table->string('responsible_name');
            $table->string('email')->unique();
            $table->string('password_hash');

            $table->string('totp_secret')->nullable();
            $table->timestamp('totp_enabled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_offices');
    }
};
