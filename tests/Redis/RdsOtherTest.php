<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

class RdsOtherTest extends RdsBaseTest
{

    public function testBit()
    {
        $key = $this->key('bit');
        $this->rds->del($key);

        $result = $this->rds->setBit($key, 1111, 1);
        $this->assertEquals(0, $result);

        $bit = $this->rds->getBit($key, 1000);
        $this->assertEquals(0, $bit);

        $this->rds->setBit($key, 111, 1);
        $this->rds->setBit($key, 11, 1);
        $this->rds->setBit($key, 1, 1);
        $count = $this->rds->bitCount($key);
        $this->assertEquals(4, $count);

        $pos = $this->rds->bitPos($key, '1', -1);
        $this->assertEquals(1111, $pos);

        $pos = $this->rds->bitPos($key, '1');
        $this->assertEquals(1, $pos);
        $this->rds->del($key);
    }

    public function testHyperLogLog()
    {
        $key = $this->key('hyper');
        $this->rds->del($key);
        $num = $this->rds->pfCount($key);
        $this->assertEquals(0, $num);

        $num = 10000;

        $this->rds->pfAdd($key, range(1, $num));

        $calc = $this->rds->pfCount($key);
        $this->assertGreaterThan($num * .9919, $calc);
        $this->assertLessThan($num * 1.0081, $calc);
        $this->rds->del($key);
    }
}