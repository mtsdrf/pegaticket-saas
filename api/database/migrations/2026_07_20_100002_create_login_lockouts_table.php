<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle de bloqueio de login por e-mail (roadmap 1A). Tabela própria em
 * vez de colunas em `users` de propósito: não acopla o controle de força
 * bruta ao model de usuário e não vaza se o e-mail existe (uma linha aqui
 * pode existir para um e-mail que nem tem conta). AuthService::login
 * incrementa a cada falha de credencial e bloqueia por 15min ao atingir 5.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('login_lockouts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_lockouts');
    }
};
