<?php

declare(strict_types=1);

namespace App\Config;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
)]
#[OA\Info(
    version: \Config\Project::VERSION,
    title: \Config\Project::NAME,
    description: \Config\Project::DESCRIPTION,
)]
#[OA\Server(
    url: 'http://localhost:8080',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter your JWT token in the format: Bearer {token}'
)]
#[OA\SecurityScheme(
    securityScheme: 'appKeyAuth',
    type: 'apiKey',
    name: 'X-App-Key',
    in: 'header',
    description: 'Per-application API key issued by this server, used to authenticate the calling app (no user JWT required).'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'User authentication endpoints'
)]
#[OA\Tag(
    name: 'Users',
    description: 'User management endpoints'
)]
#[OA\Tag(
    name: 'Files',
    description: 'File management endpoints'
)]
#[OA\Tag(
    name: 'Metrics',
    description: 'Operational metrics endpoints'
)]
#[OA\Tag(
    name: 'Audit',
    description: 'Audit log endpoints'
)]
#[OA\Tag(
    name: 'Health',
    description: 'Health and readiness endpoints'
)]
class OpenApi
{
    // This class only holds OpenAPI annotations
}
