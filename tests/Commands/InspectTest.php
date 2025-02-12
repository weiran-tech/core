<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Commands;

use Weiran\Framework\Application\TestCase;

class InspectTest extends TestCase
{

    public function testDbSeo()
    {
        $result = py_console()->call('wr-core:inspect', [
            'type' => 'db_seo',
        ]);
        $this->assertEquals(0, $result);
    }
}