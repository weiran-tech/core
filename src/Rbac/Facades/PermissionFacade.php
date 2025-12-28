<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Rbac Facade
 */
class PermissionFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'weiran.core.permission';
    }
}
