<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Permission;

use Auth;
use Illuminate\Support\Collection;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Core\Module\Module;
use Weiran\Core\Rbac\Repositories\PermissionRepository;
use Weiran\Core\Rbac\Traits\RbacUserTrait;

/**
 * 权限管理器
 */
class PermissionManager
{
    use CoreTrait;

    /**
     * @var PermissionRepository|null
     */
    protected ?PermissionRepository $repository = null;

    /**
     * check permission
     * @param string $permission 需要检测权限
     * @param string $guard      保护的 guard
     * @return bool
     */
    public function check(string $permission, string $guard): bool
    {
        /** @var RbacUserTrait $user */
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return false;
        }

        return $user->capable($permission);
    }

    /**
     * @return PermissionRepository
     */
    public function repository(): PermissionRepository
    {
        if (!$this->repository instanceof PermissionRepository) {
            $this->repository = new PermissionRepository();
            $collection       = collect();
            $this->coreModule()->enabled()->each(function (Module $module) use ($collection) {
                if ($module->offsetExists('permissions')) {
                    $collection->put($module->slug(), $module->get('permissions'));
                }
            });
            $this->repository->initialize($collection);
        }

        return $this->repository;
    }

    /**
     * Get all permissions.
     * @return Collection|Permission[]
     */
    public function permissions(): Collection
    {
        $perms = collect();
        $this->repository()->each(function ($permissions, $module) use ($perms) {
            collect($permissions)->each(function ($root) use ($perms, $module) {
                $rootSlug  = $root['slug'] ?? '';
                $rootTitle = $root['title'] ?? '';
                if (!$rootSlug) {
                    return;
                }
                $typeSlug = '';
                if (strpos($rootSlug, ':') !== false) {
                    [$typeSlug, $rootSlug] = explode(':', $rootSlug);
                }
                $groups = collect($root['groups'] ?? []);
                $groups->each(function ($group) use ($perms, $module, $typeSlug, $rootSlug, $rootTitle) {
                    $groupSlug  = $group['slug'] ?? '';
                    $groupTitle = $group['title'] ?? '';
                    if (!$groupSlug) {
                        return;
                    }
                    $permissions = collect($group['permissions'] ?? []);
                    $permissions->each(
                        function ($permission) use ($perms, $module, $typeSlug, $rootSlug, $groupSlug, $groupTitle, $rootTitle) {
                            $permissionSlug = $permission['slug'] ?? '';
                            if (!$permissionSlug) {
                                return;
                            }
                            $permission['root_title']  = $rootTitle;
                            $permission['group_title'] = $groupTitle;
                            $permission['module']      = $module;
                            $permission['root']        = $rootSlug;
                            $permission['type']        = $typeSlug;
                            $permission['group']       = $groupSlug;
                            $id                        = "{$typeSlug}:{$rootSlug}.{$groupSlug}.{$permissionSlug}";
                            $perms->put($id, new Permission($permission, $id));
                        }
                    );
                });
            });
        });

        return $perms;
    }

    /**
     * @param string $permission 权限
     * @return bool
     */
    public function has(string $permission): bool
    {
        return $this->cachedPermissionNames()->contains($permission);
    }


    /**
     * 缓存的权限 KV
     * @param string|null $key
     * @return mixed|string
     */
    public function cachedPermissionKv(string $key = null)
    {
        static $permissions;
        if (!$permissions) {
            $permissions = sys_tag('weiran-core')->remember(PyCoreDef::ckPermissionKv(), config('cache.ttl', 600), function () {
                $data = collect();
                $this->corePermission()->permissions()->each(function (Permission $permission) use ($data) {
                    $data->put($permission->key(), $permission->description());
                });
                return $data->toArray();
            });
        }
        if ($key) {
            return $permissions[$key] ?? '';
        }
        return $permissions;
    }

    /**
     * 缓存的权限
     * @return Collection
     */
    public function cachedPermissionNames(): Collection
    {
        return sys_tag('weiran-core')->remember(PyCoreDef::ckPermissionNames(), config('cache.ttl', 600), function () {
            return $this->permissions()->keys();
        });
    }

    /**
     * 清除权限缓存
     * @return void
     */
    public function clearCachedPermissionNames(): void
    {
        sys_tag('weiran-core')->del(PyCoreDef::ckPermissionNames());
    }
}
