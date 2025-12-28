<?php

declare(strict_types = 1);

namespace Weiran\Core\Module;

use Illuminate\Support\Collection;
use Weiran\Core\Module\Repositories\Modules;
use Weiran\Core\Module\Repositories\ModulesHook;
use Weiran\Core\Module\Repositories\ModulesMenu;
use Weiran\Core\Module\Repositories\ModulesPath;
use Weiran\Core\Module\Repositories\ModulesService;

/**
 * Class ModuleManager.
 */
class ModuleManager
{
    private ?Modules $repository = null;

    private ?ModulesMenu $menuRepository = null;

    private ?ModulesPath $pathRepository = null;

    private ?ModulesHook $hooksRepo = null;

    private ?ModulesService $serviceRepo = null;

    public function enabled(): Collection
    {
        return $this->modules()->enabled();
    }

    /**
     * 返回所有模块信息
     */
    public function modules(): Modules
    {
        if (!$this->repository instanceof Modules) {
            $this->repository = new Modules();
            $slugs            = app('weiran')->enabled()->pluck('slug');
            $this->repository->initialize($slugs);
        }

        return $this->repository;
    }

    /**
     * Get a module by name.
     *
     * @param mixed $name name
     */
    public function get($name): Module
    {
        return $this->modules()->get($name);
    }

    /**
     * Check for module exist.
     *
     * @param mixed $name name
     */
    public function has($name): bool
    {
        return $this->modules()->has($name);
    }

    public function path(): ModulesPath
    {
        if (!$this->pathRepository instanceof ModulesPath) {
            $collection = collect();
            $this->modules()->enabled()->each(function (Module $module) use ($collection) {
                $collection->put($module->slug(), $module->get('path', []));
            });
            $this->pathRepository = new ModulesPath();
            $this->pathRepository->initialize($collection);
        }

        return $this->pathRepository;
    }

    public function menus(): ModulesMenu
    {
        if (!$this->menuRepository instanceof ModulesMenu) {
            $collection = collect();
            $this->modules()->enabled()->each(function (Module $module) use ($collection) {
                $collection->put($module->slug(), $module->get('menus', []));
            });
            $this->menuRepository = new ModulesMenu();
            $this->menuRepository->initialize($collection);
        }

        return $this->menuRepository;
    }

    public function hooks(): ModulesHook
    {
        if (!$this->hooksRepo instanceof ModulesHook) {
            $collect = collect();
            $this->modules()->enabled()->each(function (Module $module) use ($collect) {
                $collect->put($module->slug(), $module->get('hooks', []));
            });
            $this->hooksRepo = new ModulesHook();
            $this->hooksRepo->initialize($collect);
        }

        return $this->hooksRepo;
    }

    public function services(): ModulesService
    {
        if (!$this->serviceRepo instanceof ModulesService) {
            $collect = collect();
            $this->modules()->enabled()->each(function (Module $module) use ($collect) {
                $collect->put($module->slug(), $module->get('services', []));
            });
            $this->serviceRepo = new ModulesService();
            $this->serviceRepo->initialize($collect);
        }

        return $this->serviceRepo;
    }
}
