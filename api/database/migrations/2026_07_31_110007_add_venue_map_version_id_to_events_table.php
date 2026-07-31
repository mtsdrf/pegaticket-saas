<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_map_version_id')
                ->nullable()
                ->after('event_category_id')
                ->constrained('venue_map_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['venue_map_version_id']);
            $table->dropColumn('venue_map_version_id');
        });
    }
};
