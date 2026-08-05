<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relatórios agendados básicos (roadmap A2) — staff assina um e-mail
 * (próprio ou customizado) para receber o resumo dos KPIs do Home
 * (ReportService::indicators()) diária ou semanalmente. Sem ZIP, sem
 * múltiplos relatórios por assinatura (Parte VI completa da spec fora de
 * escopo, ver roadmap 5.5/7.1). `last_sent_at` decide se a assinatura está
 * em dia com a frequência (ver SendScheduledReportSummariesCommand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email');
            $table->enum('frequency', ['daily', 'weekly']);
            $table->timestamp('last_sent_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'frequency'], 'idx_scheduled_report_subs_tenant_frequency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_subscriptions');
    }
};
