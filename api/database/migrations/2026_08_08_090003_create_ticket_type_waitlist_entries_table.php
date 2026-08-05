<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de espera (roadmap inventário) — cliente se cadastra num
 * TicketType esgotado (available_quantity=0, calculado dinamicamente por
 * StorefrontHoldService::resolveTicketTypeAvailability(), não é coluna) e
 * é avisado por e-mail quando voltar a ter vaga. `notified_at` marca o
 * envio pra NotifyTicketTypeWaitlistCommand não notificar duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_type_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('email');
            $table->unsignedInteger('quantity_desired')->default(1);
            $table->timestamp('notified_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['ticket_type_id', 'email'], 'uniq_ticket_type_waitlist_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_type_waitlist_entries');
    }
};
