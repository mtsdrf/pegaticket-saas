<?php

namespace App\Http\Middleware;

use App\Exceptions\CustomerTokenBlacklistedException;
use App\Exceptions\FinalCustomerNotFoundException;
use App\Exceptions\InvalidCustomerTokenException;
use App\Support\CustomerTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/**
 * Variante "opcional" de CustomerJwtAccessMiddleware, para rotas públicas
 * que querem personalizar a resposta QUANDO o cliente final está logado
 * (ex.: is_favorited no catálogo da loja), sem exigir autenticação. Nunca
 * retorna 401 — ausência de token, token inválido/expirado ou token de
 * outra identidade (staff) simplesmente seguem como visitante anônimo
 * (portal_customer() não populado). Reaproveita a mesma resolução de
 * CustomerJwtAccessMiddleware via App\Support\CustomerTokenResolver.
 */
class OptionalCustomerJwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $customer = CustomerTokenResolver::resolve();

            app()->instance('portal_customer', $customer);
        } catch (InvalidCustomerTokenException|CustomerTokenBlacklistedException|FinalCustomerNotFoundException|JWTException $e) {
            // Casos esperados de "sem token válido" — segue anônimo, sem log.
        } catch (\Throwable $e) {
            // Erro de infraestrutura inesperado (ex.: DB indisponível ao
            // consultar TokenBlacklist) — não deveria derrubar uma rota
            // pública, mas merece visibilidade em vez de sumir em silêncio.
            Log::warning('Falha inesperada ao resolver customer.jwt.optional', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
