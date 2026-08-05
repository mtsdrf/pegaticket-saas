<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Override configurável de assunto/corpo por tipo de e-mail
        // transacional (mesma chave `type` usada em
        // CommunicationDispatcherService/CommunicationLog). tenant_id
        // nullable: tipos de plataforma (password_reset/portal_otp/
        // email_confirmation) usam tenant_id null (global), mas hoje não
        // têm CRUD que grave nele (ver EmailTemplateService::CUSTOMIZABLE_TYPES)
        // — decisão de manter fluxo de segurança fora do editor do tenant.
        // subject/body_html nullable: sem override, o Mailable usa o
        // texto/view hardcoded atual (fallback nunca quebra o envio).
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->string('type', 50)->index();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // MySQL trata múltiplos NULL como distintos em unique index, o
            // que quebraria a unicidade global (tenant_id null) exigida
            // pela regra de negócio. Coluna gerada resolve isso tratando
            // null como 0 só para fins de unicidade.
            $table->unsignedBigInteger('tenant_scope')
                ->storedAs('COALESCE(tenant_id, 0)');

            $table->unique(['tenant_scope', 'type'], 'uniq_email_template_tenant_type');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
