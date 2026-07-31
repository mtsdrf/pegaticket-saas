<?php

namespace App\Docs\En\Auth;

/**
 * @OA\Post(
 *     path="/api/v1/auth/login",
 *     operationId="authLogin",
 *     tags={"Authentication"},
 *     summary="User login",
 *     description="Authenticates a user and returns access (JWT) and refresh tokens.
 * 
 * **Flow:**
 * 1. Send email and password
 * 2. Receive `access_token` (valid for 15 minutes)
 * 3. Receive `refresh_token` (valid for 30 days)
 * 4. Use the `access_token` in all protected requests
 * 
 * **Security:**
 * - Rate limiting: maximum 5 attempts per minute
 * - Password is verified with bcrypt hash
 * - All attempts (success/failure) are logged in audit logs
 * - Supports multiple simultaneous devices (each login generates a new refresh token)",
 *     
 *     @OA\RequestBody(
 *         required=true,
 *         description="User authentication credentials",
 *         @OA\JsonContent(
 *             required={"email", "password"},
 *             @OA\Property(
 *                 property="email",
 *                 type="string",
 *                 format="email",
 *                 description="User's email address",
 *                 example="admin@company.com"
 *             ),
 *             @OA\Property(
 *                 property="password",
 *                 type="string",
 *                 format="password",
 *                 description="User's password (minimum 8 characters)",
 *                 example="Password@123"
 *             )
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Login successful."),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(
 *                     property="user",
 *                     type="object",
 *                     description="Authenticated user's basic data",
 *                     @OA\Property(property="uuid", type="string", format="uuid", example="8796e85c-7e9c-4788-a503-840ede8cf78a"),
 *                     @OA\Property(property="name", type="string", example="John Doe"),
 *                     @OA\Property(property="email", type="string", format="email", example="john@company.com"),
 *                     @OA\Property(property="is_active", type="boolean", example=true),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T10:00:00.000000Z")
 *                 ),
 *                 @OA\Property(
 *                     property="tokens",
 *                     type="object",
 *                     description="Authentication tokens",
 *                     @OA\Property(
 *                         property="access_token",
 *                         type="string",
 *                         description="JWT token for request authentication (expires in 15 minutes)",
 *                         example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
 *                     ),
 *                     @OA\Property(
 *                         property="refresh_token",
 *                         type="string",
 *                         format="uuid",
 *                         description="Token to renew access_token without logging in again (expires in 30 days)",
 *                         example="87368cbd-d979-46b4-9f92-7e27b5a0a1c2"
 *                     ),
 *                     @OA\Property(
 *                         property="token_type",
 *                         type="string",
 *                         description="Token type (always 'Bearer')",
 *                         example="Bearer"
 *                     ),
 *                     @OA\Property(
 *                         property="expires_in",
 *                         type="integer",
 *                         description="Access token expiration time in seconds",
 *                         example=900
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="meta", type="object", example={})
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Limit",
 *             description="Maximum number of allowed requests",
 *             @OA\Schema(type="integer", example=5)
 *         ),
 *         @OA\Header(
 *             header="X-RateLimit-Remaining",
 *             description="Number of remaining requests",
 *             @OA\Schema(type="integer", example=4)
 *         )
 *     ),
 *     
 *     @OA\Response(
 *         response=401,
 *         description="Invalid credentials",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Invalid credentials."),
 *             @OA\Property(property="code", type="string", example="INVALID_CREDENTIALS"),
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
 *         description="Too many login attempts",
 *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
 *     )
 * )
 */
class LoginDoc {}