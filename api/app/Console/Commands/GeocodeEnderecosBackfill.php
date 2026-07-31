<?php

namespace App\Console\Commands;

use App\Jobs\Location\GeocodeEnderecoJob;
use App\Models\Location\Endereco;
use App\Models\Tenant\Tenant;
use Illuminate\Console\Command;

/**
 * Backfill de geocodificação (Fase 3.1) — enfileira GeocodeEnderecoJob para
 * endereços ainda sem coordenada (pending) ou que falharam antes (failed).
 * Não usa ->afterCommit() (fora de contexto HTTP/transaction) nem sleep()
 * manual — o rate limit de 1 req/s do Nominatim já é imposto pelo
 * middleware RateLimited('nominatim') do próprio job, no worker.
 */
class GeocodeEnderecosBackfill extends Command
{
    protected $signature = 'enderecos:geocode-backfill {--tenant= : uuid ou id de um tenant específico, opcional}';

    protected $description = 'Enfileira GeocodeEnderecoJob para endereços com geocode_status pending/failed.';

    public function handle(): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = null;

        if ($tenantOption) {
            $tenant = is_numeric($tenantOption)
                ? Tenant::find($tenantOption)
                : Tenant::where('uuid', $tenantOption)->first();

            if (!$tenant) {
                $this->error("Tenant '{$tenantOption}' não encontrado.");

                return self::FAILURE;
            }

            $tenantId = $tenant->id;
        }

        $total = 0;

        Endereco::query()
            ->whereNull('deleted_at')
            ->whereIn('geocode_status', ['pending', 'failed'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->chunkById(200, function ($enderecos) use (&$total) {
                foreach ($enderecos as $endereco) {
                    GeocodeEnderecoJob::dispatch($endereco->id);
                    $total++;
                }
            });

        $this->info("{$total} endereço(s) enfileirado(s) para geocodificação.");

        return self::SUCCESS;
    }
}
