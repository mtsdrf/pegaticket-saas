<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('group_id')->index();
            $table->unsignedBigInteger('functionality_id')->index();
            $table->unsignedBigInteger('action_id')->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['group_id', 'functionality_id', 'action_id'], 'uniq_group_perm');

            $table->foreign('group_id')->references('id')->on('groups');
            $table->foreign('functionality_id')->references('id')->on('functionalities');
            $table->foreign('action_id')->references('id')->on('actions');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('group_permissions');
    }
};