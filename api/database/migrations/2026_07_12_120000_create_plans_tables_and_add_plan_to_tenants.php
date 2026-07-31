<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_functionalities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedBigInteger('functionality_id')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('functionality_id')->references('id')->on('functionalities')->cascadeOnDelete();
            $table->unique(['plan_id', 'functionality_id'], 'uniq_plan_functionality');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('slug')->index();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });

        $now = now();

        $planIds = [];
        foreach ([
            ['name' => 'Prata', 'slug' => 'prata', 'description' => 'Plano base com operação completa da empresa.', 'sort_order' => 10],
            ['name' => 'Ouro', 'slug' => 'ouro', 'description' => 'Plano com operação completa e acesso aos relatórios.', 'sort_order' => 20],
            ['name' => 'Diamante', 'slug' => 'diamante', 'description' => 'Plano avançado preparado para receber as funcionalidades premium do produto.', 'sort_order' => 30],
        ] as $plan) {
            $existingId = DB::table('plans')->where('slug', $plan['slug'])->value('id');
            if ($existingId) {
                $planIds[$plan['slug']] = $existingId;
                continue;
            }

            $planIds[$plan['slug']] = DB::table('plans')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $plan['name'],
                'slug' => $plan['slug'],
                'description' => $plan['description'],
                'sort_order' => $plan['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $functionalityIds = DB::table('functionalities')
            ->whereNull('deleted_at')
            ->pluck('id', 'slug');

        $planFunctionalities = [
            'prata' => [
                'users',
                'tenant_roles',
                'tenant_users',
                'estados',
                'cidades',
                'bairros',
                'product_categories',
                'product_types',
                'enderecos',
                'clients',
                'products',
                'orders',
                'reports',
            ],
            'ouro' => [
                'users',
                'tenant_roles',
                'tenant_users',
                'estados',
                'cidades',
                'bairros',
                'product_categories',
                'product_types',
                'enderecos',
                'clients',
                'products',
                'stock_locations',
                'stock',
                'orders',
                'reports',
            ],
            'diamante' => [
                'users',
                'tenant_roles',
                'tenant_users',
                'estados',
                'cidades',
                'bairros',
                'product_categories',
                'product_types',
                'enderecos',
                'clients',
                'products',
                'stock_locations',
                'stock',
                'orders',
                'reports',
            ],
        ];

        foreach ($planFunctionalities as $planSlug => $slugs) {
            $planId = $planIds[$planSlug] ?? null;
            if (!$planId) {
                continue;
            }

            foreach ($slugs as $slug) {
                $functionalityId = $functionalityIds[$slug] ?? null;

                if (!$functionalityId) {
                    continue;
                }

                $exists = DB::table('plan_functionalities')
                    ->where('plan_id', $planId)
                    ->where('functionality_id', $functionalityId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('plan_functionalities')->insert([
                    'uuid' => (string) Str::uuid(),
                    'plan_id' => $planId,
                    'functionality_id' => $functionalityId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (isset($planIds['prata'])) {
            DB::table('tenants')
                ->whereNull('plan_id')
                ->update([
                    'plan_id' => $planIds['prata'],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });

        Schema::dropIfExists('plan_functionalities');
        Schema::dropIfExists('plans');
    }
};
