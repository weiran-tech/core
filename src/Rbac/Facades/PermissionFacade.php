<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Rbac Facade
 */
class PermissionFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'poppy.core.permission';
    }
}

