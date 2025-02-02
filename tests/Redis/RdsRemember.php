<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

class RdsRemember extends RdsBaseTest
{

    public function testRemember()
    {

        $value = $this->rds->remember('remember', 20, function () {
            return 4;
        });
        // get from cache
        $secondValue = $this->rds->remember('remember', 20, function () {
            return 5;
        });
        $this->assertEquals(4, $value);
        $this->assertEquals(4, $secondValue);
        $this->rds->del('remember');


        $value = $this->rds->remember('remember-forever', 0, function () {
            return 4;
        });
        $this->assertEquals(4, $value);
    }
}