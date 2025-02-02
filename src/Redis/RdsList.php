<?php

declare(strict_types = 1);

namespace Weiran\Core\Redis;

class RdsList
{
    /**
     * 列表最大长度
     * @var int
     */
    private $maxLength;

    /**
     * @var RdsDb
     */
    private $redis;

    /**
     * @var string $cacheKey
     */
    private string $cacheKey;

    public function __construct($database = 'default', $cache_key, $max_length = 0)
    {
        $this->redis     = RdsDb::instance($database);
        $this->maxLength = $max_length;
        $this->cacheKey  = $cache_key;
    }

    /**
     * 获取所有
     * @return array
     */
    public function all(): array
    {
        return (array) $this->redis->lrange($this->cacheKey, 0, -1);
    }

    /**
     * 入队
     * @param string $item
     * @return bool
     */
    public function push($item): bool
    {
        if ($this->maxLength) {
            $length = $this->redis->llen($this->cacheKey);
            if ($length >= $this->maxLength) {
                $this->shift();
            }
        }

        $this->redis->lpush($this->cacheKey, $item);

        return true;
    }

    /**
     * 弹出第一个
     * @return string
     */
    public function shift(): string
    {
        return $this->redis->rpop($this->cacheKey);
    }

}