<?php

declare(strict_types = 1);

namespace Weiran\Core\Classes\Traits;

use Weiran\Core\Module\ModuleManager;
use Weiran\Core\Rbac\Permission\PermissionManager;

trait CoreTrait
{
    /**
     * 获取核心的模块
     */
    public function coreModule(): ModuleManager
    {
        return app('weiran.core.module');
    }

    /**
     * 权限管理
     */
    public function corePermission(): PermissionManager
    {
        return app('weiran.core.permission');
    }
}
