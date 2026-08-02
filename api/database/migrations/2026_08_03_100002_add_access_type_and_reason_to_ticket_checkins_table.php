<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_checkins', function (Blueprint $table) {
            $table->string('access_type', 20)->default('entrada')->after('result')->index();
            $table->text('reason')->nullable()->after('access_type');
        });

        DB::table('ticket_checkins')
            ->whereNull('deleted_at')
            ->update(['access_type' => 'entrada']);
    }

    public function down(): void
    {
        Schema::table('ticket_checkins', function (Blueprint $table) {
            $table->dropColumn(['access_type', 'reason']);
        });
    }
};
