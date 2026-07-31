<?php

namespace App\Docs\En\Auth;

/**
 * @OA\Post(
 *     path="/api/v1/auth/logout",
 *     operationId="authLogout",
 *     tags={"Authentication"},
 *     summary="User logout",
 *     description="Revokes the current access token by adding it to the blacklist and ending the user's session.
 * 
 * **Important:**
 * - The current token cannot be used after logout
 * - Other devices/sessions of the same user are not affected
 * - Blacklisted tokens are kept until their original expiration date
 * - The logout action is recorded in audit logs
 * 
 * **After logout:**
 * - Use the previously saved `refresh_token` to obtain a new access_token, OR
 * - Login again with email and password",
 *     security={{"bearerAuth": {}}},
 *     
 *     @OA\Response(
 *         response=200,
 *         description="Logout successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Logout successful."),
 *             @OA\Property(property="data", type="null", example=null),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=401,
 *         description="Token not provided or invalid",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Token not provided."),
 *             @OA\Property(property="code", type="string", example="TOKEN_NOT_PROVIDED"),
 *             @OA\Property(property="errors", type="object", example={}),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=500,
 *         description="Internal error processing logout",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Error processing logout."),
 *             @OA\Property(property="code", type="string", example="LOGOUT_ERROR"),
 *             @OA\Property(property="errors", type="object", example={}),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     )
 * )
 */
class LogoutDoc {}