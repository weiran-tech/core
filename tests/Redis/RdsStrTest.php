<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Illuminate\Support\Str;

class RdsStrTest extends RdsBaseTest
{

    public function testSet()
    {
        $value  = $this->faker()->name;
        $key    = $this->key('str-set');
        $result = $this->rds->set($key, $value);
        $this->assertTrue($result);
        // 不存在则设置
        $result = $this->rds->set($key, $value, 'EX', 600, 'NX');
        $this->assertFalse($result);
        // 存在则设置
        $result = $this->rds->set($key, $value, 'EX', 600, 'XX');
        $this->assertTrue($result);
        // 存在则设置
        $result = $this->rds->set($key, $value, 'XX');
        $this->assertTrue($result);
        // 不存在则设置
        $result = $this->rds->set($key, $value, 'NX');
        $this->assertFalse($result);
        // 移除
        $this->rds->del($key);
        // 不存在则设置
        $result = $this->rds->set($key, $value, 'NX');
        $this->assertTrue($result);
        $this->rds->del($key);

        // 检测设置与获取
        $this->rds->set($key, $value);
        $res = $this->rds->get($key);
        $this->assertEquals($res, $value);
        $this->rds->set($key, [$value]);
        $res = $this->rds->get($key);
        $this->assertEquals($res, [$value]);
    }


    public function testSetEx()
    {
        $value  = $this->faker()->name;
        $key    = $this->key('str-setex');
        $result = $this->rds->setex($key, 20, $value);
        $this->assertTrue($result);
        $result = $this->rds->setex($key, 20, $value);
        $this->assertTrue($result);


        $this->rds->setex($key, 20, $value);
        $res = $this->rds->get($key);
        $this->assertEquals($res, $value);
        $this->rds->setex($key, 20, [$value]);
        $res = $this->rds->get($key);
        $this->assertEquals($res, [$value]);
    }

    public function testSetNx()
    {
        $value = $this->faker()->name;
        $key   = $this->key('str-setnx');
        $this->rds->del($key);
        // 未存在设置: 成功
        $res = $this->rds->setNx($key, $value);
        $this->assertTrue($res);
        // 获取, 对比
        $result = $this->rds->get($key);
        $this->assertEquals($value, $result);
        // 存在则设置 - 失败
        $res = $this->rds->setNx($key, $value);
        $this->assertFalse($res);
        $this->rds->del($key);

        // 设置数组是否相等
        $this->rds->setNx($key, [$value]);
        $result = $this->rds->get($key);
        $this->assertEquals([$value], $result);
    }


    public function testPSetEx()
    {
        $value = $this->faker()->name;
        $key   = $this->key('str-psetex');
        $this->rds->del($key);
        $result = $this->rds->pSetEx($key, 20000, $value);
        $this->assertTrue($result);
        $result = $this->rds->pSetEx($key, 20000, $value);
        $this->assertTrue($result);


        $this->rds->pSetEx($key, 20000, $value);
        $res = $this->rds->get($key);
        $this->assertEquals($res, $value);
        $this->rds->pSetEx($key, 20000, [$value]);
        $res = $this->rds->get($key);
        $this->assertEquals($res, [$value]);
    }


    public function testGet()
    {
        $value = $this->faker()->name;
        $key   = $this->key('str-get');
        $this->rds->del($key);
        $res = $this->rds->get($key);
        $this->assertNull($res);
        $this->rds->setex($key, 30, $value);
        $result = $this->rds->get($key);
        $this->assertEquals($value, $result);
    }

    public function testGetSet()
    {
        $value = $this->faker()->name;
        $key   = $this->key('str-getset');
        $this->rds->del($key);
        $res = $this->rds->getset($key, $value);
        $this->assertNull($res);
        $singleValue = $this->rds->getset($key, [$value]);
        $this->assertEquals($value, $singleValue);
        $arrValue = $this->rds->get($key);
        $this->assertEquals([$value], $arrValue);
        $this->rds->del($key);
    }

