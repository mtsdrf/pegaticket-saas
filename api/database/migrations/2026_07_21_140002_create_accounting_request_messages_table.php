<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central de pendências tenant <-> contador (roadmap 2C): mensagens trocadas
 * dentro de um vínculo aprovado (`accounting_office_tenant_id`). `sender_type`
 * distingue quem enviou (tenant staff x escritório). `tenant_id` denormalizado
 * para escopo/consulta direta. Anexo opcional via Storage::disk('public')
 * (mesmo mecanismo já usado por imagem de produto). Sem soft delete — histórico
 * de conversa é imutável, não se "exclui" mensagem.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_request_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('accounting_office_tenant_id')
                ->constrained('accounting_office_tenant')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('sender_type'); // tenant | accounting_office
            $table->unsignedBigInteger('sender_user_id')->nullable()->index();

            $table->text('body');
            $table->date('due_date')->nullable();
            $table->string('status')->default('open')->index(); // open | answered | closed

            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->timestamps();

            $table->index(['accounting_office_tenant_id', 'created_at'], 'idx_accounting_msg_link_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_request_messages');
    }
};
