<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aceite de um documento legal por um usuário (roadmap 1A). Log imutável:
 * mantém `uuid` público, mas dispensa soft delete (um aceite nunca é
 * "removido" — é um fato histórico). Gravado por SelfSignupService no
 * momento do cadastro, capturando o IP do request.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
