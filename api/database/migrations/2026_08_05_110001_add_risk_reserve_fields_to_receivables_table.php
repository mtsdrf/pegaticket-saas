<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->decimal('reserve_amount', 10, 2)->default(0)->after('net_amount');
            $table->string('reserve_status', 20)->default('none')->index()->after('reserve_amount');
            $table->dateTime('reserve_release_at')->nullable()->index()->after('reserve_status');
            $table->dateTime('reserve_released_at')->nullable()->after('reserve_release_at');
        });
    }

    public function down(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->dropColumn(['reserve_amount', 'reserve_status', 'reserve_release_at', 'reserve_released_at']);
        });
    }
};
