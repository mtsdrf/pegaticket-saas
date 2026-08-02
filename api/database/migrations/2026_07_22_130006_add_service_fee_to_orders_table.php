<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acréscimo de taxa de serviço no Order materializado pelo fechamento de uma
 * comanda de atendimento presencial da época (roadmap legado, Fases 1+2). Espelha exatamente a coluna
 * delivery_fee já existente (acréscimo somado a total_amount, persistido em
 * coluna própria para o breakdown): mantém o total do pedido correto e as
 * formas de pagamento reconciliáveis, sem poluir delivery_fee (que é receita
 * de entrega, distinta em relatórios). default 0 preserva 100% os fluxos de
 * pedido existentes; taxa de serviço continua opcional e contextual.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('service_fee', 10, 2)->default(0)->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('service_fee');
        });
    }
};
