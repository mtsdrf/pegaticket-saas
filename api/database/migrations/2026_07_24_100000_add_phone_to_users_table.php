<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telefone do usuário STAFF (roadmap "Empresa" — assinatura/cobrança).
 * Decisão de negócio: o payer da cobrança de assinatura (Mercado Pago) é
 * sempre o PROPRIETÁRIO do tenant (User), não a empresa (tenants.phone é
 * contato do estabelecimento, dado diferente). `users` não tinha nenhuma
 * coluna de telefone antes desta migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
