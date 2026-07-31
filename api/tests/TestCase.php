<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nenhum teste pode bater em serviço externo real. GeocodeEnderecoJob
     * roda síncrono em testing e é disparado por EnderecoService::create()
     * — direto ou via ClientService::create() (cliente sempre cria seu
     * endereço) — então praticamente qualquer teste que crie
     * Endereco/Client aciona isso, não só os testes de geocodificação.
     * preventStrayRequests() torna qualquer request real não coberta por um
     * Http::fake() local um erro claro, em vez de flakiness (ex: 429 real do
     * Nominatim, como aconteceu rodando a suíte junto do backfill de
     * produção). NÃO registrar aqui um Http::fake() global pro Nominatim:
     * Http::fake() dá prioridade ao PRIMEIRO stub registrado que casa a URL
     * (FIFO, não "último vence") — um fake global no setUp() base rodaria
     * antes e blindaria qualquer fake mais específico que um teste declare
     * depois (achado ao adicionar isso — GeocodeEnderecoJobTest passou a
     * falhar porque o fake global "sem resultado" vencia o fake do próprio
     * teste). Cada teste que precisa geocodificar declara seu próprio
     * Http::fake(['nominatim.openstreetmap.org/*' => ...]).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
