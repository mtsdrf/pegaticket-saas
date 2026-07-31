<?php

namespace App\Services\Location;

use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use Illuminate\Support\Str;

/**
 * Matching hierárquico contra Estado/Cidade/Bairro (nunca cria linha nova,
 * campo sem match fica `null`) — extraído de `ReverseGeocodeService`
 * (Nominatim casa por NOME de estado, normalizado) pra ser reaproveitado
 * também por `CepLookupService` (ViaCEP casa por `uf`, sigla exata, sem
 * normalização de acento). Cidade só conta se o estado bateu; bairro só
 * conta se a cidade bateu — nunca pula nível da hierarquia.
 */
class LocalAddressMatcher
{
    public function matchByEstadoName(?string $estadoName, ?string $cidadeName, ?string $bairroName): array
    {
        if (!$estadoName) {
            return $this->empty();
        }

        $estado = Estado::whereNull('deleted_at')
            ->get()
            ->first(fn(Estado $e) => $this->normalize($e->name) === $this->normalize($estadoName));

        return $this->matchFromEstado($estado, $cidadeName, $bairroName);
    }

    public function matchByEstadoUf(?string $uf, ?string $cidadeName, ?string $bairroName): array
    {
        if (!$uf) {
            return $this->empty();
        }

        $estado = Estado::whereNull('deleted_at')
            ->whereRaw('UPPER(uf) = ?', [Str::upper(trim($uf))])
            ->first();

        return $this->matchFromEstado($estado, $cidadeName, $bairroName);
    }

    private function matchFromEstado(?Estado $estado, ?string $cidadeName, ?string $bairroName): array
    {
        $result = $this->empty();

        if (!$estado) {
            return $result;
        }

        $result['estado_uuid'] = $estado->uuid;

        if (!$cidadeName) {
            return $result;
        }

        $cidade = Cidade::where('estado_id', $estado->id)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn(Cidade $c) => $this->normalize($c->name) === $this->normalize($cidadeName));

        if (!$cidade) {
            return $result;
        }

        $result['cidade_uuid'] = $cidade->uuid;

        if (!$bairroName) {
            return $result;
        }

        $bairro = Bairro::where('cidade_id', $cidade->id)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn(Bairro $b) => $this->normalize($b->name) === $this->normalize($bairroName));

        if ($bairro) {
            $result['bairro_uuid'] = $bairro->uuid;
        }

        return $result;
    }

    private function empty(): array
    {
        return [
            'estado_uuid' => null,
            'cidade_uuid' => null,
            'bairro_uuid' => null,
        ];
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim(Str::ascii($value)));
    }
}
