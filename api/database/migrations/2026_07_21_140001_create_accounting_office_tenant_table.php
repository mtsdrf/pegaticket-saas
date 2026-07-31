<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo N:N entre escritório de contabilidade e tenant (roadmap 2C), com
 * fluxo de aprovação INVERSO ao convite de funcionário: quem SOLICITA é o
 * contador (status=pending), quem APROVA é o dono do tenant (define `scopes`
 * concedidos, status=approved) e pode revogar (status=revoked). Modelado no
 * precedente de `final_customer_tenant_links` (identidade global + vínculo
 * por tenant), não em TenantUser. `approved_by` é o user (staff) do tenant
 * que aprovou. Unique (accounting_office_id, tenant_id): um vínculo por par
 * — re-solicitar após revogação reusa a mesma linha (volta a pending).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_office_tenant', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('accounting_office_id')
                ->constrained('accounting_offices')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending')->index();
            $table->json('scopes')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->index();

            $table->timestamps();

            $table->unique(['accounting_office_id', 'tenant_id'], 'uniq_accounting_office_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_office_tenant');
    }
};
