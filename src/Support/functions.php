<?php

declare(strict_types = 1);

use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Support\Arr;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Redis\RdsDb;
use Weiran\Core\Redis\RdsStore;
use Weiran\Core\Services\Factory\ServiceFactory;
use Weiran\Framework\Classes\Resp;

if (!function_exists('sys_cache')) {
    /**
     * 获取缓存
     * @param string|null $tag 标签, 支持字串, 支持类名
     * @return Cache|TaggedCache
     */
    function sys_cache(string $tag = null)
    {
        $cache = app('cache');
        if ($tag && ($cache->getStore() instanceof TaggableStore)) {
            if (strpos(trim($tag, '\\'), '\\') !== false) {
                $tag = strtolower(substr($tag, 0, strpos($tag, '\\')));
            }

            return $cache->tags($tag);
        }

        return $cache;
    }
}

if (!function_exists('sys_tag')) {
    /**
     * 获取 Tag 下缓存标记
     * @param string $tag 标签
     * @param string $db  数据库名称
     * @return RdsDb
     * @since 4.1
     */
    function sys_tag(string $tag, string $db = ''): RdsDb
    {
        return RdsDb::instance($db, 'tag:' . $tag);
    }
}


if (!function_exists('sys_cacher')) {
    /**
     * 缓存器, 随机秒数缓存器, 不在同一时刻读取值
     * @param string $key
     * @param mixed  $value
     * @param int    $second
     * @return mixed
     */
    function sys_cacher(string $key, $value, int $second = 30)
    {
        return RdsStore::seconds($key, $value, $second);
    }
}


if (!function_exists('sys_db')) {
    /**
     * 模型缓存
     * @param string       $table 数据表
     * @param array|string $keys  密钥
     * @return array|string
     */
    function sys_db(string $table, $keys = [])
    {
        static $cache;

        if (class_exists($table)) {
            $table = (new $table)->getTable();
        }

        if (!$cache) {
            $cache = sys_tag('wr-core')->hGetAll(PyCoreDef::ckLangModels());
            if (!$cache) {
                app(ConsoleKernelContract::class)->call('wr-core:db', [
                    'do' => 'fields',
                ]);
                $cache = sys_tag('wr-core')->hGetAll(PyCoreDef::ckLangModels());
            }
        }

        $tbFields = data_get($cache, $table, []);
        if (is_string($keys)) {
            return data_get($tbFields, $keys, '');
        }
        if (count($keys)) {
            return Arr::only($tbFields, $keys);
        }
        return $tbFields;
    }
}


if (!function_exists('sys_hook')) {
    /**
     * Hook 调用
     * @param string $id
     * @param array  $params
     * @return mixed
     */
    function sys_hook(string $id, array $params = [])
    {
        return (new ServiceFactory())->parse($id, $params);
    }
}


if (!function_exists('sys_gen_mk')) {
    /**
     * 根据异常类型生成符合条件格式的日志
     * @param string $tag
     * @param mixed  $info
     * @param bool   $request
     * @return string
     */
    function sys_gen_mk(string $tag, $info, bool $request = false): string
    {
        $jsonMark = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        $req = [];
        if ($request && Request::path() !== '/') {
            $req = [
                'url'     => '[' . Request::method() . ']' . Request::path(),
                'headers' => Request::header(),
            ];
            if (strtolower(Request::method()) === 'post') {
                $req['data'] = input();
            }
            else {
                $req['params'] = input();
            }
        }

        $append = function ($info) use ($request, $req, $jsonMark, $tag) {
            try {
                $je = json_encode($req, $jsonMark);
            } catch (JsonException $e) {
                $je = '';
            }
            return "[{$tag}]:" . $info . (($request && $req) ? PHP_EOL . $je : '');
        };

        // append datagtvgit flow hotfix finish 2.9.7
        if (is_array($info)) {
            try {
                return $append(json_encode($info, JSON_THROW_ON_ERROR | $jsonMark));
            } catch (JsonException $e) {
                return $append(array_keys($info));
            }
        }

        if (is_string($info)) {
            return $append($info);
        }

        if ($info instanceof Resp) {
            return $append(implode(', code:', [$info->getMessage(), $info->getCode()]));
        }

        if ($info instanceof Throwable) {
            $content = [
                'type'  => get_class($info),
                'info'  => ['message:' . $info->getMessage(), 'code' . $info->getCode()],
                'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4))->map(function ($arr) {
                    unset($arr['args']);
                    return $arr;
                }),
            ];
            if ($request) {
                $content['request'] = $req;
            }
        }
        else {
            $content = $info;
        }

        try {
            $content = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return "[{$tag}]:" . $content;
        } catch (JsonException $e) {
            return "[{$tag}]:" . $content;
        }
    }
}

if (!function_exists('sys_error')) {
    /**
     * 用于记录系统异常信息, 通常需要开启请求
     * @param string $tag          标签或者 class 名称
     * @param mixed  $info         需要输出的信息
     * @param bool   $with_request 是否打印请求数据
     */
    function sys_error(string $tag, $info, bool $with_request = false)
    {
        app('log')->error(sys_gen_mk($tag, $info, $with_request));
    }
}

if (!function_exists('sys_debug')) {
    /**
     * 4.1 更改为展示 debug 信息, 不区分环境, 用户追踪系统中的问题
     * @param string $tag          标签或者 class 名称
     * @param mixed  $info         需要输出的信息
     * @param bool   $with_request 是否打印请求数据
     */
    function sys_debug(string $tag, $info, bool $with_request = false)
    {
        app('log')->debug(sys_gen_mk($tag, $info, $with_request));
    }
}


if (!function_exists('sys_info')) {
    /**
     * 记录信息, 一般用户信息追溯
     * @param string $tag          标签或者 class 名称
     * @param mixed  $info         需要输出的信息
     * @param bool   $with_request 是否打印请求数据
     */
    function sys_info(string $tag, $info, bool $with_request = false)
    {
        app('log')->info(sys_gen_mk($tag, $info, $with_request));
    }
}

if (!function_exists('sys_warning')) {
    /**
     * 警告信息, 一般用户 deprecated 的提示
     * @param string $tag          标签或者 class 名称
     * @param mixed  $info         需要输出的信息
     * @param bool   $with_request 是否打印请求数据
     * @since 4.1
     */
    function sys_warning(string $tag, $info, bool $with_request = false)
    {
        app('log')->warning(sys_gen_mk($tag, $info, $with_request));
    }
}

if (!function_exists('sys_emergency')) {
    /**
     * 紧急的信息, 用于提示错误内容
     * @param string $tag          标签或者 class 名称
     * @param mixed  $info         需要输出的信息
     * @param bool   $with_request 是否打印请求数据
     * @since 4.1
     */
    function sys_emergency(string $tag, $info, bool $with_request = false)
    {
        app('log')->emergency(sys_gen_mk($tag, $info, $with_request));
    }
}