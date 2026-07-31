<?php

namespace App\Docs\PtBR\Auth;

/**
 * @OA\Post(
 *     path="/api/v1/auth/refresh",
 *     operationId="authRefresh",
 *     tags={"Autenticação"},
 *     summary="Renovar token de acesso",
 *     description="Renova o token de acesso (access_token) usando um refresh_token válido, sem necessidade de fazer login novamente.
 * 
 * **Rotação de Tokens:**
 * - O `refresh_token` enviado é REVOGADO após o uso
 * - Um NOVO `refresh_token` é gerado e retornado
 * - Isso previne reutilização de refresh tokens e aumenta a segurança
 * - O novo `access_token` tem validade de 15 minutos
 * - O novo `refresh_token` tem validade de 30 dias
 * 
 * **Quando usar:**
 * - Quando o `access_token` expirar (após 15 minutos)
 * - Para manter o usuário logado sem pedir credenciais novamente
 * - Implemente renovação automática no frontend quando receber erro 401 com code TOKEN_EXPIRED
 * 
 * **Segurança:**
 * - Rate limiting: máximo 10 tentativas por minuto
 * - Refresh tokens são hasheados com SHA-512 no banco
 * - Validação de IP e User-Agent (opcional, configurável)
 * - Todas as renovações são registradas em logs de auditoria",
 *     
 *     @OA\RequestBody(
 *         required=true,
 *         description="Refresh token obtido no login",
 *         @OA\JsonContent(
 *             required={"refresh_token"},
 *             @OA\Property(
 *                 property="refresh_token",
 *                 type="string",
 *                 format="uuid",
 *                 description="Token de atualização recebido no login ou no último refresh",
 *                 example="87368cbd-d979-46b4-9f92-7e27b5a0a1c2"
 *             )
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=200,
 *         description="Token renovado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Token renovado com sucesso."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="tokens",
 *                     type="object",
 *                     description="Novos tokens de autenticação",
 *                     @OA\Property(
 *                         property="access_token",
 *                         type="string",
 *                         description="Novo token JWT para autenticação (expira em 15 minutos)",
 *                         example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
 *                     ),
 *                     @OA\Property(
 *                         property="refresh_token",
 *                         type="string",
 *                         format="uuid",
 *                         description="Novo refresh token (o anterior foi revogado)",
 *                         example="9d2f1c3e-8a7b-4f2e-b9c1-5e8d7f4a2b1c"
 *                     ),
 *                     @OA\Property(
 *                         property="token_type",
 *                         type="string",
 *                         example="Bearer"
 *                     ),
 *                     @OA\Property(
 *                         property="expires_in",
 *                         type="integer",
 *                         description="Tempo de expiração do novo access_token em segundos",
 *                         example=900
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="meta", type="object", example={})
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Limit",
 *             description="Número máximo de requisições permitidas",
 *             @OA\Schema(type="integer", example=10)
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Remaining",
 *             description="Número de requisições restantes",
 *             @OA\Schema(type="integer", example=9)
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=401,
 *         description="Refresh token inválido, expirado ou já usado",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Token de atualização inválido."),
 *             @OA\Property(property="code", type="string", example="INVALID_REFRESH_TOKEN"),
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
 *         description="Muitas tentativas de renovação",
 *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
 *     )
 * )
 */
class RefreshDoc {}