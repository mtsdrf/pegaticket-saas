<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TicketCheckin = registro de controle de acesso/portaria (spec 5.16).
 * Um Ticket pode ter mais de um registro (tentativas recusadas também são
 * gravadas — ver CheckinService::checkin — só a leitura "valido" muda
 * Ticket.status para utilizado). `gate_name` é texto livre por enquanto
 * (sem entidade própria de "portaria" nesta rodada).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_checkins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            $table->string('gate_name')->nullable();

            $table->unsignedBigInteger('operator_id')->nullable()->index();

            $table->dateTime('checked_in_at');
            $table->string('result', 20)->index();
            $table->string('device_info')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('operator_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_checkins');
    }
};
