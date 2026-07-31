<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transition_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants', 'id', 'wtl_tenant_fk');
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'wtl_user_fk')->nullOnDelete();
            $table->string('workflow_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid');
            $table->string('from_stage', 60)->nullable();
            $table->string('to_stage', 60);
            $table->string('transition_type', 30);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['tenant_id', 'workflow_type', 'moved_at'], 'wtl_tenant_flow_moved_idx');
            $table->index(['workflow_type', 'entity_uuid'], 'wtl_flow_entity_uuid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transition_logs');
    }
};
