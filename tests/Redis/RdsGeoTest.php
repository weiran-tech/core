<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;


class RdsGeoTest extends RdsBaseTest
{

    public function testGeo()
    {
        // Add
        $key = $this->key('geo');
        $this->rds->del($key);
        $count = 1000;
        for ($i = 1; $i <= $count; $i++) {
            $this->rds->geoAdd($key, $this->faker()->longitude(), $this->faker()->latitude(), 'pos-' . $i);
        }
        $this->assertTrue(true);

        // GeoPos
        $pos = $this->rds->geoPos($key, 'no-exist');
        $this->assertNull($pos[0]);

        $pos = $this->rds->geoPos($key, 'pos-1');
        $this->assertCount(2, $pos[0]);

        $pos = $this->rds->geoPos($key, ['pos-1', 'pos-2']);
        $this->assertCount(2, $pos);

        // GeoDist
        $dist = $this->rds->geoDist($key, 'pos-1', 'pos-2');
        $this->assertGreaterThan(0, $dist);

        // 有一个未存在的, 返回null
        $dist = $this->rds->geoDist($key, 'pos-1', 'pos-no-exit');
        $this->assertNull($dist);


    }

    public function testRadius()
    {
        // Add
        $key      = $this->key('geo-dist');
        $keyStore = $this->key('geo-store');
        $this->rds->del($key);
        $count = 1000;
        for ($i = 1; $i <= $count; $i++) {
            $this->rds->geoAdd($key, $this->faker()->longitude(), $this->faker()->latitude(), 'pos-' . $i);
        }

        // 返回数量
        $members = $this->rds->geoRadiusByMember($key, 'pos-1', 2000, 'km', [
            'storedist' => $keyStore, 'count' => '100',
        ]);

        $this->assertIsInt($members);

        // 返回用户
        $members = $this->rds->geoRadiusByMember($key, 'pos-1', 2000, 'km', [
            'count' => '100',
        ]);

        $this->assertIsArray($members);

        // 获取数据
        $members = $this->rds->geoRadius($key, $this->faker()->longitude(), $this->faker()->latitude(), '2000', 'km', [
            'count' => '100',
        ]);

        $this->assertIsArray($members);

        // 获取并存储
        $members = $this->rds->geoRadius($key, $this->faker()->longitude(), $this->faker()->latitude(), '2000', 'km', [
            'storedist' => $keyStore . '-radius',
        ]);
        $this->assertIsInt($members);

        // 返回数组类型, 不存在为 [null]
        $hash = $this->rds->geoHash($key, 'not-exist');
        $this->assertEmpty($hash[0]);

        // 返回数组,
        $hash = $this->rds->geoHash($key, ['pos-1', 'pos-2']);
        $this->assertCount(2, $hash);
    }
}
