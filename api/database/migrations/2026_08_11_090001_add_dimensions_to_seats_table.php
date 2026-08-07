<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->decimal('width', 10, 2)->nullable()->after('pos_y');
            $table->decimal('height', 10, 2)->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
