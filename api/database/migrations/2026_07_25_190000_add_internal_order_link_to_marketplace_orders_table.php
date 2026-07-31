<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->foreignId('internal_order_id')->nullable()->after('marketplace_merchant_id')->constrained('orders')->nullOnDelete();
            $table->timestamp('imported_at')->nullable()->after('last_synced_at');
            $table->text('import_error_message')->nullable()->after('imported_at');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_order_id');
            $table->dropColumn(['imported_at', 'import_error_message']);
        });
    }
};
