<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_refund_id')
                ->nullable()
                ->after('sale_id')
                ->index();

            $table->foreign('sale_refund_id')
                ->references('id')
                ->on('sale_refunds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlement_adjustments', function (Blueprint $table) {
            $table->dropForeign(['sale_refund_id']);
            $table->dropColumn('sale_refund_id');
        });
    }
};
