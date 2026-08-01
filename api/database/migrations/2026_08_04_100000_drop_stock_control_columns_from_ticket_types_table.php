<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resíduo do produto antigo "Maskats" (retail/estoque físico). Nunca são
 * aceitos em CreateTicketTypeDTO/UpdateTicketTypeDTO/validação/Service —
 * disponibilidade de ingresso já é controlada por TicketBatch.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $columns = [
                'min_stock',
                'max_stock',
                'reorder_point',
                'reorder_qty',
                'is_lot_controlled',
                'is_expiry_controlled',
                'is_serial_controlled',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('ticket_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Migração destrutiva: sem rollback automático.
    }
};
