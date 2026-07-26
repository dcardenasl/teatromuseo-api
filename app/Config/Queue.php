<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Queue extends BaseConfig
{
    /**
     * Queue driver
     * Options: 'database', 'redis', 'sync'
     *
     * @var string
     */
    public string $driver = 'database';

    /**
     * Default queue name
     *
     * @var string
     */
    public string $defaultQueue = 'default';

    /**
     * Maximum number of attempts before marking job as failed
     *
     * @var int
     */
    public int $maxAttempts = 3;

    /**
     * Retry delay in seconds
     *
     * @var int
     */
    public int $retryAfter = 90;

    /**
     * Database connection for queue storage
     *
     * @var string
     */
    public string $databaseConnection = 'default';

    /**
     * Redis configuration
     *
     * @var array<string, mixed>
     */
    public array $redis = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ];

    public function __construct()
    {
        parent::__construct();

        $defaultConnection = ENVIRONMENT === 'testing' ? 'tests' : 'default';

        // Load from environment
        $this->driver = env('QUEUE_DRIVER', 'database');
        $this->maxAttempts = (int) env('QUEUE_MAX_ATTEMPTS', 3);
        $this->retryAfter = (int) env('QUEUE_RETRY_AFTER', 90);
        $this->databaseConnection = env('QUEUE_DATABASE_CONNECTION', $defaultConnection);

        // Redis configuration from environment
        $this->redis['host'] = env('QUEUE_REDIS_HOST', '127.0.0.1');
        $this->redis['port'] = (int) env('QUEUE_REDIS_PORT', 6379);
        $this->redis['password'] = env('QUEUE_REDIS_PASSWORD', null);
        $this->redis['database'] = (int) env('QUEUE_REDIS_DATABASE', 0);
    }
}
