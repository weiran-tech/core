<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Commands;

use Weiran\Framework\Application\TestCase;

class OpTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testMail(): void
    {
        $result = weiran_console()->call('weiran:core:op', [
            'do' => 'mail',
        ]);
        $this->assertEquals(0, $result);
    }
}