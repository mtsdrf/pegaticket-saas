<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('avatar_updated_at')->nullable()->after('avatar_mime');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('image_updated_at')->nullable()->after('image_mime');
        });

        DB::table('users')
            ->where(function ($query) {
                $query->whereNotNull('avatar_data')
                    ->orWhereNotNull('avatar_path');
            })
            ->update([
                'avatar_updated_at' => DB::raw('COALESCE(avatar_updated_at, updated_at, created_at)'),
            ]);

        DB::table('products')
            ->where(function ($query) {
                $query->whereNotNull('image_data')
                    ->orWhereNotNull('image_path');
            })
            ->update([
                'image_updated_at' => DB::raw('COALESCE(image_updated_at, updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_updated_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_updated_at');
        });
    }
};
