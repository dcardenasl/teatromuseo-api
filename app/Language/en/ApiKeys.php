<?php

declare(strict_types=1);

/**
 * API Key language strings (English)
 */
return [
    // General
    'notFound'    => 'API key not found',
    'idRequired'  => 'API key ID is required',

    // Validation
    'nameRequired'   => 'API key name is required',
    'fieldRequired'  => 'At least one field must be provided for update',
    'validation' => [
        'name' => [
            'maxLength' => 'Name cannot exceed {0} characters',
        ],
        'keyPrefix' => [
            'required' => 'Key prefix is required',
            'maxLength' => 'Key prefix cannot exceed {0} characters',
        ],
        'keyHash' => [
            'required' => 'Key hash is required',
            'maxLength' => 'Key hash cannot exceed {0} characters',
        ],
        'rateLimitRequests' => [
            'integer' => 'Rate limit requests must be an integer',
            'greaterThan' => 'Rate limit requests must be greater than 0',
        ],
        'rateLimitWindow' => [
            'integer' => 'Rate limit window must be an integer',
            'greaterThan' => 'Rate limit window must be greater than 0',
        ],
        'userRateLimit' => [
            'integer' => 'User rate limit must be an integer',
            'greaterThan' => 'User rate limit must be greater than 0',
        ],
        'ipRateLimit' => [
            'integer' => 'IP rate limit must be an integer',
            'greaterThan' => 'IP rate limit must be greater than 0',
        ],
    ],

    // Success messages
    'createdSuccess' => 'API key created successfully. Store the key securely — it will not be shown again.',
    'deletedSuccess' => 'API key deleted successfully',

    // Error messages
    'createError'  => 'Failed to create API key',
    'deleteError'  => 'Failed to delete API key',
    'retrieveError' => 'Failed to retrieve created API key',

    // Auth / rate limiting
    'invalidKey'   => 'The provided API key is invalid or inactive',
    'unauthorized' => 'Unauthorized',
];
