<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac\Middlewares;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Role In Rbac
 */
class RbacRole
{
    /**
     * @var Guard 用户 auth
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
     * @param Request $request 请求
     * @param Closure $next    后续处理
     * @param string  $roles   角色, 多个使用 | 分隔
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $roles)
    {
        if ($this->auth->guest() || !$request->user()->hasRole(explode('|', $roles))) {
            abort(403);
        }

        return $next($request);
    }
}
