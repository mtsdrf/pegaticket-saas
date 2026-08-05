<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revenda oficial verificada (roadmap Fase 4 — extensão da transferência
 * simples já existente em TicketService::transfer()). Titular de um
 * Ticket `ativo` anuncia por um preço <= o pago originalmente
 * (sale_items.unit_price do item de origem, congelado em
 * `original_unit_price` no momento do anúncio — preço do ingresso pode
 * mudar depois); outro FinalCustomer compra. Fechamento reaproveita
 * TicketService::transfer() (mesma rotação de code/qr_token) — não há
 * lógica de transferência/QR própria aqui.
 *
 * seller_final_customer_id nullable: hoje todo Ticket vem de uma Sale com
 * final_customer_id (bilheteria online) — mas venda de balcão pode gerar
 * Sale sem FinalCustomer vinculado (comprador anônimo). Nesse caso o
 * ingresso não é elegível pra revenda (sem conta no Portal pra receber o
 * repasse) — Service valida antes de criar o anúncio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_resale_listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('seller_final_customer_id')->nullable()->constrained('final_customers')->nullOnDelete();
            $table->foreignId('buyer_final_customer_id')->nullable()->constrained('final_customers')->nullOnDelete();

            $table->decimal('original_unit_price', 10, 2);
            $table->decimal('asking_price', 10, 2);

            $table->enum('status', ['listado', 'vendido', 'cancelado'])->default('listado')->index();

            // Repasse financeiro ao vendedor (decisão: crédito registrado +
            // liberação manual do staff do tenant, NÃO automática — ver
            // TicketResaleService::purchase()/TicketResalePayoutController).
            $table->decimal('seller_payout_amount', 10, 2)->nullable();
            $table->enum('seller_payout_status', ['nao_aplicavel', 'pendente_liberacao', 'liberado'])->default('nao_aplicavel');
            $table->timestamp('seller_payout_released_at')->nullable();
            $table->unsignedBigInteger('seller_payout_released_by')->nullable();

            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

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
        Schema::dropIfExists('ticket_resale_listings');
    }
};
