<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StoreBusinessHour;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * GET/PUT /store-settings/business-hours (Delivery Fase 2) — CRUD em
 * lote, sempre exatamente 7 linhas por tenant. Ver
 * App\Services\Storefront\StoreBusinessHoursService.
 */
class StoreBusinessHoursTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('business-hours-user@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function validDaysPayload(): array
    {
        $days = [];

        for ($day = 0; $day <= 6; $day++) {
            $days[] = [
                'day_of_week' => $day,
                'is_closed' => $day === 0,
                'opens_at' => $day === 0 ? null : '08:00',
                'closes_at' => $day === 0 ? null : '18:00',
            ];
        }

        return ['days' => $days];
    }

    #[Test]
    public function show_returns_7_days_with_closed_defaults_when_nothing_configured_yet(): void
    {
        $this->grantPermission('storefront', 'update');

        $response = $this->auth()->getJson('/api/v1/store-settings/business-hours')->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(7, $data);

        foreach ($data as $day) {
            $this->assertTrue($day['is_closed']);
            $this->assertNull($day['opens_at']);
            $this->assertNull($day['closes_at']);
        }

        // Nada persistido ainda — só o fail-safe em memória.
        $this->assertDatabaseCount('store_business_hours', 0);
    }

    #[Test]
    public function update_persists_exactly_7_rows(): void
    {
        $this->grantPermission('storefront', 'update');

        $response = $this->auth()->putJson('/api/v1/store-settings/business-hours', $this->validDaysPayload());

        $response->assertStatus(200);
        $this->assertCount(7, $response->json('data'));
        $this->assertDatabaseCount('store_business_hours', 7);

        $this->assertDatabaseHas('store_business_hours', [
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 0,
            'is_closed' => true,
        ]);

        $this->assertDatabaseHas('store_business_hours', [
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 1,
            'is_closed' => false,
        ]);
    }

    /**
     * Chamar replaceForTenant() várias vezes nunca duplica linha nem deixa
     * menos de 7 — substituição em lote (delete + insert) por tenant.
     */
    #[Test]
    public function update_called_multiple_times_never_leaves_more_or_fewer_than_7_rows(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $this->validDaysPayload())
            ->assertStatus(200);
        $this->assertDatabaseCount('store_business_hours', 7);

        $secondPayload = $this->validDaysPayload();
        $secondPayload['days'][1]['opens_at'] = '09:00';
        $secondPayload['days'][1]['closes_at'] = '17:00';

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $secondPayload)
            ->assertStatus(200);

        $this->assertDatabaseCount('store_business_hours', 7);
        $this->assertDatabaseHas('store_business_hours', [
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'closes_at' => '17:00',
        ]);

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $this->validDaysPayload())
            ->assertStatus(200);
        $this->assertDatabaseCount('store_business_hours', 7);
    }

    /**
     * Menos de 7 dias agora é válido — dia ausente do payload é tratado como
     * fechado por omissão (fail-safe em getForTenant), sem persistir linha.
     */
    #[Test]
    public function update_accepts_fewer_than_7_days_treating_missing_as_closed(): void
    {
        $this->grantPermission('storefront', 'update');

        $payload = ['days' => [
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '18:00'],
        ]];

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $payload)
            ->assertStatus(200);

        $this->assertDatabaseCount('store_business_hours', 1);

        // show() completa com os fail-safe fechados (7 dias representados).
        $data = $this->auth()->getJson('/api/v1/store-settings/business-hours')->json('data');
        $this->assertCount(7, $data);
    }

    /**
     * Dois turnos no MESMO day_of_week (ex.: manhã + tarde) agora é válido —
     * persiste 2 linhas para o mesmo dia.
     */
    #[Test]
    public function update_accepts_multiple_shifts_on_the_same_day(): void
    {
        $this->grantPermission('storefront', 'update');

        $payload = ['days' => [
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '12:00'],
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '13:00', 'closes_at' => '18:00'],
        ]];

        $response = $this->auth()->putJson('/api/v1/store-settings/business-hours', $payload)
            ->assertStatus(200);

        // O PUT retorna exatamente os turnos persistidos (2), sem os
        // fail-safe fechados (que o GET/show completa depois).
        $this->assertCount(2, $response->json('data'));

        $this->assertDatabaseCount('store_business_hours', 2);
        $this->assertDatabaseHas('store_business_hours', [
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '12:00',
        ]);
        $this->assertDatabaseHas('store_business_hours', [
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 1,
            'opens_at' => '13:00',
            'closes_at' => '18:00',
        ]);
    }

    #[Test]
    public function update_rejects_overlapping_shifts_on_the_same_day(): void
    {
        $this->grantPermission('storefront', 'update');

        $payload = ['days' => [
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '15:00'],
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '14:00', 'closes_at' => '18:00'],
        ]];

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $payload)
            ->assertStatus(422);

        $this->assertDatabaseCount('store_business_hours', 0);
    }

    #[Test]
    public function update_rejects_more_than_4_shifts_on_the_same_day(): void
    {
        $this->grantPermission('storefront', 'update');

        $payload = ['days' => [
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '06:00', 'closes_at' => '07:00'],
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '09:00'],
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '11:00'],
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '12:00', 'closes_at' => '13:00'],
            ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '14:00', 'closes_at' => '15:00'],
        ]];

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $payload)
            ->assertStatus(422);

        $this->assertDatabaseCount('store_business_hours', 0);
    }

    /**
     * closes_at < opens_at é horário que atravessa a meia-noite (ex.: abre
     * 20:00, fecha 03:00) — PERMITIDO de propósito, interpretado assim por
     * StoreBusinessHoursService::isOpenNow(). Só closes_at === opens_at é
     * rejeitado (ambíguo).
     */
    #[Test]
    public function update_accepts_closes_at_before_opens_at_as_overnight_hours(): void
    {
        $this->grantPermission('storefront', 'update');

        $payload = $this->validDaysPayload();
        $payload['days'][1]['opens_at'] = '20:00';
        $payload['days'][1]['closes_at'] = '03:00';

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('store_business_hours', [
            'tenant_id' => $this->tenant->id,
            'day_of_week' => 1,
            'opens_at' => '20:00',
            'closes_at' => '03:00',
        ]);
    }

    #[Test]
    public function update_rejects_closes_at_equal_to_opens_at(): void
    {
        $this->grantPermission('storefront', 'update');

        $payload = $this->validDaysPayload();
        $payload['days'][1]['opens_at'] = '08:00';
        $payload['days'][1]['closes_at'] = '08:00';

        $this->auth()->putJson('/api/v1/store-settings/business-hours', $payload)
            ->assertStatus(422);
    }

    #[Test]
    public function tenants_are_isolated_from_each_other(): void
    {
        $this->grantPermission('storefront', 'update');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        StoreBusinessHour::create([
            'tenant_id' => $otherTenant->id,
            'day_of_week' => 1,
            'is_closed' => false,
            'opens_at' => '08:00:00',
            'closes_at' => '18:00:00',
        ]);

        $response = $this->auth()->getJson('/api/v1/store-settings/business-hours')->assertStatus(200);

        // Nenhuma linha de outro tenant vaza — este tenant continua vendo
        // o fail-safe fechado (nada configurado ainda).
        foreach ($response->json('data') as $day) {
            $this->assertTrue($day['is_closed']);
        }
    }

    #[Test]
    public function user_without_permission_cannot_view_or_update(): void
    {
        $this->auth()->getJson('/api/v1/store-settings/business-hours')->assertStatus(403);
        $this->auth()->putJson('/api/v1/store-settings/business-hours', $this->validDaysPayload())
            ->assertStatus(403);
    }

    /**
     * isOpenNow() com 2 turnos no mesmo dia: aberto dentro de qualquer turno,
     * fechado no intervalo entre eles.
     */
    #[Test]
    public function is_open_now_respects_multiple_shifts_on_the_same_day(): void
    {
        // Quarta-feira (day_of_week = 3): manhã 08:00-12:00 + tarde 13:00-18:00.
        foreach ([['08:00:00', '12:00:00'], ['13:00:00', '18:00:00']] as [$opens, $closes]) {
            StoreBusinessHour::create([
                'tenant_id' => $this->tenant->id,
                'day_of_week' => 3,
                'is_closed' => false,
                'opens_at' => $opens,
                'closes_at' => $closes,
            ]);
        }

        $service = app(\App\Services\Storefront\StoreBusinessHoursService::class);

        \Illuminate\Support\Carbon::setTestNow('2026-07-22 09:00:00'); // quarta, dentro do turno da manhã
        $this->assertTrue($service->isOpenNow($this->tenant->id));

        \Illuminate\Support\Carbon::setTestNow('2026-07-22 12:30:00'); // quarta, no intervalo entre turnos
        $this->assertFalse($service->isOpenNow($this->tenant->id));

        \Illuminate\Support\Carbon::setTestNow('2026-07-22 15:00:00'); // quarta, dentro do turno da tarde
        $this->assertTrue($service->isOpenNow($this->tenant->id));

        \Illuminate\Support\Carbon::setTestNow();
    }
}
