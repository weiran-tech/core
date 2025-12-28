<?php

declare(strict_types = 1);

namespace Weiran\Core\Redis;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\InteractsWithTime;

class Cache
{
    use InteractsWithTime;

    private RdsDb $store;

    private function __construct(string $tag)
    {
        $this->store = new RdsDb('', $tag);
    }

    /**
     * @param string $tag tag
     */
    public static function of(string $tag = ''): Cache
    {
        return new self($tag);
    }

    public function getStore(): RdsDb
    {
        return $this->store;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @return mixed|string|null
     */
    public function get(string $key, $default = null)
    {
        return $this->store->get($key) ?? value($default);
    }

    /**
     * @param int|null $ttl seconds
     */
    public function put(string $key, $value, $ttl = null): bool
    {
        if ($ttl === null) {
            return $this->forever($key, $value);
        }

        $seconds = $this->getSeconds($ttl);
        if ($seconds <= 0) {
            return $this->forget($key);
        }

        return $this->store->setEx($key, (int) max(1, $seconds), $value);
    }

    /**
     * @param int|null $ttl seconds
     */
    public function set(string $key, $value, $ttl = null): bool
    {
        return $this->put($key, $value, $ttl);
    }

    public function forever(string $key, $value): bool
    {
        return $this->store->set($key, $value);
    }

    /**
     * @param int $ttl seconds
     *
     * @return mixed|string
     */
    public function remember(string $key, int $ttl, Closure $callback)
    {
        $value = $this->store->get($key);
        if ($value !== null) {
            return $value;
        }

        $this->put($key, $value = $callback(), $ttl);

        return $value;
    }

    /**
     * @return mixed|string
     */
    public function rememberForever(string $key, Closure $callback)
    {
        $value = $this->store->get($key);
        if ($value !== null) {
            return $value;
        }

        $this->forever($key, $value = $callback());

        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    public function delete(string $key): bool
    {
        return $this->store->del($key) > 0;
    }

    protected function getSeconds($ttl): int
    {
        $duration = $this->parseDateInterval($ttl);

        if ($duration instanceof DateTimeInterface) {
            $duration = Carbon::now()->diffInRealSeconds($duration, false);
        }

        return (int) $duration > 0 ? $duration : 0;
    }
}
