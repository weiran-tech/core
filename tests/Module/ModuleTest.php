<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Module;

use Illuminate\Support\Arr;
use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Core\Module\Module;
use Weiran\Core\Module\Repositories\Modules;
use Weiran\Core\Module\Repositories\ModulesMenu;
use Weiran\Core\Module\Repositories\ModulesService;
use Weiran\Framework\Application\TestCase;

class ModuleTest extends TestCase
{

    use CoreTrait;


    public function setUp(): void
    {
        parent::setUp();
        py_console()->call('cache:clear');
    }

    public function testHasAttributes(): void
    {
        $module = (new Module('weiran.core'));
        $this->assertEquals(weiran_path('weiran.core'), $module->directory());
        $this->assertEquals('weiran.core', $module->slug());
        $this->assertEquals('Weiran\\Core', $module->namespace());
    }

    public function testMenus(): void
    {
        $menus = $this->coreModule()->menus();
        $this->assertTrue($menus instanceof ModulesMenu);
    }

    public function testPath(): void
    {
        $menus = $this->coreModule()->path();
        $this->assertIsNumeric($menus->count());
    }

    public function testModules(): void
    {
        $repo = $this->coreModule()->modules();
        $this->assertTrue(Arr::exists($repo->toArray(), 'weiran.core'), '模块中没有发现 weiran.core 模块');
    }

    public function testServices(): void
    {
        $repo = $this->coreModule()->services();
        $this->assertTrue($repo instanceof ModulesService);
    }

    public function testEnable(): void
    {
        $repo = $this->coreModule()->enabled();
        $this->assertTrue($repo instanceof Modules);
    }

    public function testGet(): void
    {
        $module = $this->coreModule()->get('weiran.core');
        $this->assertTrue($module instanceof Module);

        $exists = $this->coreModule()->has('weiran.core');
        $this->assertTrue($exists);
    }
}