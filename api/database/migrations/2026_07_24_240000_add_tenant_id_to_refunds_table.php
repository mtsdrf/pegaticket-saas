<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Painel de reembolsos do proprietário do tenant (roadmap 2026-07-24,
 * `GET /subscription/refunds`). `refunds` não carregava `tenant_id` direto
 * (convenção do projeto é toda tabela de domínio ter `tenant_id`
 * denormalizado — ver `webhook_deliveries`); o vínculo só existia
 * indiretamente via `payment_id` -> `payments.payable_type/payable_id` ->
 * `orders.tenant_id`/`invoices.tenant_id`, e nem sempre existe (Refund de
 * arrependimento em trial pode nascer com `payment_id` nulo — ver
 * SubscriptionService::requestWithdrawal). Sem `tenant_id` próprio não
 * havia forma segura de escopar por tenant nesse caso. Nullable porque
 * segue o padrão de soft-safe/coluna de FK opcional já usado em `refunds`
 * (`payment_id`), mas os 3 pontos de criação (OrderPaymentService,
 * SubscriptionService, ExternalPaymentReviewRegistrar) passam a preencher
 * sempre. Backfill best-effort para linhas antigas via `payment_id`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Subquery correlacionada (não `UPDATE ... JOIN`, exclusivo do MySQL)
        // para o backfill rodar igual em MySQL (produção) e SQLite (testes).
        DB::statement(
            <<<SQL
            UPDATE refunds
            SET tenant_id = (
                SELECT COALESCE(
                    (SELECT orders.tenant_id FROM payments
                        INNER JOIN orders ON orders.id = payments.payable_id
                        WHERE payments.id = refunds.payment_id AND payments.payable_type = ?),
                    (SELECT invoices.tenant_id FROM payments
                        INNER JOIN invoices ON invoices.id = payments.payable_id
                        WHERE payments.id = refunds.payment_id AND payments.payable_type = ?)
                )
            )
            WHERE refunds.tenant_id IS NULL AND refunds.payment_id IS NOT NULL
            SQL,
            [\App\Models\Order\Order::class, \App\Models\Subscription\Invoice::class]
        );

        Schema::table('refunds', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_refunds_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('idx_refunds_tenant_created');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
