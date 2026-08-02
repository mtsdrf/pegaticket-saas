<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('status', 20)->default('confirmed')->after('cancelled_at')->index();
            $table->string('origin', 20)->default('staff')->after('status')->index();
            $table->boolean('stock_reserved')->default(true)->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['status', 'origin', 'stock_reserved']);
        });
    }
};
