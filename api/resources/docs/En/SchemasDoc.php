<?php

namespace App\Docs\En;

/**
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     title="Success Response",
 *     description="Standard API success response structure",
 *     required={"success", "data"},
 *     @OA\Property(property="success", type="boolean", example=true, description="Indicates if the request was successful"),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully.", description="Human-readable success message"),
 *     @OA\Property(property="data", description="Response data (can be object, array, or null)"),
 *     @OA\Property(property="meta", type="object", description="Additional metadata (pagination, timestamps, etc)", example={})
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Standard API error response structure",
 *     required={"success", "message", "code"},
 *     @OA\Property(property="success", type="boolean", example=false, description="Always false for error responses"),
 *     @OA\Property(property="message", type="string", example="An error occurred while processing the request.", description="Human-readable error message"),
 *     @OA\Property(property="code", type="string", example="ERROR_CODE", description="Unique error code for identification and programmatic handling"),
 *     @OA\Property(property="errors", type="object", description="Specific error details (empty if no details available)", example={}),
 *     @OA\Property(property="meta", type="object", description="Additional error metadata", example={})
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     title="Validation Error Response",
 *     description="Response returned when there are validation errors in submitted data",
 *     required={"success", "message", "code", "errors"},
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Validation error."),
 *     @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Validation errors organized by field",
 *         example={
 *             "email": {"The email field is required.", "The email must be valid."},
 *             "password": {"The password must be at least 8 characters."}
 *         }
 *     ),
 *     @OA\Property(property="meta", type="object", example={})
 * )
 * 
 * @OA\Schema(
 *     schema="RateLimitErrorResponse",
 *     type="object",
 *     title="Rate Limit Exceeded Response",
 *     description="Response when request rate limit per minute is exceeded",
 *     required={"success", "message", "code"},
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Too many attempts. Please try again in 1 minute(s)."),
 *     @OA\Property(property="code", type="string", example="TOO_MANY_REQUESTS"),
 *     @OA\Property(property="errors", type="object", example={}),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="retry_after_seconds", type="integer", example=60, description="Seconds until you can try again"),
 *         @OA\Property(property="retry_after", type="string", format="date-time", example="2025-01-04T23:46:00+00:00", description="ISO-8601 timestamp of when you can try again")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     title="Pagination Metadata",
 *     description="Information about result pagination",
 *     @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
 *     @OA\Property(property="from", type="integer", example=1, description="Index of first item on current page (1-based)"),
 *     @OA\Property(property="last_page", type="integer", example=10, description="Last available page number"),
 *     @OA\Property(property="per_page", type="integer", example=15, description="Number of items per page"),
 *     @OA\Property(property="to", type="integer", example=15, description="Index of last item on current page (1-based)"),
 *     @OA\Property(property="total", type="integer", example=150, description="Total number of items across all pages"),
 *     @OA\Property(
 *         property="links",
 *         type="object",
 *         description="Navigation URLs between pages",
 *         @OA\Property(property="first", type="string", example="http://api.com/users?page=1"),
 *         @OA\Property(property="last", type="string", example="http://api.com/users?page=10"),
 *         @OA\Property(property="prev", type="string", nullable=true, example=null),
 *         @OA\Property(property="next", type="string", example="http://api.com/users?page=2")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="UUID",
 *     type="string",
 *     format="uuid",
 *     pattern="^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$",
 *     example="8796e85c-7e9c-4788-a503-840ede8cf78a",
 *     description="Universally Unique Identifier (UUID version 4)"
 * )
 * 
 * @OA\Schema(
 *     schema="Timestamp",
 *     type="string",
 *     format="date-time",
 *     example="2025-01-04T23:30:00.000000Z",
 *     description="Date and time in ISO-8601 format (UTC)"
 * )
 */
class SchemasDoc {}