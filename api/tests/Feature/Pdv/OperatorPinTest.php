<?php

namespace Tests\Feature\Pdv;

use App\Models\AuditLog;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class OperatorPinTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('pin-owner@test.com');
        $this->grantPermission('pdv', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function user_can_set_and_change_own_pin(): void
    {
        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '1234'])
            ->assertStatus(200);

        $this->assertDatabaseCount('user_pins', 1);

        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '5678'])
            ->assertStatus(200);

        // Trocar o PIN atualiza a linha existente, não cria uma segunda.
        $this->assertDatabaseCount('user_pins', 1);
    }

    #[Test]
    public function pin_must_be_numeric_with_4_to_6_digits(): void
    {
        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => 'abcd'])
            ->assertStatus(422);

        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '123'])
            ->assertStatus(422);
    }

    #[Test]
    public function cannot_reuse_a_pin_already_taken_by_another_operator_in_the_same_tenant(): void
    {
        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '1111'])
            ->assertStatus(200);

        $otherUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Operator',
            'email' => 'pin-other@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $role = TenantRole::where('tenant_id', $this->tenant->id)->first();

        TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'tenant_role_id' => $role->id,
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'pin-other@test.com',
            'password' => 'password123',
        ])->json('data');

        $switch = $this->withHeader('Authorization', 'Bearer ' . $login['access_token'])
            ->postJson('/api/v1/auth/switch-tenant', ['tenant_uuid' => $this->tenant->uuid])
            ->json('data');

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'PDV Read Other Operator ' . Str::random(6),
            'slug' => 'pdv-read-other-' . Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $functionalityId = DB::table('functionalities')->where('slug', 'pdv')->value('id');
        $actionId = DB::table('actions')->where('key', 'read')->value('id');

        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'functionality_id' => $functionalityId,
            'action_id' => $actionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $switch['access_token'])
            ->putJson('/api/v1/pdv/operator-pin', ['pin' => '1111'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PIN');
    }

    #[Test]
    public function resolves_operator_by_correct_pin_within_current_tenant(): void
    {
        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '9999']);

        $response = $this->auth()->postJson('/api/v1/pdv/operator-session', ['pin' => '9999']);

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', User::where('id', $this->userId)->value('uuid'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'operator_session_resolved']);
    }

    #[Test]
    public function rejects_wrong_pin(): void
    {
        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '4321']);

        $this->auth()->postJson('/api/v1/pdv/operator-session', ['pin' => '0000'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PIN');
    }

    #[Test]
    public function pin_from_another_tenant_does_not_resolve_here(): void
    {
        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '2468']);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $otherRole = TenantRole::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Member',
            'slug' => 'member',
            'is_active' => true,
        ]);

        TenantUser::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->userId,
            'tenant_role_id' => $otherRole->id,
            'is_active' => true,
        ]);

        $switch = $this->auth()->postJson('/api/v1/auth/switch-tenant', [
            'tenant_uuid' => $otherTenant->uuid,
        ])->json('data');

        $this->withHeader('Authorization', 'Bearer ' . $switch['access_token'])
            ->postJson('/api/v1/pdv/operator-session', ['pin' => '2468'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PIN');
    }

    #[Test]
    public function pdv_sale_records_operator_when_resolved_via_pin(): void
    {
        $this->grantPermission('pdv', 'read');
        $this->grantPermission('pdv', 'open');
        $this->grantPermission('pdv', 'sell');
        $this->grantPermission('stock', 'entry');

        $this->auth()->putJson('/api/v1/pdv/operator-pin', ['pin' => '7777']);
        $operatorUuid = $this->auth()->postJson('/api/v1/pdv/operator-session', ['pin' => '7777'])
            ->json('data.uuid');

        $this->auth()->postJson('/api/v1/pdv/cash-sessions', ['opening_amount' => 100])
            ->assertStatus(201);

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'operator_uuid' => $operatorUuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.operator.uuid', $operatorUuid);
    }
}
