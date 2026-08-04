<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_id')
                ->nullable()
                ->after('sale_refund_id')
                ->index();

            $table->foreign('refund_id')
                ->references('id')
                ->on('refunds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlement_adjustments', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
            $table->dropColumn('refund_id');
        });
    }
};
