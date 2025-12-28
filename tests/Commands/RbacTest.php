<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Commands;

use Weiran\Core\Rbac\Helper\RbacHelper;
use Weiran\Framework\Application\TestCase;

class RbacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testHelper()
    {
        $permissions = RbacHelper::permission('backend');
        $this->assertGreaterThan(0, $permissions->count());
    }
}
