<?php

namespace Tests\Feature\Tenant;

use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantLogoTest extends TestCase
{
    use RefreshDatabase;

    protected int $userId;
    protected string $accessToken;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('media.public_disks.tenant'));

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Logo User',
            'email' => 'tenant-logo@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->userId = $user->id;

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'tenant-logo@test.com',
            'password' => 'password123',
        ])->json('data');

        $this->accessToken = $login['access_token'];

        $this->plan = Plan::firstOrCreate(
            ['slug' => 'premium'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Premium',
                'description' => 'Premium',
                'sort_order' => 30,
                'is_active' => true,
            ]
        );
    }

    #[Test]
    public function creating_tenant_with_logo_stores_data_and_exposes_url(): void
    {
        $this->grantPermission('tenants', 'create');

        $logo = UploadedFile::fake()->image('logo.png');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->post('/api/v1/tenants', [
                'name' => 'Tenant With Logo',
                'slug' => 'tenant-with-logo',
                'plan_uuid' => $this->plan->uuid,
                'logo' => $logo,
            ])
            ->assertStatus(201);

        $logoUrl = $response->json('data.logo_url');
        $this->assertNotNull($logoUrl);

        $tenant = Tenant::where('uuid', $response->json('data.uuid'))->first();
        $this->assertNotNull($tenant->logo_path);
        $this->assertNotNull($tenant->logo_mime);
        $this->assertStringStartsWith($tenant->uuid . '/', $tenant->logo_path);
        Storage::disk((string) config('media.public_disks.tenant'))->assertExists($tenant->logo_path);
    }

    #[Test]
    public function updating_tenant_logo_replaces_old_data(): void
    {
        $this->grantPermission('tenants', 'create');
        $this->grantPermission('tenants', 'update');

        $created = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->post('/api/v1/tenants', [
                'name' => 'Tenant With Logo',
                'slug' => 'tenant-with-logo',
                'plan_uuid' => $this->plan->uuid,
                'logo' => UploadedFile::fake()->image('old.png', 100, 100),
            ])
            ->assertStatus(201)
            ->json('data');

        $originalTenant = Tenant::where('uuid', $created['uuid'])->first();
        $oldPath = $originalTenant->logo_path;
        $this->assertNotNull($oldPath);
        Storage::disk((string) config('media.public_disks.tenant'))->assertExists($oldPath);

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->post('/api/v1/tenants/' . $created['uuid'], [
                '_method' => 'PUT',
                'name' => 'Tenant With Logo',
                'plan_uuid' => $this->plan->uuid,
                'is_active' => true,
                'logo' => UploadedFile::fake()->image('new.png', 50, 50),
            ])
            ->assertStatus(200);

        $updatedTenant = Tenant::where('uuid', $created['uuid'])->first();
        $newPath = $updatedTenant->logo_path;

        $this->assertNotEquals($oldPath, $newPath);
        $this->assertStringStartsWith($updatedTenant->uuid . '/', $newPath);
        Storage::disk((string) config('media.public_disks.tenant'))->assertMissing($oldPath);
        Storage::disk((string) config('media.public_disks.tenant'))->assertExists($newPath);
        $this->assertNotNull($response->json('data.logo_url'));
    }

    #[Test]
    public function updating_tenant_without_sending_logo_keeps_existing_one(): void
    {
        $this->grantPermission('tenants', 'create');
        $this->grantPermission('tenants', 'update');

        $created = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->post('/api/v1/tenants', [
                'name' => 'Tenant With Logo',
                'slug' => 'tenant-with-logo',
                'plan_uuid' => $this->plan->uuid,
                'logo' => UploadedFile::fake()->image('keep.png'),
            ])
            ->assertStatus(201)
            ->json('data');

        $originalPath = Tenant::where('uuid', $created['uuid'])->first()->logo_path;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->putJson('/api/v1/tenants/' . $created['uuid'], [
                'name' => 'Renamed Tenant',
                'plan_uuid' => $this->plan->uuid,
                'is_active' => true,
            ])
            ->assertStatus(200);

        $tenant = Tenant::where('uuid', $created['uuid'])->first();

        $this->assertEquals($originalPath, $tenant->logo_path);
        $this->assertEquals($response->json('data.logo_url'), $created['logo_url']);
    }

    #[Test]
    public function get_tenant_logo_returns_bytes_with_correct_content_type(): void
    {
        $this->grantPermission('tenants', 'create');

        $created = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->post('/api/v1/tenants', [
                'name' => 'Tenant With Logo',
                'slug' => 'tenant-with-logo',
                'plan_uuid' => $this->plan->uuid,
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertStatus(201)
            ->json('data');

        $tenant = Tenant::where('uuid', $created['uuid'])->first();

        $response = $this->get('/api/v1/tenants/' . $tenant->uuid . '/logo');

        $response->assertRedirect(Storage::disk((string) config('media.public_disks.tenant'))->url($tenant->logo_path));

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=' . config('media.public_cache_seconds', 31536000), $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    #[Test]
    public function get_tenant_logo_returns_404_when_tenant_has_no_logo(): void
    {
        $this->grantPermission('tenants', 'create');

        $created = $this
            ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
            ->post('/api/v1/tenants', [
                'name' => 'Tenant Without Logo',
                'slug' => 'tenant-without-logo',
                'plan_uuid' => $this->plan->uuid,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->get('/api/v1/tenants/' . $created['uuid'] . '/logo')
            ->assertStatus(404);
    }

    protected function grantPermission(string $functionality, string $action): void
    {
        $suffix = $functionality . '-' . $action;

        $groupId = DB::table('groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'RBAC Group ' . $suffix,
            'slug' => 'rbac-' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_user')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $funcId = DB::table('functionalities')->where('slug', $functionality)->value('id');

        if (!$funcId) {
            $funcId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst($functionality),
                'slug' => $functionality,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $actionId = DB::table('actions')->where('key', $action)->value('id');

        if (!$actionId) {
            $actionId = DB::table('actions')->insertGetId([
                'key' => $action,
                'name' => ucfirst($action),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_permissions')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'functionality_id' => $funcId,
            'action_id' => $actionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
