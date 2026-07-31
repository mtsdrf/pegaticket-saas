<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiHardening
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        // API pura (só JSON, nunca serve HTML/script/estilo próprios) — a
        // política mais restritiva possível: nada pode ser carregado a
        // partir de uma resposta desta API, defesa em profundidade caso
        // algum payload seja renderizado por engano num contexto de browser.
        $response->headers->set('Content-Security-Policy', "default-src 'none'");

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}