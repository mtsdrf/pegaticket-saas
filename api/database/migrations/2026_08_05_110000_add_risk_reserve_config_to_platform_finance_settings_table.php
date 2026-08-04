<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_finance_settings', function (Blueprint $table) {
            $table->decimal('extra_reserve_percentage', 5, 2)->default(0)->after('extra_reserve_enabled');
            $table->unsignedSmallInteger('extra_reserve_release_offset_days')->default(30)->after('extra_reserve_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('platform_finance_settings', function (Blueprint $table) {
            $table->dropColumn(['extra_reserve_percentage', 'extra_reserve_release_offset_days']);
        });
    }
};
