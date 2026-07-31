<?php

namespace Tests\Feature\Stock;

use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Todo tenant novo (criado via TenantService::create(), evento TenantCreated)
 * ganha automaticamente uma StockLocation padrão via o listener
 * CreateDefaultStockLocation, para o usuário não precisar configurar um
 * local antes de conseguir movimentar estoque.
 */
class DefaultStockLocationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_tenant_via_api_auto_creates_a_default_stock_location(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Creator',
            'email' => 'tenant-creator@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'tenant-creator@test.com',
            'password' => 'password123',
        ])->json('data');

        $token = $login['access_token'];

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group ' . Str::random(6),
            'slug' => 'rbac-' . Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $funcId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenants',
            'slug' => 'tenants',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = DB::table('actions')->insertGetId([
            'key' => 'create',
            'name' => 'Create',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'functionality_id' => $funcId,
            'action_id' => $actionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/tenants', [
                'name' => 'Fresh Tenant',
                'slug' => 'fresh-tenant-' . Str::random(6),
            ]);

        $response->assertStatus(201);

        $tenantUuid = $response->json('data.uuid');
        $tenant = Tenant::where('uuid', $tenantUuid)->firstOrFail();

        $location = StockLocation::where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($location);
        $this->assertSame('Depósito Principal', $location->name);
        $this->assertTrue((bool) $location->is_default);
    }

    #[Test]
    public function ensure_default_location_is_idempotent(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Idempotent Tenant',
            'slug' => 'idempotent-tenant-' . Str::random(6),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $service = app(\App\Services\Stock\StockLocationService::class);

        $first = $service->ensureDefaultLocation($tenant->id);
        $second = $service->ensureDefaultLocation($tenant->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StockLocation::where('tenant_id', $tenant->id)->count());
    }
}
