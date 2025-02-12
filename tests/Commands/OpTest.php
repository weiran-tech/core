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
        $result = py_console()->call('wr-core:op', [
            'do' => 'mail',
        ]);
        $this->assertEquals(0, $result);
    }
}