<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Module;

use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Framework\Application\TestCase;

class ModuleMenuTest extends TestCase
{

    use CoreTrait;


    public function setUp(): void
    {
        parent::setUp();
        weiran_console()->call('cache:clear');
    }

    public function testParse(): void
    {
        $regex  = '/([a-z0-9:_.-]*)(\/?[a-z0-9_,.]*)?(\?.*)?/';
        $routes = [
            'demo:web.search.index',
            'demo:web.search.index/name',
            'demo:web.search.index/u_some,keb',
            'demo:web.search.index?name=abc_xxx',
            'demo:web.search.index/u_some?name=wolegeaa',
            'demo:web.search.index/u_some,other,some/my.?name=wolegeaa',
            'weiran-ad:backend.place.index/uname,ic?name=abc',
        ];
        foreach ($routes as $item) {
            if (preg_match($regex, $item)) {
                $this->assertTrue(true);
            }
        }
    }
}