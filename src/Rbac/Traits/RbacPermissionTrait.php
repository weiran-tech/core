<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 权限 trait
 */
trait RbacPermissionTrait
{
    /**
     * {@inheritDoc}
     */
    public function roles(): BelongsToMany
    {
        $roleFk              = config('weiran.core.rbac.role_fk');
        $permissionFk        = config('weiran.core.rbac.permission_fk');
        $roleModel           = config('weiran.core.rbac.role');
        $rolePermissionModel = config('weiran.core.rbac.role_permission');

        return $this->belongsToMany(
            $roleModel,
            (new $rolePermissionModel())->getTable(),
            $permissionFk,
            $roleFk
        );
    }

    /**
     * Boot the permission model
     * Attach event listener to remove the many-to-many records when trying to delete
     * Will NOT delete any records if the permission model uses soft deletes.
     * 这个地方有点绕, 是我所属的所有角色中同步为空, 删除关于我的信息
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();
        static::deleting(function ($permission) {
            $traits = class_uses_recursive(static::class);
            if (isset($traits[SoftDeletes::class])) {
                return;
            }
            $permission->roles()->sync([]);
        });
    }
}
