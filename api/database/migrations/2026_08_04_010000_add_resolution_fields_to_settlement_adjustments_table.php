<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_adjustments', function (Blueprint $table) {
            $table->string('resolution_type', 40)->nullable()->after('status')->index();
            $table->text('resolution_notes')->nullable()->after('resolution_type');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolution_notes')->index();
            $table->dateTime('resolved_at')->nullable()->after('resolved_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('settlement_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'resolution_type',
                'resolution_notes',
                'resolved_by',
                'resolved_at',
            ]);
        });
    }
};
