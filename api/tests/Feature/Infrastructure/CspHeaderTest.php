<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CspHeaderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function responses_include_restrictive_csp_header(): void
    {
        $response = $this->getJson('/api/v1/legal-documents/terms');

        $response->assertHeader('Content-Security-Policy', "default-src 'none'");
    }
}
