<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 角色约束
 */
interface RbacRoleContract
{
    /**
     * Many-to-Many relations with the user model.
     */
    public function users(): BelongsToMany;

    /**
     * Many-to-Many relations with the permission model.
     * Named "perms" for backwards compatibility. Also, because "perms" is short and sweet.
     */
    public function perms(): BelongsToMany;

    /**
     * Save the inputted permissions.
     *
     * @param mixed $permissions 需要保存的权限
     *
     * @deprecated
     */
    public function savePermissions($permissions): void;

    /**
     * Save the inputted permissions.
     *
     * @param mixed $id 需要保存的权限, 如果是空数组, 默认 detach = true
     */
    public function syncPermission($id): void;

    /**
     * Attach permission to current role.
     *
     * @param object|array $id 权限
     */
    public function attachPermission($id): void;

    /**
     * Detach permission form current role.
     *
     * @param object|array $id 权限
     */
    public function detachPermission($id): void;

    /**
     * Attach multiple permissions to current role.
     *
     * @param array $permissions 多个权限
     *
     * @deprecated 4.1
     */
    public function attachPermissions(array $permissions): void;

    /**
     * Detach multiple permissions from current role
     *
     * @param array $permissions 多个权限
     *
     * @deprecated 4.1
     */
    public function detachPermissions(array $permissions): void;
}
