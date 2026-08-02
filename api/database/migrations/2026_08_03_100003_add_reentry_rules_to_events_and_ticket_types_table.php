<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('reentry_enabled')->default(false)->after('status');
            $table->unsignedInteger('max_reentries')->nullable()->after('reentry_enabled');
            $table->unsignedInteger('reentry_cooldown_minutes')->nullable()->after('max_reentries');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->boolean('reentry_enabled')->nullable()->after('status');
            $table->unsignedInteger('max_reentries')->nullable()->after('reentry_enabled');
            $table->unsignedInteger('reentry_cooldown_minutes')->nullable()->after('max_reentries');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['reentry_enabled', 'max_reentries', 'reentry_cooldown_minutes']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['reentry_enabled', 'max_reentries', 'reentry_cooldown_minutes']);
        });
    }
};
