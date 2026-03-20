<?php

/**
 * @OA\Info(
 *     title="Spicy Noodle Management API",
 *     version="1.0.0",
 *     description="REST API cho hệ thống quản lý nhà hàng mỳ cay",
 *     @OA\Contact(
 *         name="API Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="https://seoul-spicy-production.up.railway.app/api",
 *     description="Production server"
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local development server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Users",
 *     description="User management (Admin only)"
 * )
 * 
 * @OA\Tag(
 *     name="Customers",
 *     description="Customer management"
 * )
 * 
 * @OA\Tag(
 *     name="Categories",
 *     description="Category management"
 * )
 */

class OpenApiInfo {
    // This class only contains OpenAPI annotations
}
