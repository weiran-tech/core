<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Illuminate\Support\Arr;

class RdsZSetTest extends RdsBaseTest
{

    public function testZAdd()
    {
        $key = $this->key('z-add');
        $this->rds->del($key);

        $result = $this->rds->zAdd($key, [
            'a' => 111,
            'c' => 1,
            'f' => 238,
        ]);
        $this->assertEquals(3, $result);

        $result = $this->rds->zAdd($key, [
            'd' => '369',
        ]);
        $this->assertEquals(1, $result);

        $result = $this->rds->zAdd($key, [
            'a' => '22',
        ]);
        $this->assertEquals(0, $result);

        $this->rds->del($key);
    }

    public function testZScore()
    {
        $key = $this->key('z-score');
        $this->rds->del($key);

        $this->rds->zAdd($key, [
            'a' => '111',
        ]);

        $score = $this->rds->zScore($key, 'a');
        $this->assertEquals(111, $score);
        $score = $this->rds->zScore($key, 'no-exists');
        $this->assertEquals(null, $score);
        $this->rds->del($key);
    }

    public function testZIncrBy()
    {
        $key = $this->key('z-incr');
        $this->rds->del($key);

        $this->rds->zAdd($key, [
            'a' => '111',
        ]);

        $score = $this->rds->zIncrBy($key, 20, 'a');
        $this->assertEquals(131, $score);
        $score = $this->rds->zIncrBy($key, 100, 'no-exists');
        $this->assertEquals(100, $score);
        $this->rds->del($key);
    }


    public function testZCard()
    {
        $key = $this->key('z-card');
        $this->rds->del($key);

        $this->rds->zAdd($key, [
            'a' => '111',
        ]);

        $score = $this->rds->zCard($key);
        $this->assertEquals(1, $score);
        $this->rds->zIncrBy($key, 100, 'no-exists');
        $score = $this->rds->zCard($key);
        $this->assertEquals(2, $score);
        $this->rds->del($key);
    }


    public function testZCount()
    {
        $key = $this->key('z-count');
        $this->rds->del($key);

        $range = range(1, 200);
        $add   = [];
        foreach ($range as $value) {
            $add[$this->faker()->userName] = $value;
        }

        $this->rds->zAdd($key, $add);

        $count = $this->rds->zCount($key, 180, 200);
        $this->assertEquals(21, $count);
        $this->rds->del($key);
    }

    public function testZRange()
    {
        $key = $this->key('z-range');
        $this->rds->del($key);

        $range = range(1, 200);
        $add   = [];
        foreach ($range as $value) {
            $add[$this->faker()->userName] = $value;
        }
        $this->rds->zAdd($key, $add);
        $values = $this->rds->zRange($key, 20, 25, [
            'withscores' => 1,
        ]);

        $first = Arr::first($values);
        $last  = Arr::last($values);

        $this->assertEquals(21, $first);
        $this->assertEquals(26, $last);

        $this->assertCount(6, $values);

        $this->rds->del($key);
    }

    public function testZRevRange()
    {
        $key = $this->key('z-rev-range');
        $this->rds->del($key);

        $range = range(1, 200);
        $add   = [];
        foreach ($range as $value) {
            $add[$this->faker()->userName] = $value;
        }
        $this->rds->zAdd($key, $add);
        $values = $this->rds->zRevRange($key, 0, 10, [
            'withscores' => 1,
        ]);

        $first = Arr::first($values);
        $last  = Arr::last($values);

        $this->assertEquals(200, $first);
        $this->assertEquals(190, $last);

        $this->assertCount(11, $values);

        $this->rds->del($key);
    }

    public function testZRangeByScore()
    {
        $key = $this->key('z-range-by-score');
        $this->rds->del($key);

        $range = range(1, 200);
        $add   = [];
        foreach ($range as $value) {
            $add[$this->faker()->userName] = $value;
        }
        $this->rds->zAdd($key, $add);
        $values = $this->rds->zRangeByScore($key, 0, 10, [
            'withscores' => 1,
        ]);

        $this->assertCount(10, $values);

        $this->rds->del($key);
    }


    public function testZRevRangeByScore()
    {
        $key = $this->key('z-rev-range-by-score');
        $this->rds->del($key);

        $range = range(1, 200);
        $add   = [];
        foreach ($range as $value) {
            $add[$this->faker()->userName] = $value;
        }
        $this->rds->zAdd($key, $add);
        $values = $this->rds->zRangeByScore($key, 1, 10, [
            'withscores' => 1,
        ]);

        $this->assertCount(10, $values);

        $this->rds->del($key);
    }


