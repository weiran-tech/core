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
        $result = weiran_console()->call('weiran:core:permission', [
            'do' => 'list',
        ]);
        $this->assertEquals(0, $result);
    }

    public function testInit()
    {
        $result = weiran_console()->call('weiran:core:permission', [
            'do' => 'init',
        ]);
        $this->assertEquals(0, $result);
    }
}