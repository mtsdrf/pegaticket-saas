<?php

namespace App\Docs\En;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="Enterprise API",
 *         description="Complete RESTful API with JWT authentication, granular permissions system, audit logging, and rate limiting. This API provides programmatic access to all enterprise management system resources.",
 *         termsOfService="https://company.com/terms-of-service",
 *         @OA\Contact(
 *             name="API Support Team",
 *             email="api-support@company.com",
 *             url="https://company.com/support"
 *         ),
 *         @OA\License(
 *             name="Proprietary",
 *             url="https://company.com/license"
 *         )
 *     ),
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST,
 *         description="Local Development Server"
 *     ),
 *     @OA\Server(
 *         url="https://api.company.com",
 *         description="Production Server"
 *     ),
 *     @OA\Server(
 *         url="https://staging-api.company.com",
 *         description="Staging Server"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="JWT Bearer Token authentication. Format: `Bearer {your_token_here}`
 * 
 * **How to obtain a token:**
 * 1. Use the `POST /api/v1/auth/login` endpoint with your credentials
 * 2. Copy the `access_token` value from the response
 * 3. Click the 'Authorize' button above
 * 4. Paste the token in the format: `Bearer eyJ0eXAiOiJKV1Q...`
 * 
 * **Important:**
 * - Tokens expire in 15 minutes (900 seconds)
 * - Use the `refresh_token` to renew without logging in again
 * - Revoked tokens (after logout) cannot be reused"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Authentication and session management endpoints.
 * 
 * **Authentication Flow:**
 * 1. **Login:** Obtain `access_token` and `refresh_token`
 * 2. **Usage:** Include `access_token` in the header of all protected requests
 * 3. **Renewal:** Use `refresh_token` when access token expires
 * 4. **Logout:** Revoke current token to end session
 * 
 * **Security:**
 * - Rate limiting: 5 login attempts per minute
 * - Tokens are hashed with SHA-512
 * - All actions are logged in audit logs
 * - Support for multiple simultaneous devices",
 *     @OA\ExternalDocumentation(
 *         description="Complete Authentication Guide",
 *         url="https://docs.company.com/authentication"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Users",
 *     description="System user management operations.
 * 
 * **Required Permissions:**
 * - `users:read` - List and view users
 * - `users:create` - Create new users
 * - `users:update` - Update user data
 * - `users:delete` - Remove users (soft delete)
 * 
 * **Features:**
 * - Advanced search and filters
 * - Automatic pagination
 * - Soft delete (preserves history)
 * - Complete audit trail",
 *     @OA\ExternalDocumentation(
 *         description="User Management Guide",
 *         url="https://docs.company.com/users"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Groups",
 *     description="Group management and permission assignment.
 * 
 * **Features:**
 * - Create and manage user groups
 * - Assign granular permissions to groups
 * - Synchronize group members
 * - Role-Based Access Control (RBAC)
 * 
 * **Permissions are inherited:**
 * A user in multiple groups receives the union of all permissions.",
 *     @OA\ExternalDocumentation(
 *         description="Groups & Permissions Guide",
 *         url="https://docs.company.com/groups"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Functionalities",
 *     description="System functionalities and modules management.
 * 
 * **Functionalities** are the system modules/features that can have permissions assigned.
 * 
 * Examples: users, groups, reports, settings, etc."
 * )
 */
class BaseDoc {}