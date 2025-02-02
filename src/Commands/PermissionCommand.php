<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Core\Events\PermissionInitEvent;
use Weiran\Core\Rbac\Permission\Permission;
use Weiran\Core\Rbac\Permission\PermissionManager;

/**
 * Permission Command
 */
class PermissionCommand extends Command
{
    use CoreTrait;

    protected $signature = 'py-core:permission
		{do : The permission action to handle, allow <lists,init>}
		';

    protected $description = 'Permission manage list.';

    /**
     * @var PermissionManager
     */
    private PermissionManager $permission;


    public function __construct()
    {
        parent::__construct();
        $this->permission = $this->corePermission();
    }

    /**
     * Command Handler.
     * @return int
     * @throws Exception
     */
    public function handle()
    {
        $action = $this->argument('do');
        switch ($action) {
            case 'list':
                $this->lists();
                break;
            case 'init':
                $this->init();
                break;
            case 'menus':
                $this->checkMenus();
                break;
            default:
                $this->error(
                    sys_gen_mk(self::class, ' Command Not Exists!')
                );
                break;
        }

        return 0;
    }


    private function lists(): void
    {
        $data = new Collection();
        $this->permission->permissions()->each(function (Permission $permission) use ($data) {
            $data->push([
                $permission->type(),
                $permission->key(),
                $permission->description(),
            ]);
        });
        $this->table(
            ['Type', 'Identification', 'Description'],
            $data->toArray()
        );
    }

    private function init()
    {
        sys_tag('py-core')->del(PyCoreDef::ckModule('module'));

        sys_tag('py-core-rbac')->clear();

        $this->permission->clearCachedPermissionNames();

        // get all permission
        $permissions = $this->permission->permissions();
        if (!$permissions->count()) {
            $this->info(sys_gen_mk(self::class, 'No permission need import.'));
            return;
        }

        event(new PermissionInitEvent($permissions));

        $num = $this->permission->cachedPermissionNames()->count();

        $this->info(sys_gen_mk(self::class, "Init {$num} permission Success!"));
    }


    /**
     * 检查菜单
     */
    private function checkMenus()
    {
        // clear cache
        sys_tag('py-core')->clear();

        // calc
        $navigations = $this->coreModule()->menus();
        $format      = function ($item, $slug) {
            return [
                'title'      => $item['title'],
                'slug'       => $slug,
                'permission' => $item['permission'],
            ];
        };

        $faults = collect();
        $navigations->each(function ($item, $slug) use ($faults, $format) {

            collect($item['groups'])->each(function ($group) use ($faults, $format, $slug) {

                // 分组
                $children = collect((array) $group['children']);
                $children->map(function ($item) use ($faults, $format, $slug) {

                    $permission = $item['permission'] ?? '';
                    if ($permission && !$this->corePermission()->has($permission)) {
                        $faults->push($format($item, $slug));
                    }

                    $children = collect((array) ($item['children'] ?? []));
                    // 路由
                    $children->each(function ($item) use ($faults, $format, $slug) {
                        $permission = $item['permission'] ?? '';
                        if ($permission && !$this->corePermission()->has($permission)) {
                            $faults->push($format($item, $slug));
                        }
                    });
                });
            });

        });

        if (!$faults->count()) {
            $this->info(
                sys_gen_mk(self::class, 'All Permission are right.')
            );
        }
        else {
            $this->warn(
                sys_gen_mk(self::class, 'Error Permission in menus:')
            );
            $this->table(
                ['Title', 'Parent', 'Permission'],
                $faults->toArray()
            );
        }
    }
}
