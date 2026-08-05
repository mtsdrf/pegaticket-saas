<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal de venda (roadmap "cotas por canal") — distinto de `origin`
 * (que só marca storefront/staff): `channel` também distingue `affiliate`
 * (venda com affiliate_id atribuído, mesmo passando pelo checkout
 * público). Nullable/populado só nos fluxos que já sabem identificar o
 * canal (App\Models\Sale\Sale::resolveChannel()) — vendas antigas ficam
 * null, sem regressão. Ver TicketTypeChannelQuotaService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('channel', 20)->nullable()->after('origin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
