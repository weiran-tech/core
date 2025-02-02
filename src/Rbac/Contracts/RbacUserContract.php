<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * 用户约束
 */
interface RbacUserContract
{
    /**
     * Many-to-Many relations with Role.
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany;

    /**
     * Checks if the user has a role by its name.
     * @param string|array $name        role name or array of role names
     * @param bool         $require_all all roles in the array are required
     * @return bool
     */
    public function hasRole($name, bool $require_all = false): bool;

    /**
     * Check if user has a permission by its name.
     * @param string|array $permission  permission string or array of permissions
     * @param bool         $require_all all permissions in the array are required
     * @return bool
     */
    public function capable($permission, bool $require_all = false): bool;

    /**
     * Checks role(s) and permission(s).
     * @param string|array $roles       Array of roles or comma separated string
     * @param string|array $permissions array of permissions or comma separated string
     * @param array        $options     validate_all (true|false) or return_type (boolean|array|both)
     * @return array|bool
     * @throws InvalidArgumentException
     */
    public function ability($roles, $permissions, array $options = []);

    /**
     * Alias to eloquent many-to-many relation's attach() method.
     * @param int|array<int>|object|array<object> $id 角色ID, 角色, 角色数组, ID 数组
     */
    public function attachRole($id);

    /**
     * Alias to eloquent many-to-many relation's detach() method.
     * @param int|array<int>|object|array<object> $id 角色ID, 角色, 角色数组, ID 数组
     */
    public function detachRole($id);
}
