<?php

declare(strict_types = 1);

namespace Weiran\Core\Module\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Exceptions\PermissionException;
use Weiran\Core\Rbac\Contracts\RbacUserContract;
use Weiran\Core\Rbac\Traits\RbacUserTrait;
use Weiran\Framework\Support\Abstracts\Repository;

/**
 * Class MenuRepository.
 */
class ModulesMenu extends Repository
{

    /**
     * Initialize.
     * @param Collection $collection 集合
     */
    public function initialize(Collection $collection)
    {
        // check serve setting
        $this->items = sys_tag('weiran-core')->remember(
            PyCoreDef::ckModule('menu'),
            PyCoreDef::MIN_ONE_DAY * 60,
            function () use ($collection) {
                $collection = $collection->map(function ($definition) {
                    // slug  - module
                    // layer - module
                    return collect($definition)->map(function ($definition) {
                        // new groups
                        $parsedGroups = [];
                        collect($definition['groups'])->each(function ($groups, $item_key) use (&$parsedGroups) {
                            // layer - children
                            $parsedGroup = $this->parseLink($groups);
                            if (!is_null($parsedGroup)) {
                                $parsedGroups[$item_key] = $parsedGroup;
                            }
                        })->toArray();
                        $definition['groups'] = $parsedGroups;
                        return $definition;
                    })->toArray();
                });

                $items = collect();
                $collection->each(function ($definition, $slug) use ($items) {
                    $this->parse($definition, $slug, $items);
                });

                /* 对 Injection 进行处理
                 * ---------------------------------------- */
                $reCollection = collect();
                $items->each(function ($definition, $slug) use ($reCollection) {
                    $groups = $definition['groups'] ?? [];
                    if ($groups) {
                        collect($groups)->each(function ($group, $index) use ($reCollection, &$definition) {
                            $injection = $group['injection'] ?? '';
                            if (!$injection) {
                                return;
                            }

                            [$key, $name] = explode('||', $injection);
                            // weiran.mgr-page/backend

                            if ($reCollection->offsetExists($key)) {
                                $item                                = $reCollection->get($key);
                                $item['groups'][$name]['children'][] = $group;
                                $reCollection->put($key, $item);
                                unset($definition['groups'][$index]);
                            }
                        });
                    }
                    $reCollection->put($slug, $definition);
                });


                $items   = $reCollection;
                $handled = collect();
                $collect = collect();
                $items->each(function ($definition, $key) use (&$handled, $collect) {
                    /* 避免重复循环请求的数据错误
                     * ---------------------------------------- */
                    if ($handled->contains($key)) {
                        $definition = $collect->get($key);
                    }
                    $collect->put($key, $definition);
                });

                // 进行排序
                $collect->sortBy('order', SORT_ASC);

                return $collect->all();
            }
        );
    }

    /**
     * 根据用户返回合适的菜单
     * @param string                              $type 指定用户的类型
     * @param bool                                $is_full_permission 是否是全部权限
     * @param null|RbacUserTrait|RbacUserContract $pam 用户
     * @return Collection
     * @throws PermissionException
     */
    public function withPermission(string $type, bool $is_full_permission = false, $pam = null): Collection
    {
        $menus = $this->where('type', $type);

        if (!$is_full_permission && is_null($pam)) {
            throw new PermissionException('非全部权限用户需要用户实体设定');
        }

        $menu = collect();
        $menus->each(function ($module) use ($pam, $menu, $is_full_permission) {
            $groups = collect();
            collect($module['groups'])->each(function ($group) use ($pam, $groups, $is_full_permission) {
                $children = collect();

                collect($group['children'])->each(function ($link) use ($children, $pam, $is_full_permission) {

                    /* 三级菜单的权限
                     * ---------------------------------------- */
                    if ($link['children'] ?? []) {
                        $submenus = collect([]);
                        collect($link['children'] ?? [])->each(function ($url) use ($submenus, $pam, $is_full_permission) {
                            if ($url['permission'] ?? '') {
                                // 管理员拥有所有权限
                                if ($is_full_permission) {
                                    $submenus->push($url);
                                }
                                elseif ($pam->capable($url['permission'])) {
                                    $submenus->push($url);
                                }
                            }
                            else {
                                $submenus->push($url);
                            }
                        });
                        if ($submenus->count()) {
                            $link['children'] = $submenus->toArray();
                            $children->push($link);
                        }
                    }
                    else if (($link['route'] ?? '') && ($link['permission'] ?? '')) {
                        // 管理员拥有所有权限
                        if ($is_full_permission) {
                            $children->push($link);
                        }
                        elseif ($pam->capable($link['permission'])) {
                            $children->push($link);
                        }
                    }
                    else {
                        $children->push($link);
                    }
                });
                $group['children'] = $children;
                $groups->push($group);
            });
            $module['groups'] = $groups;
            if (count($module['groups'])) {
                $menu->push($module);
            }
        });
        return $menu;
    }