    public function testStrLen()
    {
        $value          = $this->faker()->randomFloat(4);
        $key            = $this->key('str-strlen');
        $noExistsLength = $this->rds->strlen($key . Str::random());
        $this->assertEquals(0, $noExistsLength);

        $length = Str::length($value);

        $this->rds->set($key, $value, 'EX', 60);
        $lenOfRds = $this->rds->strlen($key);
        $this->assertEquals($length, $lenOfRds);
    }

    public function testAppend()
    {
        $value = (string) $this->faker()->randomFloat(4);
        $key   = $this->key('str-append');
        $this->rds->del($key);
        $append = $this->rds->append($key, '');
        $this->assertEquals(0, $append);
        $length = Str::length($value);
        $append = $this->rds->append($key, $value);
        $this->assertEquals($append, $length);
    }

    public function testSetRange()
    {
        $key   = $this->key('str-set-range');
        $value = (string) $this->faker()->randomFloat(4);
        $this->rds->del($key);
        $length = $this->rds->setRange($key, 0, $value);
        $this->assertEquals(strlen($value), $length);
        $this->rds->del($key);
        $length = $this->rds->setRange($key, 5, $value);
        $this->assertEquals(5 + strlen($value), $length);
    }

    public function testIncr()
    {
        $key   = $this->key('str-incr');
        $value = $this->faker()->randomNumber(3);
        $this->rds->del($key);
        $res = $this->rds->incr($key);
        $this->assertEquals(1, $res);
        $res = $this->rds->incr($key);
        $this->assertEquals(2, $res);
        $res = $this->rds->incr($key, $value);
        $this->assertEquals(2 + $value, $res);

        $this->rds->del($key);
        $res = $this->rds->incr($key, 20);
        $this->assertEquals(20, $res);

        $this->rds->del($key);
        $res = $this->rds->incrByFloat($key, 0.0000001);
        $this->assertEquals('0.0000001', $res);
    }

    public function testDecr()
    {
        $key   = $this->key('str-decr');
        $value = $this->faker()->randomNumber(3);
        $this->rds->del($key);
        $res = $this->rds->decr($key);
        $this->assertEquals(-1, $res);
        $res = $this->rds->decr($key);
        $this->assertEquals(-2, $res);
        $res = $this->rds->decr($key, $value);
        $this->assertEquals(-2 - $value, $res);

        $this->rds->del($key);
        $res = $this->rds->decr($key, 20);
        $this->assertEquals(-20, $res);
    }

    public function testMSet()
    {
        $values = [$this->key('str-mset-a') => 1, $this->key('str-mset-b') => 2];
        $this->rds->del($this->key('str-mset-a'));
        $this->rds->mSet($values);
        $value = $this->rds->get($this->key('str-mset-a'));
        $this->assertEquals(1, $value);
    }

    public function testMSetNx()
    {
        $values = [$this->key('str-msetnex-a') => 1, $this->key('str-msetnex-b') => 2];
        $this->rds->del(array_keys($values));
        $value = $this->rds->mSetNx($values);
        $this->assertTrue($value);

        // will set false
        $values = [$this->key('str-msetnex-a') => 'xxx', $this->key('str-msetnex-b') => 2];
        $value  = $this->rds->mSetNx($values);
        $this->assertFalse($value);
        $this->rds->del(array_keys($values));
    }

    public function testMGet()
    {
        $values = [$this->key('str-mget-a') => 1, $this->key('str-mget-b') => 2];
        $this->rds->del(array_keys($values));
        $value = $this->rds->mSetNx($values);
        $this->assertTrue($value);

        // will set false
        $items = array_merge(array_keys($values), [$this->key('str-no-exists')]);
        $value = $this->rds->mget($items);
        $this->assertEquals([
            '1',
            '2',
            null,
        ], $value);
        $this->rds->del(array_keys($values));
    }
}