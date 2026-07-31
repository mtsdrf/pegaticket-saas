<?php

namespace Tests\Feature\Console;

use App\Jobs\Location\GeocodeEnderecoJob;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeocodeEnderecosBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected Estado $estado;
    protected Cidade $cidade;
    protected Bairro $bairro;
    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'São Paulo',
            'uf' => 'SP',
            'is_active' => true,
        ]);

        $this->cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $this->estado->id,
            'name' => 'Campinas',
            'is_active' => true,
        ]);

        $this->bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $this->cidade->id,
            'name' => 'Cambuí',
            'is_active' => true,
        ]);

        $this->tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    protected function seedEndereco(Tenant $tenant, string $status): Endereco
    {
        return Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'estado_id' => $this->estado->id,
            'cidade_id' => $this->cidade->id,
            'bairro_id' => $this->bairro->id,
            'logradouro' => 'Rua ' . Str::random(6),
            'is_active' => true,
            'geocode_status' => $status,
        ]);
    }

    #[Test]
    public function command_enqueues_only_pending_and_failed_enderecos(): void
    {
        Queue::fake();

        $pending = $this->seedEndereco($this->tenantA, 'pending');
        $failed = $this->seedEndereco($this->tenantA, 'failed');
        $success = $this->seedEndereco($this->tenantA, 'success');

        $this->artisan('enderecos:geocode-backfill')
            ->expectsOutputToContain('2 endereço(s) enfileirado(s)')
            ->assertExitCode(0);

        Queue::assertPushed(GeocodeEnderecoJob::class, 2);
        Queue::assertPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $pending->id);
        Queue::assertPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $failed->id);
        Queue::assertNotPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $success->id);
    }

    #[Test]
    public function command_respects_tenant_option(): void
    {
        Queue::fake();

        $pendingA = $this->seedEndereco($this->tenantA, 'pending');
        $pendingB = $this->seedEndereco($this->tenantB, 'pending');

        $this->artisan('enderecos:geocode-backfill', ['--tenant' => $this->tenantA->uuid])
            ->assertExitCode(0);

        Queue::assertPushed(GeocodeEnderecoJob::class, 1);
        Queue::assertPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $pendingA->id);
        Queue::assertNotPushed(GeocodeEnderecoJob::class, fn($job) => $job->enderecoId === $pendingB->id);
    }
}
