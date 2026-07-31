<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento passa a ser polimórfico (roadmap 2A). A tabela `payments` da
 * Onda 1 ligava só a `invoices` (cobrança da Maskats pro tenant). Agora o
 * mesmo registro serve também para pagamento de PEDIDO (cliente final →
 * tenant, Modelo A sem custódia). Troca `invoice_id` fixo por
 * `payable_type`/`payable_id` (padrão `morphs()` do Laravel), mais idiomático
 * e extensível que um par de FKs nullable com check de aplicação.
 *
 * Seguro sobre dado real: ainda não há pagamento gravado em produção local.
 * Ainda assim faz backfill defensivo (invoice_id existente → payable=Invoice)
 * antes de derrubar a coluna, para não perder vínculo caso rode em base já
 * populada.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->nullableMorphs('payable');
        });

        // Backfill defensivo: qualquer pagamento pré-existente aponta para
        // sua fatura via a nova coluna polimórfica.
        DB::table('payments')->whereNotNull('invoice_id')->update([
            'payable_type' => \App\Models\Subscription\Invoice::class,
            'payable_id' => DB::raw('invoice_id'),
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('uuid')->constrained()->cascadeOnDelete();
        });

        DB::table('payments')
            ->where('payable_type', \App\Models\Subscription\Invoice::class)
            ->update(['invoice_id' => DB::raw('payable_id')]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropMorphs('payable');
        });
    }
};
