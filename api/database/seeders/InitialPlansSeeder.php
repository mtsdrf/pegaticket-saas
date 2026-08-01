<?php

namespace Database\Seeders;

use App\Models\Plan\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitialPlansSeeder extends Seeder
{
    private const ACTIVE_PLAN_SLUG = 'pegaticket';

    public function run(): void
    {
        $plans = [
            [
                'name' => 'PegaTicket',
                'slug' => self::ACTIVE_PLAN_SLUG,
                'description' => 'Plano unico com todas as funcionalidades atuais liberadas.',
                'sort_order' => 10,
                'functionalities' => [
                    'users',
                    'tenant_roles',
                    'tenant_users',
                    'event_categories',
                    'events',
                    'event_sessions',
                    'ticket_types',
                    'ticket_batches',
                    'event_products',
                    'venues',
                    'seats',
                    'stock',
                    'orders',
                    'customers',
                    'storefront',
                    'reports',
                    'analytics',
                    'storefront-orders',
                    'finance',
                    'dashboard',
                    'tenant_settings',
                    'tenant-profile',
                    'support',
                    'subscription',
                    // deliberately excluded: global admin/internal-only
                    // functionalities like groups/functionalities/plans/
                    // tenants/audit_logs/payment_admin/release_notes.
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

        Plan::query()
            ->where('slug', '!=', self::ACTIVE_PLAN_SLUG)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $defaultPlanId = DB::table('plans')->where('slug', self::ACTIVE_PLAN_SLUG)->value('id');
        if ($defaultPlanId) {
            DB::table('tenants')->update([
                'plan_id' => $defaultPlanId,
                'updated_at' => now(),
            ]);
        }
    }
}
