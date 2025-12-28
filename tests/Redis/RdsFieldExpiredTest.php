<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Predis\Client;
use Weiran\Core\Classes\WeiranCoreDef;
use Weiran\Core\Redis\RdsDb;
use Weiran\Core\Redis\RdsFieldExpired;

/**
 * redis 字段filed有效期
 */
class RdsFieldExpiredTest extends RdsBaseTest
{
    /**
     * 设置有效期
     */
    public function testSetFieldExpireTime()
    {
        $database = 'default';
        $types    = [
            RdsFieldExpired::TYPE_ZSET,
            RdsFieldExpired::TYPE_SET,
            RdsFieldExpired::TYPE_HASH,
        ];
        $expired  = [1, 5, 60, 300];

        $caches = [];
        $count  = 100;

        $keyPrefix = uniqid('test', true);
        for ($i = 1; $i <= $count; $i++) {
            $type   = $this->faker()->randomElement($types);
            $key    = $keyPrefix . '_' . $type;
            $expire = $this->faker()->randomElement($expired);

            switch ($type) {
                case 'hash':
                    $this->rds->hset($key, $i, $i);
                    break;
                case 'set':
                    $this->rds->sadd($key, $i);
                    break;
                case 'zset':
                    $this->rds->zadd($key, [
                        $i => $i * 10,
                    ]);
                    break;
            }

            $fullName = $this->rds->keyName($key);

            RdsFieldExpired::setFieldExpireTime($fullName, $i, $type, $database, $expire);

            $caches[$database . '-' . $fullName] = [
                'database' => $database,
                'key'      => $fullName,
            ];

            $this->rds->disconnect();
        }

        $this->assertEquals($count, $this->cacheCount($caches));

    }

    /**
     * 过期
     */
    public function testExpired()
    {
        $cache = new Client(config('database.redis.default'));
        $cache->multi();
        $beforeCount = $cache->zcard(WeiranCoreDef::ckRdsKeyFieldExpired());

        (new RdsFieldExpired())->clearExpiredField();

        $afterCount = $cache->zcard(WeiranCoreDef::ckRdsKeyFieldExpired());

        $cache->exec();
        $this->assertGreaterThanOrEqual($beforeCount, $afterCount);
    }

    private function cacheCount($caches): int
    {
        $count = 0;
        foreach ($caches as $item) {
            $database = $item['database'];
            $key      = $item['key'];

            [, $type] = explode('_', $key);

            $cache = new RdsDb($database);
            switch ($type) {
                case 'hash':
                    $count += $cache->hlen($key);
                    break;
                case 'set':
                    $count += $cache->scard($key);
                    break;
                case 'zset':
                    $count += $cache->zcard($key);
                    break;
            }
        }

        return $count;
    }
}
