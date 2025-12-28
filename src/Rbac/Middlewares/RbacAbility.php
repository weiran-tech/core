<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Middlewares;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Rbac 能力
 */
class RbacAbility
{
    /**
     * @var Guard 权限
     */
    protected $auth;

    /**
     * Creates a new instance of the middleware.
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request     Request 请求
     * @param Closure $next        下一个
     * @param string  $roles       角色, 多个使用 | 分隔
     * @param string  $permissions 权限, 多个使用 | 分隔
     * @param bool    $validateAll 是否验证所有
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $roles, $permissions, $validateAll = false)
    {
        if ($this->auth->guest() || !$request->user()->ability(explode('|', $roles), explode('|', $permissions), ['validate_all' => $validateAll])) {
            abort(403);
        }

        return $next($request);
    }
}
