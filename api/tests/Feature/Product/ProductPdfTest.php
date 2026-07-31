<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ProductPdfTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('product-pdf-user@test.com');
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
     * visível, sem depender de lib externa de parsing de PDF.
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

    #[Test]
    public function user_without_permission_cannot_export_pdf(): void
    {
        $this->auth()->postJson('/api/v1/products/pdf')->assertStatus(403);
    }

    #[Test]
    public function products_pdf_endpoint_returns_a_valid_pdf(): void
    {
        $this->grantPermission('products', 'read');
        $this->createProduct($this->tenant->id, ['name' => 'Queijo Minas']);

        $response = $this->auth()->postJson('/api/v1/products/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function products_pdf_respects_filters(): void
    {
        $this->grantPermission('products', 'read');
        $this->createProduct($this->tenant->id, ['name' => 'Produto Disponivel', 'is_available' => true]);
        $this->createProduct($this->tenant->id, ['name' => 'Produto Indisponivel', 'is_available' => false]);

        $response = $this->auth()->postJson('/api/v1/products/pdf', ['is_available' => true]);

        $response->assertStatus(200);
        $text = $this->extractPdfText($response->streamedContent());

        $this->assertStringContainsString('Produto Disponivel', $text);
        $this->assertStringNotContainsString('Produto Indisponivel', $text);
    }

    #[Test]
    public function products_pdf_works_without_any_image(): void
    {
        $this->grantPermission('products', 'read');
        $this->createProduct($this->tenant->id, ['name' => 'Produto Sem Imagem', 'image_data' => null]);

        $response = $this->auth()->postJson('/api/v1/products/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Produto Sem Imagem', $this->extractPdfText($response->streamedContent()));
    }

    #[Test]
    public function products_pdf_embeds_local_image_as_base64(): void
    {
        $this->grantPermission('products', 'read');

        $file = UploadedFile::fake()->image('produto.jpg', 100, 100);

        $this->createProduct($this->tenant->id, [
            'name' => 'Produto Com Imagem',
            'image_data' => file_get_contents($file->getRealPath()),
            'image_mime' => $file->getMimeType(),
        ]);

        $response = $this->auth()->postJson('/api/v1/products/pdf');

        $response->assertStatus(200);

        // A view usa data URI (base64) só internamente — o DomPDF decodifica
        // e embute a imagem como um XObject próprio no PDF final, não sobra
        // texto "data:image" no arquivo. `/Subtype /Image` confirma que a
        // imagem foi de fato processada e embutida (não é comprimida junto
        // com o content stream, aparece direto nos bytes crus do PDF).
        $this->assertStringContainsString('/Subtype /Image', $response->streamedContent());
    }
}
