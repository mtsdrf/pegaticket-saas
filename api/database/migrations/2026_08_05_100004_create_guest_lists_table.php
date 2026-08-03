<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Cortesias estruturadas" (roadmap Fase 4) — organizador cria uma lista
 * por evento/sessão + tipo de ingresso; cada GuestListEntry vira um
 * autoatendimento de resgate (sem conta no Portal), ver
 * GuestListRedemptionService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_session_id')->nullable()->constrained('event_sessions')->nullOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('quantity_per_entry')->default(1);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_lists');
    }
};
