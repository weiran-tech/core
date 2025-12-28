<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use stdClass;

class RdsSetTest extends RdsBaseTest
{
    /**
     * 能添加数组和字串/ID, 并且ID 和字串相同时候被视为一个值
     */
    public function testSAdd(): void
    {
        $key = $this->key('s-add');
        $this->rds->del($key);
        if ($this->rds->sismember($key, 1)) {
            $remNum = $this->rds->srem($key, 1);
            $this->assertEquals(1, $remNum);
        }

        $result = $this->rds->sadd($key, 1);
        $this->assertEquals(1, $result);

        $result = $this->rds->sadd($key, '1');
        $this->assertEquals(0, $result);

        // add array
        $result = $this->rds->sadd($key, ['1', '2', '3', 4]);
        $this->assertEquals(3, $result);

        $num = $this->rds->sadd($key, [new stdClass()]);
        $this->assertEquals(1, $num);
        $this->rds->del($key);
    }

    public function testSIsMember(): void
    {
        $key = $this->key('s-is-member');
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);
        $res = $this->rds->sIsMember($key, [1]);
        $this->assertTrue($res);
        $res = $this->rds->sIsMember($key, '1');
        $this->assertTrue($res);
        $res = $this->rds->sIsMember($key, 1);
        $this->assertTrue($res);
        $res = $this->rds->sIsMember($key, new stdClass());
        $this->assertTrue($res);
        $this->rds->del($key);
    }

    public function testSPop(): void
    {
        $key = $this->key('s-pop');
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);
        $res = $this->rds->sPop($key);
        $this->assertNotEmpty($res);
        $res = $this->rds->sPop($key);
        $this->assertNotEmpty($res);
        $res = $this->rds->sPop($key);
        $this->assertNotEmpty($res);
        $res = $this->rds->sPop($key);
        $this->assertEmpty($res);
        $this->rds->del($key);
    }

    public function testSRem(): void
    {
        $key = $this->key('s-rem');
        $this->rds->del($key);
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);
        $res = $this->rds->sRem($key, 1);
        $this->assertEquals(1, $res);
        $res = $this->rds->sRem($key, [[1]]);
        $this->assertEquals(1, $res);
        $res = $this->rds->sRem($key, new stdClass());
        $this->assertEquals(1, $res);
        $res = $this->rds->sRem($key, '1');
        $this->assertEquals(0, $res);
        $this->rds->del($key);
    }

    public function testSMove(): void
    {
        $key     = $this->key('s-move');
        $keyDist = $this->key('s-move-dist');
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);
        $res = $this->rds->sMove($key, $keyDist, 1);
        $this->assertEquals(1, $res);
        $res = $this->rds->sMove($key, $keyDist, [1]);
        $this->assertEquals(1, $res);
        $res = $this->rds->sMove($key, $keyDist, new stdClass());
        $this->assertEquals(1, $res);
        $res = $this->rds->scard($keyDist);
        $this->assertEquals(3, $res);
        $res = $this->rds->scard($key);
        $this->assertEquals(0, $res);
        $this->rds->del([$key, $keyDist]);
    }

    public function testCard(): void
    {
        $key = $this->key('s-card');
        $this->rds->del($key);
        $res = $this->rds->sCard($key);
        $this->assertEquals(0, $res);
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);

        $res = $this->rds->sCard($key);
        $this->assertEquals(3, $res);
        $this->rds->del($key);
    }

    public function testSMembers(): void
    {
        $key = $this->key('s-members');
        $res = $this->rds->sMembers($key);
        $this->assertEquals([], $res);
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);

        $res = $this->rds->sMembers($key);
        $this->assertCount(3, $res);
        $this->rds->del($key);
    }

    public function testSScan(): void
    {
        $key = $this->key('s-scan');
        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);
        $request = true;
        $result  = [];
        $cursor  = 0;
        while ($request) {
            // 使用循环来获取数据, 不支持排序(无序列表)
            $val = $this->rds->sScan($key, $cursor, [
                'count' => 1,
            ]);
            array_push($result, ...$val[0]);
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

    public function testInter(): void
    {
        $key      = $this->key('s-inter');
        $key2     = $this->key('s-inter-2');
        $keyStore = $this->key('s-inter-store');
        $this->rds->del([
            $key, $key2, $keyStore,
        ]);

        $this->rds->sAdd($key, [
            1, '1', [1], new stdClass(),
        ]);
        $this->rds->sAdd($key2, [
            [1],
        ]);
        $inter = $this->rds->sInter([
            $key2, $key,
        ]);
        $this->assertContains([1], $inter);

        $this->rds->sInterStore($keyStore, [
            $key, $key2,
        ]);
        $this->assertCount(1, $this->rds->sMembers($keyStore));

        $this->rds->del([
            $key, $key2, $keyStore,
        ]);
    }

    public function testSUnion(): void
    {
        $key      = $this->key('s-union');
        $key2     = $this->key('s-union-2');
        $keyStore = $this->key('s-union-store');

        $this->rds->del([
            $key, $key2, $keyStore,
        ]);

        $this->rds->sAdd($key, [
            1, '1',
        ]);
        $this->rds->sAdd($key2, [
            [1],
        ]);

        $values = $this->rds->sUnion([
            $key, $key2,
        ]);
        $this->assertContains([1], $values);
        $this->assertContains(1, $values);

        $this->rds->sUnionStore($keyStore, [
            $key, $key2,
        ]);
        $this->assertCount(2, $this->rds->sMembers($keyStore));
        $this->rds->del([
            $key, $key2, $keyStore,
        ]);
    }

    /**
     * 检测数据的 Diff
     */
    public function testSDiff(): void
    {
        $key      = $this->key('s-diff');
        $key2     = $this->key('s-diff-2');
        $keyStore = $this->key('s-diff-store');

        $this->rds->del([
            $key, $key2, $keyStore,
        ]);

        $this->rds->sadd($key, 1);
        $this->rds->sadd($key2, ['1', '2', '3', 4]);
        $result = $this->rds->sDiff([$key2, $key]);
        $this->assertEquals(['2', '3', '4'], $result);

        $this->rds->sDiffStore($keyStore, [
            $key2, $key,
        ]);

        $this->assertCount(3, $this->rds->sMembers($keyStore));
        $this->rds->del([
            $key, $key2, $keyStore,
        ]);
    }

    public function testSRandMember(): void
    {
        $key = $this->key('s-diff');
        $this->rds->sadd($key, range(1, 20));
        $rand = $this->rds->srandmember($key, 3);
        $this->assertCount(3, $rand);
    }
}
