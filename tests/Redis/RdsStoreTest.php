<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Weiran\Core\Redis\RdsStore;
use Weiran\Framework\Application\TestCase;

class RdsStoreTest extends TestCase
{
    /**
     * @
     */
    public function testInLock(): void
    {
        for ($start = 1; $start <= 20; $start++) {
            RdsStore::inLock('testing_atomic_lock', 1);
            // 100 ms
            usleep(100000);
            if ($start % 10 === 0) {
                $this->assertEquals(false, RdsStore::inLock('testing_atomic_lock', 1), 'Lock');
            }
            else {
                $this->assertEquals(true, RdsStore::inLock('testing_atomic_lock', 1), 'No Lock');
            }
        }
    }
}
