<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_user_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_role_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->index();
            $table->string('token_hash')->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // Não é unique composta: um e-mail pode ter mais de um convite
            // histórico na mesma tenant (expirado/aceito). A checagem de
            // "já existe convite pendente" é feita em nível de aplicação
            // (TenantUserInviteService), não é representável como unique
            // parcial simples no MySQL. Índice só para acelerar essa busca.
            $table->index(['tenant_id', 'email'], 'idx_tenant_user_invite_tenant_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_invites');
    }
};