    public function testZRank()
    {
        $key = $this->key('z-rank');
        $this->rds->del($key);

        $range      = range(1, 200);
        $add        = [];
        $member     = '';
        $memberLast = '';
        foreach ($range as $index => $value) {
            $username     = $this->faker()->userName;
            $unique       = $username . '-' . $value;
            $add[$unique] = $value;
            if ($index === 30) {
                $member = $unique;
            }
            if ($index === 200) {
                $memberLast = $unique;
            }
        }
        $this->rds->zAdd($key, $add);

        $rank = $this->rds->zRank($key, $member);

        $this->assertEquals(30, $rank);

        $rank = $this->rds->zRevRank($key, $memberLast);

        $this->assertEquals(0, $rank);

        $this->rds->del($key);
    }


    public function testZRem()
    {
        $key = $this->key('z-rem');
        $this->rds->del($key);
        $range   = range(1, 200);
        $add     = [];
        $members = [];
        foreach ($range as $value) {
            $username       = $this->faker()->userName;
            $add[$username] = $value;
            $members[]      = $username;
        }
        $this->rds->zAdd($key, $add);

        $first = array_pop($members);

        $next = [array_pop($members), array_pop($members)];

        $res = $this->rds->zRem($key, $first);
        $this->assertEquals(1, $res);

        $res = $this->rds->zRem($key, $next);
        $this->assertEquals(2, $res);

        $res = $this->rds->zRem($key, 'no-exist-key');
        $this->assertEquals(0, $res);
        $this->rds->del($key);
    }

    public function testZRemRangeByRank()
    {
        $key = $this->key('z-rem-range-by-rank');
        $this->rds->del($key);
        $range = range(1, 200);
        $add   = [];
        foreach ($range as $i => $value) {
            $username                  = $this->faker()->userName;
            $add[$username . '-' . $i] = $value;
        }
        $this->rds->zAdd($key, $add);

        $res = $this->rds->zRemRangeByRank($key, 0, 19);
        $this->assertEquals(20, $res);

        $count = $this->rds->zCard($key);
        $this->assertEquals(180, $count);
        $this->rds->del($key);
    }

    public function testZRemRangeByScore()
    {
        $key = $this->key('z-rem-range-by-score');
        $this->rds->del($key);
        $range = range(1, 200);
        $add   = [];
        foreach ($range as $value) {
            $add[$this->faker()->userName . '-' . $value] = $value;
        }
        $this->rds->zAdd($key, $add);

        $res = $this->rds->zRemRangeByScore($key, 1, 20);
        $this->assertEquals(20, $res);

        $count = $this->rds->zCard($key);
        $this->assertEquals(180, $count);
        $this->rds->del($key);
    }


    public function testZScan()
    {
        $key = $this->key('z-scan');
        $this->rds->del($key);
        $this->rds->zAdd($key, [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);
        $request = true;
        $result  = [];
        $cursor  = 0;
        while ($request) {
            // 使用循环来获取数据, 不支持排序(无序列表)
            $val = $this->rds->zScan($key, $cursor, [
                'count' => 1,
            ]);
            array_push($result, ...$val[1]);
            if ($val[0]) {
                $cursor = $val[0];
            }
            else {
                $this->assertCount(3, $result);
                $request = false;
            }
        }
        $this->rds->del($key);
    }

    public function testZUnionStore()
    {
        $key      = $this->key('z-union');
        $keyStore = $this->key('z-union-store');
        $key2     = $this->key('z-union-2');
        $this->rds->del([$key, $key2, $keyStore]);
        $this->rds->zAdd($key, [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);
        $this->rds->zAdd($key2, [
            'a'  => 100,
            'b2' => 200,
            'c'  => 300,
        ]);

        $this->rds->zUnionStore($keyStore, [
            $key, $key2,
        ], [
            'weights' => [3, 9],
        ]);
        $values = $this->rds->zRange($keyStore, 0, -1, [
            'withscores' => '1',
        ]);

        $this->assertCount(4, $values);
    }


    public function testZInterStore()
    {
        $key      = $this->key('z-inter');
        $keyStore = $this->key('z-inter-store');
        $key2     = $this->key('z-inter-2');
        $this->rds->del([$key, $key2, $keyStore]);
        $this->rds->zAdd($key, [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);
        $this->rds->zAdd($key2, [
            'a'  => 100,
            'b2' => 200,
            'c'  => 300,
        ]);

        $this->rds->zInterStore($keyStore, [
            $key, $key2,
        ], [
            'weights' => [3, 9],
        ]);
        $values = $this->rds->zRange($keyStore, 0, -1, [
            'withscores' => '1',
        ]);

        $this->assertCount(2, $values);
    }
}