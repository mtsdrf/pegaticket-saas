<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index(); // externo
            $table->unsignedBigInteger('user_id')->index();

            $table->string('token_hash', 128)->unique(); // sha512
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();

            $table->string('user_agent_hash', 128)->nullable()->index();
            $table->string('ip_hash', 128)->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('token_blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 64)->unique()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_blacklists');
        Schema::dropIfExists('refresh_tokens');
    }
};