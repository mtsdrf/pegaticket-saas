<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_role_id')->index();
            $table->unsignedBigInteger('functionality_id')->index();
            $table->unsignedBigInteger('action_id')->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_role_id', 'functionality_id', 'action_id'],
                'uniq_tenant_role_permission'
            );

            $table->foreign('tenant_role_id')
                ->references('id')
                ->on('tenant_roles')
                ->cascadeOnDelete();

            $table->foreign('functionality_id')
                ->references('id')
                ->on('functionalities');

            $table->foreign('action_id')
                ->references('id')
                ->on('actions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_role_permissions');
    }
};