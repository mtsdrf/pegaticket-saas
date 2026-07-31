<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->foreignId('event_session_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropForeign(['event_session_id']);
            $table->dropColumn('event_session_id');
        });
    }
};
