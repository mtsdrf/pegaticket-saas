<?php

namespace App\Docs\PtBR\Auth;

/**
 * @OA\Post(
 *     path="/api/v1/auth/logout",
 *     operationId="authLogout",
 *     tags={"Autenticação"},
 *     summary="Realizar logout",
 *     description="Revoga o token de acesso atual adicionando-o à blacklist e encerrando a sessão do usuário.
 * 
 * **Importante:**
 * - O token atual não poderá mais ser usado após o logout
 * - Outros dispositivos/sessões do mesmo usuário não são afetados
 * - Tokens na blacklist são mantidos até sua data de expiração original
 * - A ação de logout é registrada nos logs de auditoria
 * 
 * **Após o logout:**
 * - Use o `refresh_token` salvo anteriormente para obter novo access_token, OU
 * - Faça login novamente com email e senha",
 *     security={{"bearerAuth": {}}},
 *     
 *     @OA\Response(
 *         response=200,
 *         description="Logout realizado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Logout realizado com sucesso."),
 *             @OA\Property(property="data", type="null", example=null),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=401,
 *         description="Token não fornecido ou inválido",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Token não fornecido."),
 *             @OA\Property(property="code", type="string", example="TOKEN_NOT_PROVIDED"),
 *             @OA\Property(property="errors", type="object", example={}),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=500,
 *         description="Erro interno ao processar logout",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Erro ao processar logout."),
 *             @OA\Property(property="code", type="string", example="LOGOUT_ERROR"),
 *             @OA\Property(property="errors", type="object", example={}),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     )
 * )
 */
class LogoutDoc {}