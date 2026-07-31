<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central de chamados nativa (roadmap A4, item 17). Reaproveita o mesmo
 * padrão de dado já usado em `accounting_request_messages` (anexo opcional
 * via Storage::disk('public'), status simples). `diagnostics` (json) é
 * preenchido no momento da criação quando o cliente marca
 * `include_diagnostics=true` — snapshot do plano, últimas linhas de
 * auditoria, checagem de fila e versão da aplicação (ver
 * SupportTicketService::buildDiagnostics). `created_by` (de BaseModel) já
 * identifica o autor, sem coluna extra de user.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('subject');
            $table->text('description');
            $table->string('status', 20)->default('open')->index();

            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->json('diagnostics')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'created_at'], 'idx_support_tickets_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
