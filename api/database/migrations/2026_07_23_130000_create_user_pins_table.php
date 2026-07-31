<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIN individual por operador (roadmap A4, item 15) — login rápido de um
 * funcionário DENTRO de uma sessão de staff já autenticada via JWT, só para
 * identificar quem operou uma venda de PDV/Balcão (auditoria de operador em
 * terminal compartilhado). NÃO é autenticação primária — não substitui o
 * JWT nem cria uma 4ª identidade (ver security-standards.md sobre as 3
 * identidades JWT existentes).
 *
 * Tabela própria (não coluna em `users`) porque o mesmo usuário pode operar
 * em tenants diferentes com PINs diferentes — users é global, o PIN é
 * por vínculo tenant+user. `pin_hash` é hash determinístico (mesmo padrão
 * de `final_customer_otps.code_hash`, ver PortalAuthService), não bcrypt —
 * precisa de lookup direto por tenant_id+pin_hash sem saber o usuário
 * antes (bcrypt/argon não permitem esse lookup, o salt é por linha).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_pins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('pin_hash', 64);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id'], 'uniq_tenant_user_pin');
            $table->unique(['tenant_id', 'pin_hash'], 'uniq_tenant_pin_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pins');
    }
};
