<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_reset_token_hash')->nullable()->unique()->after('pending_email_expires_at');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_reset_token_hash', 'password_reset_expires_at']);
        });
    }
};
