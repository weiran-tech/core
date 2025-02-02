<?php
/**
 * Core 模块的使用配置
 */

use Weiran\System\Models\PamAccount;
use Weiran\System\Models\PamPermission;
use Weiran\System\Models\PamPermissionRole;
use Weiran\System\Models\PamRole;
use Weiran\System\Models\PamRoleAccount;

return [

    /* 维护邮箱地址
     * ---------------------------------------- */
    'op_mail' => env('CORE_OP_MAIL', ''),

    /* Rbac 模型和外键设定
     * ---------------------------------------- */
    'rbac'    => [
        'role'            => PamRole::class,
        'account'         => PamAccount::class,
        'role_account'    => PamRoleAccount::class,
        'permission'      => PamPermission::class,
        'role_permission' => PamPermissionRole::class,
        'role_fk'         => 'role_id',
        'account_fk'      => 'account_id',
        'permission_fk'   => 'permission_id',
    ],

];