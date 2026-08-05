<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log imutável de e-mail transacional (hub de comunicação, roadmap).
        // Sem uuid/soft delete/updated_at: nunca exposto por rota de detalhe
        // nem editado/excluído por API, só listado — mesmo espírito de
        // audit_logs. tenant_id nullable: PasswordResetMail/
        // UserEmailConfirmationMail (users, sem tenant_id na tabela) e
        // PortalOtpMail (final_customers, também sem tenant_id) não têm
        // tenant claro no momento do envio.
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();

            $table->string('type', 50)->index();
            $table->string('recipient_email');
            $table->string('status', 10)->index();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
