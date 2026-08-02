<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Guarda o ÚLTIMO sales.codigo emitido pelo tenant (não o
            // próximo) — OrderService::create() faz increment() ANTES de
            // ler o valor (atômico), então o codigo do pedido = valor já
            // incrementado. Default 999 pra que o PRIMEIRO pedido do
            // tenant (increment 999->1000) receba codigo "1000". Ver
            // também o comando sales:backfill-codigo (mesma convenção).
            $table->unsignedInteger('next_sale_code')->default(999);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('next_sale_code');
        });
    }
};
