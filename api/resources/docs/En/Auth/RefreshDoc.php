<?php

namespace App\Docs\En\Auth;

/**
 * @OA\Post(
 *     path="/api/v1/auth/refresh",
 *     operationId="authRefresh",
 *     tags={"Authentication"},
 *     summary="Refresh access token",
 *     description="Renews the access token (access_token) using a valid refresh_token, without needing to login again.
 * 
 * **Token Rotation:**
 * - The sent `refresh_token` is REVOKED after use
 * - A NEW `refresh_token` is generated and returned
 * - This prevents refresh token reuse and increases security
 * - The new `access_token` is valid for 15 minutes
 * - The new `refresh_token` is valid for 30 days
 * 
 * **When to use:**
 * - When the `access_token` expires (after 15 minutes)
 * - To keep the user logged in without asking for credentials again
 * - Implement automatic renewal in the frontend when receiving 401 error with code TOKEN_EXPIRED
 * 
 * **Security:**
 * - Rate limiting: maximum 10 attempts per minute
 * - Refresh tokens are hashed with SHA-512 in the database
 * - IP and User-Agent validation (optional, configurable)
 * - All renewals are recorded in audit logs",
 *     
 *     @OA\RequestBody(
 *         required=true,
 *         description="Refresh token obtained at login",
 *         @OA\JsonContent(
 *             required={"refresh_token"},
 *             @OA\Property(
 *                 property="refresh_token",
 *                 type="string",
 *                 format="uuid",
 *                 description="Refresh token received at login or last refresh",
 *                 example="87368cbd-d979-46b4-9f92-7e27b5a0a1c2"
 *             )
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=200,
 *         description="Token refreshed successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Token refreshed successfully."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="tokens",
 *                     type="object",
 *                     description="New authentication tokens",
 *                     @OA\Property(
 *                         property="access_token",
 *                         type="string",
 *                         description="New JWT token for authentication (expires in 15 minutes)",
 *                         example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
 *                     ),
 *                     @OA\Property(
 *                         property="refresh_token",
 *                         type="string",
 *                         format="uuid",
 *                         description="New refresh token (the previous one was revoked)",
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
 *                         description="Expiration time of the new access_token in seconds",
 *                         example=900
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="meta", type="object", example={})
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Limit",
 *             description="Maximum number of allowed requests",
 *             @OA\Schema(type="integer", example=10)
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Remaining",
 *             description="Number of remaining requests",
 *             @OA\Schema(type="integer", example=9)
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=401,
 *         description="Invalid, expired, or already used refresh token",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Invalid refresh token."),
 *             @OA\Property(property="code", type="string", example="INVALID_REFRESH_TOKEN"),
 *             @OA\Property(property="errors", type="object", example={}),
 *             @OA\Property(property="meta", type="object", example={})
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 *     ),
 *     
 *     @OA\Response(
 *         response=429,
 *         description="Too many refresh attempts",
 *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
 *     )
 * )
 */
class RefreshDoc {}