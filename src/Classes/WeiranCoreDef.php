<?php

declare(strict_types = 1);

namespace Weiran\Core\Classes;


class WeiranCoreDef
{

    public const MIN_DEBUG     = 0;
    public const MIN_ONE_HOUR  = 60;
    public const MIN_SIX_HOUR  = 360;
    public const MIN_HALF_DAY  = 720;
    public const MIN_ONE_DAY   = 1440;
    public const MIN_HALF_WEEK = 5040;
    public const MIN_ONE_WEEK  = 10080;
    public const MIN_ONE_MONTH = 43200;


    /**
     * 持久化
     * @param string $key 持久化KEY
     * @return string
     */
    public static function ckPersistPersist(string $key): string
    {
        return 'persist:' . $key;
    }

    /**
     * 模型注释
     * @return string
     */
    public static function ckLangModels(): string
    {
        return 'lang-models';
    }

    /**
     * 模块注释
     * @param string $type
     * @return string
     */
    public static function ckModule(string $type): string
    {
        return 'module' . ($type ? '-' . $type : '');
    }

    /**
     * 权限
     * @return string
     */
    public static function ckPermissionKv(): string
    {
        return 'permission-kv';
    }

    /**
     * 权限
     * @return string
     */
    public static function ckPermissionNames(): string
    {
        return 'permission-names';
    }

    /**
     * 缓存器
     * @param string $key 标识KEY
     * @return string
     */
    public static function ckCacher(string $key): string
    {
        return 'cacher-' . $key;
    }

    /**
     * Rbac 角色缓存
     * @param int|string $id
     * @return string
     */
    public static function rbacCkRolePermissions($id): string
    {
        return 'permission-role-' . $id;
    }

    /**
     * 用户角色缓存
     * @param int|string $id
     * @return string
     */
    public static function rbacCkUserRoles($id): string
    {
        return 'roles-user-' . $id;
    }

    /**
     * 过期的KEY/Field
     * @return string
     */
    public static function ckRdsKeyFieldExpired(): string
    {
        return 'rds-key-field-expired';
    }

    /**
     * 锁定 KEY
     * @param $key
     * @return string
     */
    public static function ckPersistRdsLock($key): string
    {
        return 'rds-lock:' . $key;
    }
}