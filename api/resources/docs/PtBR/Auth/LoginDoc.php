<?php

namespace App\Docs\PtBR\Auth;

/**
 * @OA\Post(
 *     path="/api/v1/auth/login",
 *     operationId="authLogin",
 *     tags={"Autenticação"},
 *     summary="Realizar login",
 *     description="Autentica um usuário e retorna tokens de acesso (JWT) e atualização.
 * 
 * **Fluxo:**
 * 1. Envie email e senha
 * 2. Receba `access_token` (válido por 15 minutos)
 * 3. Receba `refresh_token` (válido por 30 dias)
 * 4. Use o `access_token` em todas as requisições protegidas
 * 
 * **Segurança:**
 * - Rate limiting: máximo 5 tentativas por minuto
 * - Senha é verificada com hash bcrypt
 * - Todas as tentativas (sucesso/falha) são registradas em logs de auditoria
 * - Suporta múltiplos dispositivos simultâneos (cada login gera um novo refresh token)",
 *     
 *     @OA\RequestBody(
 *         required=true,
 *         description="Credenciais de autenticação do usuário",
 *         @OA\JsonContent(
 *             required={"email", "password"},
 *             @OA\Property(
 *                 property="email",
 *                 type="string",
 *                 format="email",
 *                 description="Endereço de email do usuário",
 *                 example="admin@empresa.com.br"
 *             ),
 *             @OA\Property(
 *                 property="password",
 *                 type="string",
 *                 format="password",
 *                 description="Senha do usuário (mínimo 8 caracteres)",
 *                 example="Senha@123"
 *             )
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=200,
 *         description="Login realizado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Login realizado com sucesso."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="user",
 *                     type="object",
 *                     description="Dados básicos do usuário autenticado",
 *                     @OA\Property(property="uuid", type="string", format="uuid", example="8796e85c-7e9c-4788-a503-840ede8cf78a"),
 *                     @OA\Property(property="name", type="string", example="João Silva"),
 *                     @OA\Property(property="email", type="string", format="email", example="joao@empresa.com.br"),
 *                     @OA\Property(property="is_active", type="boolean", example=true),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T10:00:00.000000Z")
 *                 ),
 *                 @OA\Property(
 *                     property="tokens",
 *                     type="object",
 *                     description="Tokens de autenticação",
 *                     @OA\Property(
 *                         property="access_token",
 *                         type="string",
 *                         description="Token JWT para autenticação nas requisições (expira em 15 minutos)",
 *                         example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL3YxL2F1dGgvbG9naW4iLCJpYXQiOjE3Njc1NzE3NTUsImV4cCI6MTc2NzU3MjY1NSwibmJmIjoxNzY3NTcxNzU1LCJqdGkiOiJ4R3JXY1N4MFcxV0xEVGpNIiwic3ViIjoiMSIsInBydiI6ImI5MTI3OTk3OGYxMWFhN2JjNTY3MDQ4N2ZmZjAxZTIyODI1M2ZlNDgiLCJ1dWlkIjoiODc5NmU4NWMtN2U5Yy00Nzg4LWE1MDMtODQwZWRlOGNmNzhhIn0.kQPFRoWXBXXu0uUAZJQvfEuinmWl1vfdXJhbntYvzR0"
 *                     ),
 *                     @OA\Property(
 *                         property="refresh_token",
 *                         type="string",
 *                         format="uuid",
 *                         description="Token para renovar o access_token sem fazer login novamente (expira em 30 dias)",
 *                         example="87368cbd-d979-46b4-9f92-7e27b5a0a1c2"
 *                     ),
 *                     @OA\Property(
 *                         property="token_type",
 *                         type="string",
 *                         description="Tipo de token (sempre 'Bearer')",
 *                         example="Bearer"
 *                     ),
 *                     @OA\Property(
 *                         property="expires_in",
 *                         type="integer",
 *                         description="Tempo de expiração do access_token em segundos",
 *                         example=900
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="meta", type="object", example={})
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Limit",
 *             description="Número máximo de requisições permitidas",
 *             @OA\Schema(type="integer", example=5)
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Remaining",
 *             description="Número de requisições restantes",
 *             @OA\Schema(type="integer", example=4)
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=401,
 *         description="Credenciais inválidas",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Credenciais inválidas."),
 *             @OA\Property(property="code", type="string", example="INVALID_CREDENTIALS"),
 *             @OA\Property(property="errors", type="object", example={}),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação",
 *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 *     ),
 *     
 *     @OA\Response(
 *         response=429,
 *         description="Muitas tentativas de login",
 *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
 *     )
 * )
 */
class LoginDoc {}