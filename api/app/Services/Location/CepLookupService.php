<?php

namespace App\Services\Location;

use App\Exceptions\CepNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Consulta de CEP via ViaCEP, pro fluxo "digitar o CEP" do endereço do
 * checkout da loja pública — mesmo padrão de `ReverseGeocodeService`
 * (cache 30 dias, timeout 10s). ViaCEP devolve `uf` (sigla, ex. "SP"), não
 * nome do estado — por isso usa `LocalAddressMatcher::matchByEstadoUf()`,
 * diferente do reverse-geocode (Nominatim, nome completo do estado).
 */
class CepLookupService
{
    private const CACHE_TTL_DAYS = 30;

    public function __construct(private LocalAddressMatcher $matcher)
    {
    }

    public function lookup(string $cep): array
    {
        $digits = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($digits) !== 8) {
            throw new CepNotFoundException(__('messages.location.cep_not_found'));
        }

        $cacheKey = 'cep-lookup:' . $digits;

        $data = Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($digits) {
            $response = Http::timeout(10)->get("https://viacep.com.br/ws/{$digits}/json/");

            $response->throw();

            $json = $response->json();

            return ($json['erro'] ?? false) === true ? null : $json;
        });

        if ($data === null) {
            throw new CepNotFoundException(__('messages.location.cep_not_found'));
        }

        $matched = $this->matcher->matchByEstadoUf($data['uf'] ?? null, $data['localidade'] ?? null, $data['bairro'] ?? null);

        return [
            ...$matched,
            'cep' => $data['cep'] ?? $digits,
            'logradouro' => $data['logradouro'] ?: null,
        ];
    }
}
