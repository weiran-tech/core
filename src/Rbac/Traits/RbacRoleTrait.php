<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Rbac\Permission\Permission;

/**
 * 角色 Trait
 */
trait RbacRoleTrait
{
    //Big block of caching functionality.

    /**
     * @return Collection|mixed
     */
    public function cachedPermissions()
    {
        $cacheKey = PyCoreDef::rbacCkRolePermissions($this->{$this->primaryKey});
        return sys_tag('weiran-core-rbac')->remember($cacheKey, config('cache.ttl'), function () {
            return $this->perms()->get();
        });
    }

    /**
     * @inheritDoc
     */
    public static function boot(): void
    {
        parent::boot();
        $traits = class_uses_recursive(static::class);

        // Attach event listener to remove the many-to-many records when trying to delete
        // Will NOT delete any records if the role model uses soft deletes.
        static::deleting(function ($role) use ($traits) {
            // 非软删除
            if (!isset($traits[SoftDeletes::class])) {
                $role->users()->sync([]);
                $role->perms()->sync([]);
            }
            self::clearCachedPermissions();
            return true;
        });

        static::saved(function () {
            self::clearCachedPermissions();
        });
        static::deleted(function () {
            self::clearCachedPermissions();
        });

        // soft delete restore
        if (isset($traits[SoftDeletes::class])) {
            static::restored(function () {
                self::clearCachedPermissions();
            });
        }
    }

    /**
     * 清理权限
     */
    public function flushPermissionRole()
    {
        self::clearCachedPermissions();
    }

    /**
     * @inheritDoc
     */
    public function users(): BelongsToMany
    {
        $accountClass     = config('weiran.core.rbac.account');
        $roleAccountClass = config('weiran.core.rbac.role_account');
        $roleFk           = config('weiran.core.rbac.role_fk');
        $accountFk        = config('weiran.core.rbac.account_fk');
        return $this->belongsToMany(
            $accountClass,
            (new $roleAccountClass)->getTable(),
            $roleFk,
            $accountFk
        );
    }

    /**
     * Many-to-Many relations with the permission model.
     * Named "perms" for backwards compatibility. Also, because "perms" is short and sweet.
     * @return BelongsToMany
     */
    public function perms(): BelongsToMany
    {
        $permissionClass = config('weiran.core.rbac.permission');
        $roleFk          = config('weiran.core.rbac.role_fk');
        $permissionFk    = config('weiran.core.rbac.permission_fk');
        return $this->belongsToMany(
            $permissionClass,
            $this->getPermissionRoleTable(),
            $roleFk,
            $permissionFk
        );
    }

    /**
     * @inheritDoc
     */
    public function savePermissions($permissions): void
    {
        $this->syncPermission($permissions);
    }

    /**
     * @inheritDoc
     */
    public function syncPermission($id): void
    {
        $this->perms()->sync($id);

        // clear current role id cache
        self::clearCachedPivotPermissions($this->{$this->primaryKey});
    }

    /**
     * 给角色添加权限, 并且清空角色缓存
     * @param object|array|Permission $id 权限
     * @return void
     */
    public function attachPermission($id): void
    {
        if (is_object($id)) {
            $id = $id->getKey();
        }

        if (is_array($id)) {
            $id = $id['id'];
        }

        $this->perms()->attach($id);

        // clear current role id cache
        self::clearCachedPivotPermissions($this->{$this->primaryKey});
    }

    /**
     * Detach permission from current role.
     * @param object|array $id 权限ID
     * @return void
     */
    public function detachPermission($id): void
    {
        if (is_object($id)) {
            $id = $id->getKey();
        }

        if (is_array($id)) {
            $id = $id['id'];
        }

        $this->perms()->detach($id);

        // clear current role id cache
        self::clearCachedPivotPermissions($this->{$this->primaryKey});
    }

    /**
     * @inheritDoc
     */
    public function attachPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->attachPermission($permission);
        }
    }

    /**
     * @inheritDoc
     */
    public function detachPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->detachPermission($permission);
        }
    }

    /**
     * Checks if the role has a permission by its name.
     * @param string|array $name permission name or array of permission names
     * @param bool         $require_all all permissions in the array are required
     * @return bool
     */
    public function hasPermission($name, bool $require_all = false): bool
    {
        if (is_array($name)) {
            foreach ($name as $permissionName) {
                $hasPermission = $this->hasPermission($permissionName);

                if ($hasPermission && !$require_all) {
                    return true;
                }

                if (!$hasPermission && $require_all) {
                    return false;
                }
            }

            // If we've made it this far and $requireAll is FALSE, then NONE of the permissions were found
            // If we've made it this far and $requireAll is TRUE, then ALL the permissions were found.
            // Return the value of $requireAll;
            return $require_all;
        }

        foreach ($this->cachedPermissions() as $permission) {
            if ($permission->name === $name) {
                return true;
            }
        }

        return false;
    }

    protected static function clearCachedPermissions(): void
    {
        sys_tag('weiran-core-rbac')->clear(PyCoreDef::rbacCkRolePermissions('*'));
    }

    protected static function clearCachedPivotPermissions($role_id): void
    {
        sys_tag('weiran-core-rbac')->clear(PyCoreDef::rbacCkRolePermissions($role_id));
    }

    /**
     * @return string
     */
    private function getPermissionRoleTable(): string
    {
        $permissionRole = config('weiran.core.rbac.role_permission');
        return (new $permissionRole)->getTable();
    }
}