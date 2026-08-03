<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade GLOBAL do cliente final (roadmap 5.2 — Portal do cliente
 * final), sem tenant_id/created_by/deleted_by de propósito: diferente das
 * tabelas de domínio tenant-scoped cobertas por database-rules.md, essa
 * identidade existe ACIMA de qualquer tenant (a mesma pessoa pode ter
 * vendas em várias lojas). Não estende o padrão BaseModel (uuid+soft
 * delete+created_by automático) porque não há "ator staff autenticado" em
 * nenhum fluxo que a toca — é sempre o próprio cliente final, anônimo até
 * o login OTP. Ver App\Models\FinalCustomer\FinalCustomer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('final_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->string('name')->nullable();
            $table->string('email')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_customers');
    }
};
