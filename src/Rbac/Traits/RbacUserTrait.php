<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Redis\Cache;

/**
 * 用户 trait
 */
trait RbacUserTrait
{
    protected ?Collection $permissions = null;

    /**
     * 缓存权限
     */
    public function cachedRoles(): Collection
    {
        if ($this->permissions === null) {
            $cacheKey          = PyCoreDef::rbacCkUserRoles($this->{$this->primaryKey});
            $this->permissions = Cache::of('weiran-core-rbac')->remember($cacheKey, config('cache.ttl'), function () {
                return $this->roles()->get();
            });
        }

        return $this->permissions;
    }

    /**
     * @inheritDoc
     */
    public static function boot()
    {
        parent::boot();
        $traits = class_uses_recursive(static::class);

        // Attach event listener to remove the many-to-many records when trying to delete
        // Will NOT delete any records if the user model uses soft deletes.
        static::deleting(function ($user) use ($traits) {
            if (!isset($traits[SoftDeletes::class])) {
                $user->roles()->sync([]);
            }
            return true;
        });
        static::deleted(function () {
            self::clearCachedRoles();
        });
        static::saved(function () {
            self::clearCachedRoles();
        });

        if (isset($traits[SoftDeletes::class])) {
            static::restored(function () {
                self::clearCachedRoles();
            });
        }
    }


    /**
     * Many-to-Many relations with Role.
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('weiran.core.rbac.role');
        $accountFk = config('weiran.core.rbac.account_fk');
        $roleFk    = config('weiran.core.rbac.role_fk');
        return $this->belongsToMany(
            $roleModel,
            $this->getRoleUserTable(),
            $accountFk,
            $roleFk
        );
    }

    /**
     * @inheritDoc
     */
    public function hasRole($name, bool $require_all = false): bool
    {
        if (is_array($name)) {
            foreach ($name as $roleName) {
                $hasRole = $this->hasRole($roleName);

                if ($hasRole && !$require_all) {
                    return true;
                }

                if (!$hasRole && $require_all) {
                    return false;
                }
            }

            // If we've made it this far and $requireAll is FALSE, then NONE of the roles were found
            // If we've made it this far and $requireAll is TRUE, then ALL the roles were found.
            // Return the value of $requireAll;
            return $require_all;
        }

        foreach ($this->cachedRoles() as $role) {
            if ($role->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function capable($permission, bool $require_all = false): bool
    {
        if (is_array($permission)) {
            foreach ($permission as $permName) {
                $hasPerm = $this->capable($permName);
                if ($hasPerm && !$require_all) {
                    return true;
                }
                if (!$hasPerm && $require_all) {
                    return false;
                }
            }

            // If we've made it this far and $requireAll is FALSE, then NONE of the perms were found
            // If we've made it this far and $requireAll is TRUE, then ALL the perms were found.
            // Return the value of $requireAll;
            return $require_all;
        }

        foreach ($this->cachedRoles() as $role) {
            // Validate against the Permission table
            foreach ($role->cachedPermissions() as $perm) {
                if (Str::is($permission, $perm->name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function ability($roles, $permissions, array $options = [])
    {
        // Convert string to array if that's what is passed in.
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }
        if (!is_array($permissions)) {
            $permissions = explode(',', $permissions);
        }

        // Set up default values and validate options.
        if (!isset($options['validate_all'])) {
            $options['validate_all'] = false;
        }
        else {
            if ($options['validate_all'] !== true && $options['validate_all'] !== false) {
                throw new InvalidArgumentException();
            }
        }
        if (!isset($options['return_type'])) {
            $options['return_type'] = 'boolean';
        }
        else {
            if ($options['return_type'] != 'boolean' &&
                $options['return_type'] != 'array' &&
                $options['return_type'] != 'both'
            ) {
                throw new InvalidArgumentException();
            }
        }

        // Loop through roles and permissions and check each.
        $checkedRoles       = [];
        $checkedPermissions = [];
        foreach ($roles as $role) {
            $checkedRoles[$role] = $this->hasRole($role);
        }
        foreach ($permissions as $permission) {
            $checkedPermissions[$permission] = $this->capable($permission);
        }

        // If validate all and there is a false in either
        // Check that if validate all, then there should not be any false.
        // Check that if not validate all, there must be at least one true.
        if (($options['validate_all'] && !(in_array(false, $checkedRoles) || in_array(false, $checkedPermissions))) ||
            (!$options['validate_all'] && (in_array(true, $checkedRoles) || in_array(true, $checkedPermissions)))
        ) {
            $validateAll = true;
        }
        else {
            $validateAll = false;
        }

        // Return based on option
        if ($options['return_type'] == 'boolean') {
            return $validateAll;
        }
        elseif ($options['return_type'] == 'array') {
            return ['roles' => $checkedRoles, 'permissions' => $checkedPermissions];
        }

        return [$validateAll, ['roles' => $checkedRoles, 'permissions' => $checkedPermissions]];
    }

    /**
     * @inheritDoc
     */
    public function attachRole($id)
    {
        $this->roles()->attach($id);
        self::clearCachedRoles();
    }

    /**
     * @inheritDoc
     */
    public function detachRole($id)
    {
        $this->roles()->detach($id);
        self::clearCachedRoles();
    }


    protected static function clearCachedRoles()
    {
        Cache::of('weiran-core-rbac')->delete(PyCoreDef::rbacCkUserRoles('*'));
    }

    /**
     * @return string
     */
    private function getRoleUserTable(): string
    {
        $roleAccountModel = config('weiran.core.rbac.role_account');
        return (new $roleAccountModel)->getTable();
    }
}