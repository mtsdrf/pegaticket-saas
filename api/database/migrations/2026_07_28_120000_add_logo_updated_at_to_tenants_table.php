<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('logo_updated_at')->nullable()->after('logo_mime');
        });

        DB::table('tenants')
            ->where(function ($query) {
                $query->whereNotNull('logo_data')
                    ->orWhereNotNull('logo_path');
            })
            ->update([
                'logo_updated_at' => DB::raw('COALESCE(logo_updated_at, updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('logo_updated_at');
        });
    }
};
