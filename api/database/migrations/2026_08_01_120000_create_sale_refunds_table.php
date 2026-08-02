<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estorno externo (spec 5.14/11.3) — o clube estorna manualmente no
 * PagBank, fora do sistema; este registro só documenta o que já
 * aconteceu e dispara os efeitos internos (invalidar tickets, liberar
 * lugar se o operador escolher). NÃO é o mesmo domínio de
 * App\Models\Subscription\Refund (tabela `refunds`), que é o rail de
 * ASSINATURA clube->PegaTicket via Mercado Pago — domínios distintos,
 * sem reaproveitamento de tabela/model.
 *
 * `operator` não tem coluna própria: `created_by` (preenchido
 * automaticamente por BaseModel::booted() a partir do usuário
 * autenticado) já cobre "operador (auto = usuário autenticado)" da spec.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            $table->string('type', 10)->index(); // total|parcial
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->dateTime('refunded_at');
            $table->string('external_reference')->nullable();

            // Comprovante opcional, via MediaStorageService (mesmo padrão
            // de path por tenant já usado por Event/TicketType/Venue) —
            // disco 'sale_refund' é privado (ver config/media.php), nunca
            // servido por URL pública direta: só via
            // SaleRefundController::receipt(), tenant+perm gated.
            $table->string('receipt_path')->nullable();
            $table->string('receipt_mime')->nullable();

            $table->text('notes')->nullable();

            // Decisão explícita do operador (spec item 7) — ver
            // SaleRefundService sobre o que "liberar" significa
            // tecnicamente (seat_id nulado no ticket/item afetado).
            $table->boolean('release_seats')->default(false);

            $table->string('status', 20)->default('registrado')->index();

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
        Schema::dropIfExists('sale_refunds');
    }
};
