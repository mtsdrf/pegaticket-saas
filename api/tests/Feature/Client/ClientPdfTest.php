<?php

namespace Tests\Feature\Client;

use App\Models\Client\Client;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ClientPdfTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('client-pdf-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * DomPDF comprime (Flate/zlib) o conteúdo dos streams por padrão — não
     * dá pra `assertStringContainsString` direto nos bytes crus do PDF.
     * Extrai e descomprime cada `stream ... endstream` do arquivo (regex
     * simples + gzuncompress/gzinflate) pra permitir asserção de texto
     * visível, sem depender de lib externa de parsing de PDF. Mesmo helper
     * de ProductPdfTest — duplicado aqui de propósito (2 usos só, não
     * justifica extrair pra trait ainda).
     */
    protected function extractPdfText(string $pdfContent): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfContent, $matches);

        $text = '';

        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);

            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }

            if ($decoded !== false) {
                $text .= $decoded;
            }
        }

        // DomPDF usa Identity-H (UTF-16BE, 2 bytes por caractere) pra texto
        // com DejaVu Sans — cada char latino vira 0x00 + byte ASCII. Remover
        // os \x00 recompõe o texto legível pra assertStringContainsString.
        return str_replace("\x00", '', $text);
    }

    /**
     * Cliente com endereço completo (logradouro, número, complemento,
     * bairro, cidade/UF, CEP) — createClient() do trait compartilhado só
     * preenche logradouro, o teste de endereço completo precisa dos demais
     * campos.
     */
    protected function createClientWithFullAddress(int $tenantId, array $overrides = []): Client
    {
        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado ' . Str::random(6),
            'uf' => $this->nextUf(),
        ]);

        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Campinas',
        ]);

        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidade->id,
            'name' => 'Centro',
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'complemento' => 'Apto 45',
            'cep' => '13000-000',
            'is_active' => true,
        ]);

        return Client::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'endereco_id' => $endereco->id,
            'name' => 'Client ' . Str::random(6),
            'is_trusted' => true,
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function user_without_permission_cannot_export_pdf(): void
    {
        $this->auth()->postJson('/api/v1/clients/export-pdf')->assertStatus(403);
    }

    #[Test]
    public function clients_export_pdf_endpoint_returns_a_valid_pdf(): void
    {
        $this->grantPermission('clients', 'read');
        $this->createClient($this->tenant->id);

        $response = $this->auth()->postJson('/api/v1/clients/export-pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function clients_export_pdf_includes_full_address(): void
    {
        $this->grantPermission('clients', 'read');
        $this->createClientWithFullAddress($this->tenant->id, ['name' => 'EnderecoCompletoPdfTest']);

        $response = $this->auth()->postJson('/api/v1/clients/export-pdf');
        $response->assertStatus(200);

        $text = $this->extractPdfText($response->streamedContent());

        // Colunas estreitas da tabela fazem o DomPDF quebrar frases em
        // múltiplas linhas de texto (cada `Td`/`TJ` vira um token isolado
        // no content stream, sem espaço de junção entre eles) — por isso
        // as asserções checam palavras/tokens curtos, não a frase inteira.
        $this->assertStringContainsString('EnderecoCompletoPdfTest', $text);
        $this->assertStringContainsString('Rua', $text);
        $this->assertStringContainsString('Flores', $text);
        $this->assertStringContainsString('123', $text);
        $this->assertStringContainsString('Apto', $text);
        $this->assertStringContainsString('Centro', $text);
        $this->assertStringContainsString('Campinas', $text);
        $this->assertStringContainsString('13000', $text);
    }

    #[Test]
    public function clients_export_pdf_respects_filters(): void
    {
        $this->grantPermission('clients', 'read');
        $this->createClient($this->tenant->id);
        $activeClient = $this->createClient($this->tenant->id);
        $activeClient->forceFill(['name' => 'ClienteFiltradoAtivo', 'is_active' => true])->save();

        $inactiveClient = $this->createClient($this->tenant->id);
        $inactiveClient->forceFill(['name' => 'ClienteInativoFora', 'is_active' => false])->save();

        $response = $this->auth()->postJson('/api/v1/clients/export-pdf', ['is_active' => true]);
        $response->assertStatus(200);

        // Nome de token único (sem espaço) pra não depender de quebra de
        // linha da coluna — ver comentário em clients_export_pdf_includes_full_address.
        $text = $this->extractPdfText($response->streamedContent());

        $this->assertStringContainsString('ClienteFiltradoAtivo', $text);
        $this->assertStringNotContainsString('ClienteInativoFora', $text);
    }
}