    /**
     * @param string $type 类型
     * @param array  $perms perms
     * @return Collection
     */
    public function withType(string $type, array $perms = []): Collection
    {
        $menus = $this->where('type', $type);
        $menu  = collect();
        $menus->each(function ($module) use ($menu, $perms) {
            $groups = collect();
            collect($module['groups'])->each(function ($group) use ($groups, $perms) {
                $children = collect();
                collect($group['children'])->each(function ($url) use ($children, $perms) {
                    if ($url['permission'] ?? '') {
                        if (in_array($url['permission'], $perms, true)) {
                            $children->push($url);
                        }
                    }
                    else {
                        $children->push($url);
                    }
                });
                $group['children'] = $children;
                $groups->push($group);
            });
            $module['groups'] = $groups;
            if (count($module['groups'])) {
                $menu->push($module);
            }
        });

        return $menu;
    }

    /**
     * @param array      $items 数据数据
     * @param string     $prefix 前缀
     * @param Collection $collection 集合
     */
    private function parse(array $items, string $prefix, Collection $collection): void
    {
        collect($items)->each(function ($definition, $key) use ($collection, $prefix) {
            $key = $prefix . '/' . $key;

            /* get manifest files
             * ---------------------------------------- */
            $configuration         = app('weiran')->where('slug', $prefix);
            $definition['enabled'] = $configuration['enabled'] ?? false;
            $definition['order']   = $configuration['order'] ?? 0;
            $definition['text']    = $configuration['description'] ?? 0;
            $definition['parent']  = $prefix;
            if (isset($definition['children'])) {
                $this->parse($definition['children'], $key, $collection);
                unset($definition['children']);
            }
            $collection->put($key, $definition);
        });
    }

    /**
     * 解析链接
     * @param array $group 数据数组
     * @return array
     */
    private function parseLink(array $group): ?array
    {
        if (isset($group['children']) && is_array($group['children'])) {
            // parse children
            $newGroup = [];
            foreach ($group['children'] as $key => $define) {
                $calc = $this->parseLink($define);
                if (!is_null($calc)) {
                    $newGroup[$key] = $calc;
                }
            }
            $group['children'] = $newGroup;
        }

        // 兼容之前的取值
        $url   = $group['url'] ?? '';
        $route = $group['route'] ?? '';
        if (!$url) {
            $param = $group['route_param'] ?? '';
            $query = $group['param'] ?? '';
            if (preg_match('/([a-zA-Z0-9:_.-]*)(\/?[a-zA-Z0-9_,.]*)?(\?.*)?/', $route, $matches)) {
                $route = $matches[1];
                $param = (isset($matches[2]) && $matches[2]) ? trim($matches[2], '/') : $param;
                $query = (isset($matches[3]) && $matches[3]) ? trim($matches[3], '?') : $query;
            }

            $url = $route ? route_url($route, is_array($param) ? $param : explode(',', $param), $query, false) : '';
        }

        $group['url']   = $url;
        $group['route'] = $route;

        unset($group['route_param'], $group['param']);

        $routeHide = (array) config('weiran.core.route_hide');
        foreach ($routeHide as $hr) {
            if (Str::is($hr, $route) || Str::is($hr, $url)) {
                return null;
            }
        }
        return $group;
    }
}
