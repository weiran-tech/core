<?php

declare(strict_types = 1);

namespace Weiran\Core\Classes\Traits;

use Weiran\Core\Module\ModuleManager;
use Weiran\Core\Rbac\Permission\PermissionManager;

trait CoreTrait
{
    /**
     * 获取核心的模块
     * @return ModuleManager
     */
    public function coreModule(): ModuleManager
    {
        return app('poppy.core.module');
    }

    /**
     * 权限管理
     * @return PermissionManager
     */
    public function corePermission(): PermissionManager
    {
        return app('poppy.core.permission');
    }
}