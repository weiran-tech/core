<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

class RdsListTest extends RdsBaseTest
{
    public function testLPush()
    {
        $key = $this->key('l-push');
        $this->rds->lPush($key, ['1']);
        $num = $this->rds->lPush($key, [['1']]);
        $this->assertEquals(2, $num);
        $num = $this->rds->lPush($key, ['2', '3']);
        $this->assertEquals(4, $num);
        $this->rds->del($key);
    }

    public function testLPushX()
    {
        $key = $this->key('l-pushx');
        $num = $this->rds->lPushX($key, '1');
        $this->assertEquals(0, $num);
        $num = $this->rds->lPush($key, ['2', '3']);
        $this->assertEquals(2, $num);
        $this->rds->lPushX($key, '1');
        $num = $this->rds->lPushX($key, ['1']);
        $this->assertEquals(4, $num);
        $this->rds->del($key);
    }

    public function testRPush()
    {
        $key = $this->key('r-push');
        $num = $this->rds->rPush($key, ['1']);
        $this->assertEquals(1, $num);
        $num = $this->rds->rPush($key, ['2', '3']);
        $this->assertEquals(3, $num);
        $num = $this->rds->rPush($key, '1');
        $this->assertEquals(4, $num);
        $this->rds->del($key);
    }

    public function testRPushX()
    {
        $key = $this->key('r-pushx');
        $num = $this->rds->rPushX($key, '1');
        $this->assertEquals(0, $num);
        $num = $this->rds->rPush($key, ['2', '3']);
        $this->assertEquals(2, $num);
        $this->rds->rPushX($key, '1');
        $num = $this->rds->rPushX($key, '1');
        $this->assertEquals(4, $num);
        $this->rds->del($key);
    }

    public function testLPop()
    {
        $key = $this->key('l-pop');
        $this->rds->lPush($key, 'string');
        $this->rds->lPush($key, ['array']);
        $this->rds->lPush($key, [['array']]);
        $val = $this->rds->lPop($key);
        $this->assertEquals(['array'], $val);
        $val = $this->rds->lPop($key);
        $this->assertEquals('array', $val);
        $val = $this->rds->lPop($key);
        $this->assertEquals('string', $val);
        $this->rds->del($key);
    }

    public function testRPop()
    {
        $key = $this->key('l-pop');
        $this->rds->lPush($key, 'string');
        $this->rds->lPush($key, ['array']);
        $this->rds->lPush($key, [['array']]);
        $val = $this->rds->rPop($key);
        $this->assertEquals('string', $val);
        $val = $this->rds->rPop($key);
        $this->assertEquals('array', $val);
        $val = $this->rds->rPop($key);
        $this->assertEquals(['array'], $val);
        $this->rds->del($key);
    }

    public function testRPopLPush()
    {
        $key1 = $this->key('l-pop-a');
        $key2 = $this->key('r-push-a');
        $this->rds->del([$key1, $key2]);
        $this->rds->rPush($key1, [1, 2, 3]);
        $this->rds->rPush($key2, [3, 2, 1]);

        $this->rds->rPopLPush($key1, $key2);

        $this->assertEquals([1, 2], $this->rds->lrange($key1, 0, -1));
        $this->assertEquals([3, 3, 2, 1], $this->rds->lrange($key2, 0, -1));

        $this->rds->del([$key1, $key2]);

        $this->rds->rPush($key1, [1, 2, 3]);
        $this->rds->rPopLPush($key1, $key1);
        $this->assertEquals([3, 1, 2], $this->rds->lrange($key1, 0, -1));
    }

    public function testLRem()
    {
        $key = $this->key('l-rem');
        $this->rds->rPush($key, ['1', [1], 1, ['1']]);
        $num = $this->rds->lRem($key, 1, [1]);
        $this->assertEquals(1, $num);
        $num = $this->rds->lRem($key, 1, ['1']);
        $this->assertEquals(1, $num);
        // test length
        $num = $this->rds->lLen($key);
        $this->assertEquals(2, $num);
        $this->rds->del($key);
    }

    public function testLIndex()
    {
        $key = $this->key('l-index');
        $this->rds->rPush($key, ['1', [1], 1, ['1']]);
        $val = $this->rds->lIndex($key, 0);
        $this->assertEquals('1', $val);
        $val = $this->rds->lIndex($key, 1);
        $this->assertEquals([1], $val);
        $val = $this->rds->lIndex($key, 2);
        $this->assertEquals(1, $val);
        $val = $this->rds->lIndex($key, 3);
        $this->assertEquals(['1'], $val);
    }

    public function testLInsert()
    {
        $key = $this->key('l-insert');
        $this->rds->del($key);
        $this->rds->rPush($key, ['1', [1], 1, ['1']]);
        $this->rds->lInsert($key, 'after', '1', '2');
        $val = $this->rds->lIndex($key, 1);
        $this->assertEquals('2', $val);

        $val = $this->rds->lInsert($key, 'after', [1], [2]);
        $this->assertTrue($val);
        $val = $this->rds->lIndex($key, 3);
        $this->assertEquals([2], $val);
        $val = $this->rds->lInsert($key, 'after', ['3'], [2]);
        $this->assertFalse($val);
        $this->rds->del($key);
    }

    public function testLSet()
    {
        $key = $this->key('l-set');
        $this->rds->del($key);
        $res = $this->rds->lSet($key, 0, 'error');
        $this->assertFalse($res);

        $this->rds->lPush($key, ['how are']);
        $this->rds->lSet($key, 0, 'hay');
        $res = $this->rds->lIndex($key, 0);
        $this->assertEquals('hay', $res);
        $this->rds->lSet($key, 0, ['hay']);
        $res = $this->rds->lIndex($key, 0);
        $this->assertEquals(['hay'], $res);
        $this->rds->del($key);
    }

    public function testLTrim()
    {
        $key = $this->key('l-trim');
        $this->rds->lPush($key, range(1, 30));

        $this->rds->lTrim($key, 2, 3);
        $this->assertEquals([28, 27], $this->rds->lRange($key));
        $this->rds->del($key);
    }
}
