<?php

namespace Tests\Feature\Infrastructure;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CorsTest extends TestCase
{
    #[Test]
    public function preflight_request_from_allowed_origin_returns_cors_headers(): void
    {
        config()->set('cors.allowed_origins', ['https://app.maskats.test']);

        $response = $this
            ->withHeader('Origin', 'https://app.maskats.test')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'Authorization, Content-Type')
            ->call('OPTIONS', '/api/v1/auth/login');

        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.maskats.test');
    }
}
