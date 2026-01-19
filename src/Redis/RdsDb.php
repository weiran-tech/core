<?php

declare(strict_types = 1);

namespace Weiran\Core\Redis;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Throwable;

/**
 * @mixin RdsNative
 */
class RdsDb
{
    private static array $handleRepo;

    private RdsNative $handler;

    /**
     * Handle constructor.
     *
     * @param string $tag 4.1 支持标签化的缓存
     */
    public function __construct(string $database = '', string $tag = '')
    {
        $database      = $database ?: 'default';
        $config        = config('database.redis.' . $database);
        $this->handler = new RdsNative($config, $tag);
    }

    /**
     * 数据库单例
     *
     * @param string $db  数据库
     * @param string $tag 标签
     */
    public static function instance(string $db = 'default', string $tag = ''): self
    {
        $key = $db . ($tag ? '-' . $tag : '');
        if (!isset(self::$handleRepo[$key])) {
            self::$handleRepo[$key] = new self($db, $tag);
        }

        return self::$handleRepo[$key];
    }

    public static function __callStatic($method, $arguments)
    {
        return (new self())->$method(...$arguments);
    }

    /**
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $value = $this->handler->$method(...$arguments);
        if ($method !== 'get') {
            return $value;
        }

        $key = (string) sys_get($arguments, 0);
        if ($value === null) {
            event(new CacheMissed($key));
        }
        else {
            event(new CacheHit($key, $value));
        }

        return $value;

    }

    public function __destruct()
    {
        try {
            $this->handler->disconnect();
        }
        catch (Throwable $e) {

        }
    }
}
