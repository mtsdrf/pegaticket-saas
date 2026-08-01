<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket = ingresso digital emitido (spec 5.15). Emitido automaticamente
 * por TicketIssuanceService quando a Sale (order_items.order_id) é
 * confirmada/paga — nunca criado manualmente via formulário. `order_item_id`
 * é a origem (SaleItem::table = order_items); `ticket_type_id` é redundante
 * mas evita join extra em toda leitura de portaria/portal. `seat_id`
 * nullable: mapa de assento é fase futura, FK já pronta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->cascadeOnDelete();

            $table->foreignId('seat_id')
                ->nullable()
                ->constrained('seats')
                ->nullOnDelete();

            // Código curto pro comprador informar verbalmente/digitar —
            // alfabeto sem 0/O/1/I (ambiguidade visual).
            $table->string('code', 8)->unique();

            // Token do QR Code — Str::random(40), não previsível, não
            // derivado de dado do usuário (spec: "não deve expor dados
            // sensíveis" / "difícil de adivinhar").
            $table->string('qr_token', 64)->unique()->index();

            $table->string('attendee_name')->nullable();
            $table->string('attendee_document')->nullable();

            $table->string('status', 20)->default('pendente')->index();
            $table->dateTime('issued_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
