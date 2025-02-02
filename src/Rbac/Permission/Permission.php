<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Permission;

use Weiran\Core\Rbac\Repositories\PermissionRepository;

/**
 * Class PermissionManager.
 */
class Permission
{
    /**
     * @var PermissionRepository
     */
    protected $isDefault = false;

    /**
     * @var string permission id
     */
    protected $key = '';

    /**
     * @var string Module name
     */
    protected $root = '';

    /**
     * @var string Root Permission Title
     */
    protected $rootTitle = '';

    /**
     * @var string Permission group title
     */
    protected $groupTitle = '';

    /**
     * @var string Group name
     */
    protected $group = '';

    /**
     * @var string Module name
     */
    protected $module = '';

    /**
     * @var string permission type
     */
    protected $type = '';

    /**
     * @var string Permission description;
     */
    protected $description = '';

    public function __construct($permission, $key)
    {
        $this->isDefault   = $permission['default'] ?? false;
        $this->description = $permission['description'] ?? '';
        $this->root        = $permission['root'] ?? '';
        $this->type        = $permission['type'] ?? '';
        $this->group       = $permission['group'] ?? '';
        $this->module      = $permission['module'] ?? '';
        $this->rootTitle   = $permission['root_title'] ?? '';
        $this->groupTitle  = $permission['group_title'] ?? '';
        $this->key         = $key;
    }

    /**
     * @return string
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * @return bool|PermissionRepository
     */
    public function isDefault()
    {
        return $this->isDefault;
    }

    /**
     * @return string
     */
    public function root()
    {
        return $this->root;
    }

    /**
     * @return string
     */
    public function group()
    {
        return $this->group;
    }

    /**
     * @return string
     */
    public function module()
    {
        return $this->module;
    }

    /**
     * @return string
     */
    public function rootTitle()
    {
        return $this->rootTitle;
    }

    /**
     * @return string
     */
    public function groupTitle()
    {
        return $this->groupTitle;
    }

    /**
     * @return string
     */
    public function description()
    {
        return $this->description;
    }

    /**
     * 权限转换成数组
     * @return array
     */
    public function toArray()
    {
        return [
            'is_default'  => $this->isDefault,
            'description' => $this->description,
            'root'        => $this->root,
            'type'        => $this->type,
            'group'       => $this->group,
            'module'      => $this->module,
            'root_title'  => $this->rootTitle,
            'group_title' => $this->groupTitle,
            'key'         => $this->key,
        ];
    }
}
