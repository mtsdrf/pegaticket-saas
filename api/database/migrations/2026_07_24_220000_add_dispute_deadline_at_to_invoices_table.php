<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prazo de contestação (chargeback/claim) da fatura, quando o Mercado Pago
 * informar essa data no payload. `invoices.status` ganha o valor livre
 * "disputed" (coluna já é string(20) sem enum/constraint no banco) — ver
 * PaymentWebhookController::handleChargeback/handleClaim e
 * InvoicePaymentService::registerDisputedPayment.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('dispute_deadline_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('dispute_deadline_at');
        });
    }
};
