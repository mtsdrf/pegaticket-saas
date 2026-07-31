<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->unsignedInteger('processing_attempts')->default(0)->after('status');
            $table->timestamp('last_attempted_at')->nullable()->after('processed_at');
            $table->timestamp('dead_lettered_at')->nullable()->after('last_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->dropColumn(['processing_attempts', 'last_attempted_at', 'dead_lettered_at']);
        });
    }
};
