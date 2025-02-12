<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Commands;

use Weiran\Framework\Application\TestCase;

class PermissionTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testList()
    {
        $result = py_console()->call('wr-core:permission', [
            'do' => 'list',
        ]);
        $this->assertEquals(0, $result);
    }

    public function testInit()
    {
        $result = py_console()->call('wr-core:permission', [
            'do' => 'init',
        ]);
        $this->assertEquals(0, $result);
    }
}