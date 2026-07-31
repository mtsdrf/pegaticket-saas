<?php

namespace Database\Seeders;

use App\Models\Plan\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitialPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Prata',
                'slug' => 'prata',
                'description' => 'Plano base com a fundação operacional da plataforma.',
                'sort_order' => 10,
                'functionalities' => [
                    'users',
                    'tenant_roles',
                    'tenant_users',
                    'product_categories',
                    'estados',
                    'cidades',
                    'bairros',
                    'enderecos',
                    'clients',
                    'products',
                    'orders',
                    'storefront-orders',
                    'reports',
                    'finance',
                    'dashboard',
                    'tenant_settings',
                    'tenant-profile',
                    'support',
                    'subscription',
                    // 'storefront' (loja online, cupons, promoções) saiu do
                    // Prata em 2026-07-24 — decisão de negócio: diferencial
                    // pago a partir do Ouro, ver product-roadmap.md.
                ],
            ],
            [
                'name' => 'Ouro',
                'slug' => 'ouro',
                'description' => 'Plano com operação ampliada e inteligência gerencial.',
                'sort_order' => 20,
                'functionalities' => [
                    'users',
                    'tenant_roles',
                    'tenant_users',
                    'product_categories',
                    'estados',
                    'cidades',
                    'bairros',
                    'enderecos',
                    'clients',
                    'products',
                    'orders',
                    'storefront-orders',
                    'reports',
                    'finance',
                    'dashboard',
                    'tenant_settings',
                    'tenant-profile',
                    'storefront',
                    'support',
                    'subscription',
                    'analytics',
                ],
            ],
            [
                'name' => 'Diamante',
                'slug' => 'diamante',
                'description' => 'Plano avançado com recursos premium e maior flexibilidade operacional.',
                'sort_order' => 30,
                'functionalities' => [
                    'users',
                    'tenant_roles',
                    'tenant_users',
                    'product_categories',
                    'estados',
                    'cidades',
                    'bairros',
                    'enderecos',
                    'clients',
                    'products',
                    'orders',
                    'storefront-orders',
                    'reports',
                    'finance',
                    'dashboard',
                    'tenant_settings',
                    'tenant-profile',
                    'storefront',
                    'support',
                    'analytics',
                    'subscription',
                ],
            ],
        ];

        foreach ($plans as $data) {
            $plan = Plan::withTrashed()->where('slug', $data['slug'])->first();

            if (!$plan) {
                $plan = Plan::create([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]);
            } else {
                if ($plan->trashed()) {
                    $plan->restore();
                }

                $plan->fill([
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]);
                $plan->save();
            }

            $functionalityIds = DB::table('functionalities')
                ->whereIn('slug', $data['functionalities'])
                ->whereNull('deleted_at')
                ->pluck('id');

            DB::table('plan_functionalities')->where('plan_id', $plan->id)->delete();

            foreach ($functionalityIds as $functionalityId) {
                DB::table('plan_functionalities')->insert([
                    'uuid' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'functionality_id' => $functionalityId,
                    'created_by' => null,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $defaultPlanId = DB::table('plans')->where('slug', 'prata')->value('id');
        if ($defaultPlanId) {
            DB::table('tenants')->whereNull('plan_id')->update([
                'plan_id' => $defaultPlanId,
                'updated_at' => now(),
            ]);
        }
    }
}
