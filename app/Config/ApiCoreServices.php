<?php

declare(strict_types=1);

namespace Config;

use dcardenasl\Ci4ApiCore\Queue\QueueManagerInterface;

/**
 * API Core Services
 *
 * Centralizes essential infrastructure services for the API lifecycle,
 * including request orchestration, DTO factories, and queue management.
 */
trait ApiCoreServices
{
    public static function requestDataCollector(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\RequestDataCollector
    {
        if ($getShared) {
            return static::getSharedInstance('requestDataCollector');
        }

        return new \dcardenasl\Ci4ApiCore\Support\RequestDataCollector();
    }

    public static function requestDtoFactory(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\RequestDtoFactory
    {
        if ($getShared) {
            return static::getSharedInstance('requestDtoFactory');
        }

        return new \dcardenasl\Ci4ApiCore\Support\RequestDtoFactory();
    }

    public static function responseDtoFactory(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\ResponseDtoFactory
    {
        if ($getShared) {
            return static::getSharedInstance('responseDtoFactory');
        }

        return new \dcardenasl\Ci4ApiCore\Support\ResponseDtoFactory();
    }

    public static function queueManager(bool $getShared = true): QueueManagerInterface
    {
        if ($getShared) {
            return static::getSharedInstance('queueManager');
        }

        $queueConfig = config('Queue');
        $driver = strtolower(trim((string) $queueConfig->driver));

        return match ($driver) {
            'sync' => new \App\Libraries\Queue\SyncCompatibleQueueManager(),
            'redis' => new \dcardenasl\Ci4ApiCore\Queue\RedisQueueManager(self::buildRedisClient($queueConfig)),
            default => new \dcardenasl\Ci4ApiCore\Queue\QueueManager(),
        };
    }

    private static function buildRedisClient(\Config\Queue $queueConfig): \Redis
    {
        $redis = new \Redis();
        $redis->connect((string) $queueConfig->redis['host'], (int) $queueConfig->redis['port']);

        $password = (string) ($queueConfig->redis['password'] ?? '');
        if ($password !== '') {
            $redis->auth($password);
        }

        $redis->select((int) ($queueConfig->redis['database'] ?? 0));

        return $redis;
    }
}
