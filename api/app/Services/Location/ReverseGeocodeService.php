<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reverse geocoding síncrono (lat/lng -> estado/cidade/bairro/logradouro/
 * cep) via Nominatim, para o clique único de "usar minha localização
 * atual" no formulário de cliente/checkout da loja. Diferente de
 * GeocodeEnderecoJob (que é assíncrono/fila): aqui a resposta precisa
 * voltar na mesma request.
 *
 * Match contra Estado/Cidade/Bairro delegado a `LocalAddressMatcher`
 * (extraído pra ser reaproveitado também por `CepLookupService`) — sempre
 * exato (nome normalizado — sem acento, lowercase) e respeita a cadeia
 * hierárquica. Nunca usa LIKE pra aproximar — se não achar exato, o campo
 * correspondente fica null.
 */
class ReverseGeocodeService
{
    private const CACHE_TTL_DAYS = 30;

    public function __construct(private LocalAddressMatcher $matcher)
    {
    }

    public function reverse(float $lat, float $lng): array
    {
        $roundedLat = round($lat, 5);
        $roundedLng = round($lng, 5);

        $cacheKey = 'reverse-geocode:' . $roundedLat . ':' . $roundedLng;

        $address = Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($roundedLat, $roundedLng) {
            $response = Http::withHeaders([
                'User-Agent' => 'Maskats SaaS (contato@maskats.com.br)',
            ])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $roundedLat,
                    'lon' => $roundedLng,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'accept-language' => 'pt-BR',
                ]);

            $response->throw();

            return $response->json('address') ?? [];
        });

        $estadoName = $address['state'] ?? null;
        $cidadeName = $address['city'] ?? $address['town'] ?? $address['municipality'] ?? null;
        $bairroName = $address['suburb'] ?? $address['neighbourhood'] ?? null;

        $matched = $this->matcher->matchByEstadoName($estadoName, $cidadeName, $bairroName);

        return [
            ...$matched,
            'logradouro' => $address['road'] ?? null,
            'cep' => $address['postcode'] ?? null,
        ];
    }
}
