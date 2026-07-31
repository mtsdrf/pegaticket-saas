<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesa física do salão no fluxo presencial legado. Uma mesa pode ter mais
 * de uma comanda aberta simultânea (divisão por pessoa) — por isso o status
 * da mesa é gerido no fechamento da última comanda, não 1:1 com comanda.
 * tenant-scoped.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            $table->string('area')->nullable();
            $table->unsignedInteger('seats')->nullable();
            $table->string('status', 20)->default('free')->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'label'], 'uniq_tenant_table_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
