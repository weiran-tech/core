<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Route;
use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Core\Exceptions\PermissionException;
use Weiran\Core\Rbac\Traits\RbacUserTrait;
use Weiran\Framework\Classes\Resp;

/*
|--------------------------------------------------------------------------
| 用户权限中间件
|--------------------------------------------------------------------------
| 如果用户需要有额外需要通过的用户, 放到 passed 中来忽略此权限验证
*/

/**
 * 用户权限
 */
class RbacPermission
{
    use CoreTrait;

    /**
     * Handle an incoming request.
     *
     * @param Request $request 请求
     * @param Closure $next    后续处理
     *
     * @return mixed
     *
     * @throws PermissionException
     */
    public function handle($request, Closure $next)
    {
        /** @var RbacUserTrait $user */
        $user = $request->user();

        if (!method_exists($user, 'capable')) {
            throw new PermissionException('用户没有检测权限的方法, 无法使用此中间件');
        }

        $controller = Route::current()->controller;

        /* 未定义权限, 通过
         * ---------------------------------------- */
        if (!($controller::$permission ?? '')) {
            return $next($request);
        }

        /* 超级管理员通过
         * ---------------------------------------- */
        if (method_exists($this, 'passed') && $this->passed($user)) {
            return $next($request);
        }

        $permissions = $controller::$permission;

        /* 存在方法权限, 不验证 global
         * ---------------------------------------- */
        $method           = Str::after(Route::currentRouteAction(), '@');
        $methodPermission = $permissions[$method] ?? '';
        if ($methodPermission && $this->corePermission()->has($methodPermission)) {
            if ($user->capable($methodPermission)) {
                return $next($request);
            }
            $title = $this->corePermission()->cachedPermissionKv($methodPermission);

            return Resp::error("用户无独立 [{$title}] 权限, 无法访问");
        }

        /* 全局权限
         * ---------------------------------------- */
        $globalPermission = $permissions['global'] ?? '';
        if ($globalPermission && $this->corePermission()->has($globalPermission)) {
            if ($user->capable($globalPermission)) {
                return $next($request);
            }
            $title = $this->corePermission()->cachedPermissionKv($globalPermission);

            return Resp::error("用户无全局 [{$title}] 权限, 无法访问");
        }

        return $next($request);
    }
}
