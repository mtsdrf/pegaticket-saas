<?php

namespace App\Jobs\Location;

use App\Models\Location\Endereco;
use App\Services\Logging\ApplicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Geocodifica um Endereco via Nominatim (OpenStreetMap). Recebe só o id
 * (não o Model) para evitar serializar um snapshot desatualizado na fila.
 *
 * Escrita de lat/lng/geocode_status é metadado derivado, não mutação de
 * domínio — não dispara EnderecoUpdated/auditoria (ver
 * .claude/memory/api-patterns.md).
 */
class GeocodeEnderecoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(public readonly int $enderecoId)
    {
    }

    /**
     * Nominatim exige no máximo 1 req/s — o RateLimited middleware do
     * Laravel paceia o worker (limiter 'nominatim' registrado em
     * AppServiceProvider::boot), mais robusto que sleep() manual.
     */
    public function middleware(): array
    {
        return [new RateLimited('nominatim')];
    }

    public function handle(): void
    {
        $endereco = Endereco::with(['estado', 'cidade', 'bairro'])->find($this->enderecoId);

        if (!$endereco) {
            return;
        }

        $query = $this->buildQuery($endereco);
        $cacheKey = 'geocode:' . md5(strtolower($query));

        $result = Cache::remember($cacheKey, now()->addDays(30), function () use ($query) {
            $response = Http::withHeaders([
                'User-Agent' => 'PegaTicket SaaS (contato@pegaticket.com.br)',
            ])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'br',
                ]);

            $response->throw();

            $results = $response->json();

            if (empty($results)) {
                return null;
            }

            return [
                'lat' => (float) $results[0]['lat'],
                'lng' => (float) $results[0]['lon'],
            ];
        });

        if ($result === null) {
            $endereco->forceFill([
                'geocode_status' => 'failed',
                'geocoded_at' => now(),
            ])->saveQuietly();

            return;
        }

        $endereco->forceFill([
            'lat' => $result['lat'],
            'lng' => $result['lng'],
            'geocode_status' => 'success',
            'geocoded_at' => now(),
        ])->saveQuietly();
    }

    /**
     * Chamado quando as $tries se esgotam por EXCEÇÃO (rede, 429, timeout —
     * problema transitório do lado do Nominatim/rede, não "endereço não
     * existe"). Não mexe em geocode_status/geocoded_at de propósito: o
     * endereço permanece 'pending' pra ser tentado de novo num backfill
     * futuro. Achado em produção (2026-07-14): rodar o backfill de 1.910
     * endereços sustentado a 1 req/s por muitos minutos fez o Nominatim
     * devolver 429 em massa (throttle dinâmico do servidor demo público,
     * mais restritivo que o limite documentado de 1/s) — antes desse fix,
     * isso marcava endereços geocodificáveis como 'failed' permanente
     * (mesmo status usado pra "Nominatim não achou nada", uma resposta
     * negativa válida e definitiva). As duas situações são semanticamente
     * diferentes e não podem compartilhar o mesmo status.
     */
    public function failed(\Throwable $e): void
    {
        ApplicationLogger::warning('Falha ao geocodificar endereço (esgotou tentativas)', [
            'endereco_id' => $this->enderecoId,
            'exception' => $e->getMessage(),
        ]);
    }

    /**
     * Bairro fica de fora da query: nomes de bairro legados/informais (ex.
     * "De Santi/Imperial") não existem verbatim no OSM e fazem o Nominatim
     * não achar NADA mesmo quando rua+número+cidade bateria exato (validado
     * manualmente — ver .claude/memory/api-patterns.md). Rua+número já é
     * granularidade suficiente para geocodificação de rota.
     */
    private function buildQuery(Endereco $endereco): string
    {
        $parts = [$endereco->logradouro];

        if ($endereco->numero) {
            $parts[] = $endereco->numero;
        }

        $parts[] = $endereco->cidade->name;
        $parts[] = $endereco->estado->name;
        $parts[] = 'Brasil';

        return implode(', ', $parts);
    }
}
