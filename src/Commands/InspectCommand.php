<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;

use Artisan;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use Weiran\Core\Classes\Inspect\CommentParser;
use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Framework\Classes\Traits\KeyParserTrait;
use Weiran\Framework\Validation\Rule;

/**
 * 检查代码规则
 */
class InspectCommand extends Command
{
    use CoreTrait, KeyParserTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:inspect 
		{type? : Support type need to input, [method, file, class, env, action, controller]}
		{--module= : The module to check}
		{--export= : The module to check}
		{--class_load_only : Only load class with not show tables}
		{--log : Is Display Request Log}
	';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect code style';

    /**
     * @var array Name Rules
     */
    private array $nameRules = [];

    /**
     * Execute the console command.
     *
     * @throws ReflectionException
     */
    public function handle()
    {
        $type = $this->argument('type');
        switch ($type) {
            case 'class':
                app('weiran')->enabled()->each(function ($module, $slug) {
                    $this->inspectClass($slug);
                });
                break;
            case 'file':
                app('weiran')->enabled()->each(function ($module, $slug) {
                    $this->inspectFileName($slug);
                });
                break;
            case 'controller':
                app('weiran')->enabled()->each(function ($module, $slug) {
                    $this->inspectController($slug);
                });
                break;
            case 'action':
                app('weiran')->enabled()->each(function ($module, $slug) {
                    $this->inspectAction($slug);
                });
                break;
            case 'util':
                app('weiran')->enabled()->each(function ($module, $slug) {
                    $this->inspectUtil($slug);
                });
                break;
            case 'perms':
                $this->inspectPerms();
                break;
            case 'validation':
                $this->inspectValidation();
                break;
            case 'seo':
                $this->inspectSeo();
                break;
            case 'trans':
                $this->inspectTrans();
                break;
            default:
                $this->call('core:inspect', [
                    'type' => 'file',
                ]);

                $this->call('core:inspect', [
                    'type' => 'class',
                ]);

                $this->call('core:inspect', [
                    'type' => 'util',
                ]);

                $this->call('core:inspect', [
                    'type' => 'validation',
                ]);

                $this->call('core:inspect', [
                    'type' => 'seo',
                ]);

                $this->call('core:inspect', [
                    'type' => 'perms',
                ]);
                break;
        }

        return 0;
    }

    private function inspectValidation(): void
    {
        $ref     = new ReflectionClass(Rule::class);
        $methods = $ref->getMethods();
        $keys    = [];
        foreach ($methods as $method) {
            $name = $method->getName();

            if (in_array($name, ['mixin', 'hasMacro', '__callStatic', '__call', 'macro'])) {
                continue;
            }

            if (in_array($name, ['min', 'max', 'size', 'between'])) {
                foreach (['numeric', 'file', 'string'] as $rule) {
                    $keys[] = $name . '.' . $rule;
                }
            }
            else {
                $keys[] = Str::before(Str::snake($name), ':');
            }
        }
        $values = [];
        foreach ($keys as $key) {
            $value = trans('validation.' . $key);
            if (is_array($value)) {
                continue;
            }
            if (!preg_match('/[\x{4e00}-\x{9fa5}]/u', $value)) {
                $values[] = [
                    'rule'    => $key,
                    'content' => $value,
                ];
            }
        }
        if (count($values)) {
            $this->table(['规则', '验证内容'], $values);
        }
        else {
            $this->info('Inspect: Validation OK');
        }

    }

    private function inspectTrans(): void
    {
        $export = $this->option('export');
        try {
            $content = app('files')->get($export);
        }
        catch (Throwable $e) {
            $this->error(sys_gen_mk(self::class, $e->getMessage()));

            return;
        }
        if (preg_match_all("/trans\((.*)?['\"]/", $content, $matches, PREG_PATTERN_ORDER)) {
            $uniTrans = array_unique($matches[1]);
            if (!count($uniTrans)) {
                $this->error(sys_gen_mk(self::class, '没有可以匹配的条目'));

                return;
            }
            $trans = [];
            foreach ($uniTrans as $tran) {
                $tran = trim($tran, '\'"');
                if (trans($tran) === $tran) {
                    $trans[] = [$tran];
                }
            }
            $this->table(['Trans'], $trans);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function inspectUtil($slug): void
    {
        $directory = weiran_path($slug, 'src/Models/Policies');
        $keys      = [];
        if (app('files')->exists($directory)) {
            $files = app('files')->files($directory);
            foreach ($files as $file) {
                $class = $this->className($slug, 'Models/Policies', $file->getFilename());
                $refs  = new ReflectionClass($class);
                $model = Str::before($file->getFilename(), 'Policy');
                foreach ($refs->getMethods() as $method) {
                    if ($method->isPublic() && !$method->isStatic()) {
                        $name = $method->getName();
                        if (in_array($name, ['after', 'before'])) {
                            continue;
                        }
                        if (Str::startsWith($slug, 'weiran')) {
                            $prefix = 'py-' . Str::after($slug, 'weiran.');
                        }
                        else {
                            $prefix = Str::after($slug, 'module.');
                        }
                        $keys[] = $prefix . '::util.policy.' . Str::snake($model) . '.' . $name;
                    }
                }

            }
        }
        else {
            $this->info("{$slug} has no policies.");
        }

        $directory = weiran_path($slug, 'src/Models');
        if (app('files')->exists($directory)) {
            $files = app('files')->files($directory);
            foreach ($files as $file) {
                $model  = Str::before($file->getFilename(), '.php');
                $prefix = Str::startsWith($slug, 'weiran') ? 'weiran-' . Str::after($slug, 'weiran.') : Str::after($slug, 'module.');
                $keys[] = $prefix . '::util.classes.models.' . Str::snake($model);
            }
        }
        else {
            $this->info("{$slug} has no policies.");
        }

        $needModifies = [];
        if (!count($keys)) {
            $this->info(sys_gen_mk('weiran-core.inspect', 'util of ' . $slug . ' no keys to trans'));
        }
        else {
            foreach ($keys as $key) {
                if (trans($key) === $key) {
                    $needModifies[] = [
                        $key,
                    ];
                }
            }
            $this->table(['Keys'], $needModifies);
        }

    }

    /**
     * 生成 seo 项目
     */
    private function inspectSeo(): void
    {
        $seoList         = [];
        $unUniformedKeys = [];
        collect(\Route::getRoutes())->map(function (Route $route) use (&$seoList, &$unUniformedKeys) {
            $name = $route->getName();
            if (!$name) {
                return;
            }
            if (!preg_match('/(.+?):(.+?)\.(.+?)\.(.+?)/', $name)) {
                $unUniformedKeys[] = [
                    $name,
                ];

                return;
            }

            $module   = Str::before($name, ':');
            $seoKey   = str_replace([':', '.', '::'], ['::', '_', '::seo.'], $name);
            $transKey = trans($seoKey);
            if ($transKey === $seoKey || $transKey === '') {
                $key = str_replace([$module . ':', '.'], ['', '_'], $name);
                // 取消 API 的重命名
                if (Str::startsWith($key, 'api')) {
                    return;
                }
                $seoList[] = [
                    $module,
                    '\'' . str_replace([$module . ':', '.'], ['', '_'], $name) . '\' => \'\', ',
                ];
            }
        });

        if (count($unUniformedKeys)) {
            $this->warn('[Inspect: Uniformed Route Url]');
            $this->table(['Route Name'], $unUniformedKeys);
        }

        $this->warn('[Inspect: Seo Names]');
        if ($seoList) {
            $this->table(['Module', 'Key'], $seoList);
        }
        else {
            $this->info('Perfect, Seo rule are matched');
        }
    }

    /**
     * 检测类注释和加载
     */
    private function inspectClass($slug): void
    {
        $optClassLoadOnly = $this->option('class_load_only');

        $table      = [];
        $classTable = [];

        $files = app('files')->allFiles(weiran_path($slug, 'src'));
        foreach ($files as $file) {
            $pathName = $file->getPathname();

            // 排除指定的类
            if (Str::contains($pathName, ['functions.php', 'ServiceProvider', 'Http/Routes/']) || !Str::endsWith($pathName, '.php')) {
                continue;
            }

            $relativePath = $file->getRelativePath();
            $className    = $this->className($slug, $relativePath, $file->getFilename());

            try {
                $refection = new ReflectionClass($className);
            }
            catch (Throwable $e) {
                $classTable[] = [
                    $slug,
                    $e->getMessage(),
                ];

                continue;
            }

            $properties = $refection->getProperties();
            $varDesc    = [];
            foreach ($properties as $property) {
                if ($property->class !== $className) {
                    continue;
                }
                // action variable do not need
                if (strpos($className, '\\Models\\') !== false && in_array($property->getName(), [
                        'timestamps', 'table', 'fillable', 'primaryKey', 'dates',
                    ], true)) {
                    continue;
                }
                if (strpos($className, '\\Commands\\') !== false && in_array($property->getName(), [
                        'signature', 'description',
                    ], true)) {
                    continue;
                }

                if (!$property->getDocComment()) {
                    $varDesc[] = [
                        $slug,
                        '',
                        '$' . $property->name,
                        '[comment missing]',
                    ];
                }

                // 检测 CamelCase
                if (!$this->isCamelCase($property->getName())) {
                    $varDesc[] = [
                        $slug,
                        '',
                        '',
                        'param : => ' . '$' . Str::camel($property->getName()),
                    ];
                }

            }
            $methods = $refection->getMethods();
            if ($methods === null) {
                continue;
            }

            $methodDesc = [];

            foreach ($methods as $method) {
                $methodName = $method->getName();
                if (
                    in_array($methodName, [
                        'handle', '',
                    ], true)
                    &&
                    (
                        strpos($className, '\\Listeners\\') !== false
                        ||
                        strpos($className, '\\Middlewares\\') !== false
                    )) {
                    continue;
                }

                // 排除继承的方法
                if ($method->class !== $className) {
                    continue;
                }

                // 排除魔术方法
                if (Str::startsWith($methodName, '__')) {
                    continue;
                }

                // 不是本文件中的. 略过
                if (basename($method->getFileName()) !== basename($file->getRealPath())) {
                    continue;
                }

                // 检查 注释
                $comment = $method->getDocComment();
                $item    = [
                    $slug,
                    '',
                    $methodName,
                ];
                if (!$comment) {
                    $item[]       = '[comment: missing]';
                    $methodDesc[] = $item;
                }
                else {
                    $parser      = new CommentParser();
                    $parsed      = $parser->parseMethod($comment);
                    $commentDesc = '';
                    if (count($parsed['params'])) {
                        foreach ($parsed['params'] as $param) {
                            $name = $param['var_name'] ?? '';
                            if (!$name) {
                                continue;
                            }

                            $desc = $param['var_desc'] ?? '';
                            $type = $param['var_type'] ?? '';
                            if (!$desc || !$type) {
                                $commentDesc    .= "{$name} ";
                                $varCommentDesc = '';
                                if (!$type) {
                                    $varCommentDesc .= 'type:' . ',';
                                }
                                if (!$desc) {
                                    $varCommentDesc .= 'desc:' . ',';
                                }
                                $commentDesc .= $varCommentDesc ? '[' . rtrim($varCommentDesc, ',') . ']' : '';
                                $commentDesc .= "\n";
                            }
                        }
                    }

                    if ($commentDesc) {
                        $item[]       = rtrim($commentDesc);
                        $methodDesc[] = $item;
                    }

                    // 代码是否已经审核
                    if (isset($parsed['verify'])) {
                        $item[]       = '';
                        $item[]       = 'method: ' . $methodName . ': verify √';
                        $methodDesc[] = $item;
                    }
                }

                // 检测 CamelCase
                if (!$this->isCamelCase($methodName)) {
                    $methodDesc[] = [
                        $slug,
                        '',
                        '',
                        'method : => ' . Str::camel($methodName),
                    ];
                }
            }

            $trimComment       = str_replace(['/', '*', "\t", "\n", ' '], '', (string) $refection->getDocComment());
            $docCommentMissing = true;
            if ($trimComment) {
                $docCommentMissing = false;
            }
            if ($docCommentMissing || $varDesc || $methodDesc) {
                $table[] = [
                    $slug,
                    $className,
                    '',
                    $docCommentMissing ? '[class doc missing]' : '',
                ];
                foreach (array_merge($varDesc, $methodDesc) as $item) {
                    $table[] = $item;
                }
            }
        }

        if ($optClassLoadOnly) {
            return;
        }

        $this->warn('[Inspect:Comment]');
        if ($table) {
            if ($this->option('module')) {
                $num   = 1;
                $table = collect($table)->filter(function ($item) {
                    return stripos($item[0], $this->option('module')) === 0;
                })->map(function ($item) use (&$num) {
                    array_unshift($item, $num++);

                    return $item;
                })->toArray();
                $this->table(['Id', 'Module', 'Class Name', 'Method', 'Comment'], $table);
            }
            else {
                $this->table(['Module', 'Class Name', 'Method', 'Comment', 'Verify'], $table);
            }
        }
        else {
            $this->info('So good, You did not has bad design.');
        }
        $this->warn('[Inspect:Module Namespace]');
        if ($classTable) {
            $this->table(['Module', 'Tips'], $classTable);
        }
        else {
            $this->info('So good, You did not has bad design in module.');
        }
    }

    /**
     * 检查文件命名
     */
    private function inspectFileName($slug): void
    {
        $folders = glob(weiran_path($slug) . '/src/{Events,Listeners,Models}', GLOB_BRACE);

        if (!count($folders)) {
            $this->warn('slug `' . $slug . '` has no file to check name');

            return;
        }

        $iterator = Finder::create()
            ->files()
            ->name('*.php')
            ->in($folders);

        $rules     = [];
        $checkFile = function (SplFileInfo $file) use ($slug, &$rules) {
            $pathName = $file->getPathname();
            $fileName = $file->getFilename();
            if (str_contains($pathName, '/Events/') && !str_ends_with(pathinfo($fileName)['filename'], 'Event')) {
                $rules[] = [
                    'slug' => $slug,
                    'file' => $fileName,
                    'path' => $pathName,
                ];
            }
            if (str_contains($pathName, '/Listeners/') && !str_ends_with(pathinfo($fileName)['filename'], 'Listener')) {
                $rules[] = [
                    'slug' => $slug,
                    'file' => $fileName,
                    'path' => $pathName,
                ];
            }

            if ((str_contains($pathName, '/Policies/')) && !str_ends_with(pathinfo($fileName)['filename'], 'Policy')) {
                $rules[] = [
                    'slug' => $slug,
                    'file' => $fileName,
                    'path' => $pathName,
                ];
            }
        };

        foreach ($iterator as $file) {
            $checkFile($file);
        }

        $this->warn('[Inspect:Name Rule]: ' . $slug);
        if ($rules) {
            $this->table([
                'slug' => 'Module', 'file' => 'FileName', 'path' => 'Path',
            ], $rules);
        }
        else {
            $this->info('Beautiful, Name rules are matched.');
        }
    }

    /**
     * 把所有的功能点都列出来
     */
    private function inspectController($slug): void
    {
        $table = [];
        $files = app('files')->allFiles(weiran_path($slug, 'src'));
        foreach ($files as $file) {
            $pathName = $file->getPathname();
            // 排除指定的类
            if (!Str::contains($pathName, ['Http/Request/'])) {
                continue;
            }

            $fileName = Str::after($pathName, 'Http/Request/');

            $relativePath = $file->getRelativePath();
            $className    = $this->className($slug, $relativePath, $file->getFilename());
            try {
                $refection = new ReflectionClass($className);
            }
            catch (Throwable $e) {
                $this->warn($slug . $e->getMessage());

                continue;
            }

            $methods = $refection->getMethods();
            if ($methods === null) {
                continue;
            }

            foreach ($methods as $method) {
                $methodName = $method->getName();
                // 排除继承的方法
                if ($method->class !== $className) {
                    continue;
                }

                // 排除魔术方法
                if (Str::startsWith($methodName, '__')) {
                    continue;
                }

                // 不是本文件中的. 略过
                if (basename($method->getFileName()) !== basename($file->getRealPath())) {
                    continue;
                }

                // 检查 注释
                $comment = $method->getDocComment();
                $item    = [
                    $slug,
                    $fileName,
                    $methodName,
                ];
                if (!$comment) {
                    $item[] = '[comment: missing]';
                }
                else {
                    $parser = new CommentParser();
                    $parsed = $parser->parseMethod($comment);
                    $item[] = str_replace(['/', PHP_EOL], ['', ''], $parsed['description']);
                }

                $table[] = $item;
            }
        }

        $this->table(['slug', 'file', 'do', 'description'], $table);
    }

    /**
     * 把所有的业务逻辑都列出来
     */
    private function inspectAction($slug): void
    {
        $table     = [];
        $directory = weiran_path($slug, 'src/Action');
        if (!app('files')->exists($directory)) {
            $this->info("{$slug} has no action.");

            return;
        }
        $files = app('files')->allFiles($directory);

        foreach ($files as $file) {
            $pathName = $file->getPathname();

            if (!preg_match('/Action\/(\w+)\.php/', $pathName, $match)) {
                continue;
            }

            $action = $match[1] ?? '';

            $className = weiran_class($slug, 'Action\\' . $action);

            try {
                $refection = new ReflectionClass($className);
            }
            catch (Throwable $e) {
                $this->warn($slug . $e->getMessage());

                continue;
            }

            $methods = $refection->getMethods();
            if ($methods === null) {
                continue;
            }

            foreach ($methods as $method) {
                $methodName = $method->getName();
                // 排除继承的方法
                if (
                    $method->class !== $className
                    ||
                    $method->isPrivate()
                    ||
                    $method->isProtected()
                    ||
                    $method->isConstructor()
                    ||
                    Str::startsWith($methodName, ['set', 'get'])
                ) {
                    continue;
                }

                // 不是本文件中的. 略过
                if (basename($method->getFileName()) !== basename($file->getRealPath())) {
                    continue;
                }

                // 检查 注释
                $comment = $method->getDocComment();
                $item    = [
                    $slug,
                    $action,
                    $methodName,
                ];
                if (!$comment) {
                    $item[] = '[comment: missing]';
                }
                else {
                    $parser      = new CommentParser();
                    $parsed      = $parser->parseMethod($comment);
                    $description = trim((string) ($parsed['description'] ?? ''), "\/\n");

                    $descriptions = explode(PHP_EOL, $description);
                    $item[]       = str_replace(['/', PHP_EOL], '', $descriptions[0] ?? '');
                }

                $table[] = $item;
            }
        }

        $this->table(['module', 'action', 'do', 'description'], $table);
    }

    /**
     * 权限定义和控制器对比
     */
    private function inspectPerms(): void
    {

        Artisan::call('weiran:optimize');

        $permissions = [];
        app('weiran')->enabled()->each(function ($module, $slug) use (&$permissions) {
            $directory = weiran_path($slug, 'src/Http/Request');
            if (app('files')->exists($directory)) {
                $files = app('files')->allFiles($directory);
                foreach ($files as $file) {
                    $pathName = $file->getPathname();

                    $path = str_replace('/', '\\', substr(Str::after($pathName, weiran_path($slug, 'src/')), 0, -4));

                    $className = weiran_class($slug, $path);

                    try {
                        $refection = new ReflectionClass($className);
                        if ($refection->isAbstract()) {
                            continue;
                        }
                    }
                    catch (Throwable $e) {
                        $this->warn($slug . $e->getMessage());

                        continue;
                    }
                    $ctlPermissions = (new $className())::$permission;
                    array_push($permissions, ...$ctlPermissions);
                }
            }

            $directory = weiran_path($slug, 'src/Models/Policies');
            if (app('files')->exists($directory)) {
                $files = app('files')->allFiles($directory);
                foreach ($files as $file) {
                    $pathName = $file->getPathname();

                    $path = str_replace('/', '\\', substr(Str::after($pathName, weiran_path($slug, 'src/')), 0, -4));

                    $className = weiran_class($slug, $path);

                    try {
                        $refection = new ReflectionClass($className);
                        if (!$refection->hasMethod('getPermissionMap')) {
                            continue;
                        }
                    }
                    catch (Throwable $e) {
                        $this->warn($slug . $e->getMessage());

                        continue;
                    }
                    $ctlPermissions = (new $className())::getPermissionMap();
                    array_push($permissions, ...$ctlPermissions);
                }
            }
        });

        $menus = $this->coreModule()->menus();

        $menus->each(function ($menu) use (&$permissions) {

            $groups = $menu['groups'] ?? [];
            foreach ($groups as $group) {
                $gc = $group['children'] ?? [];
                if (count($gc)) {
                    foreach ($gc as $gi) {
                        if ($gi['permission'] ?? '') {
                            $permissions[] = $gi['permission'] ?? '';
                        }
                        $gcc = $gi['children'] ?? [];
                        if (count($gcc)) {
                            foreach ($gcc as $gci) {
                                if ($gci['permission'] ?? '') {
                                    $permissions[] = $gci['permission'] ?? '';
                                }
                            }
                        }
                    }
                }
            }
        });

        $definedPermissions = $this->corePermission()->cachedPermissionNames();

        $notUsed = array_diff($definedPermissions->toArray(), $permissions);

        $notDefined = array_diff($permissions, $definedPermissions->toArray());

        $this->table(['Inspect Permission: Permission Defined But Not Used'], collect($notUsed)->map(fn($item) => [$item]));

        $this->table(['Inspect Permission: Permission Used But Not Defined'], collect($notDefined)->map(fn($item) => [$item]));
    }

    /**
     * 生成类名
     *
     * @param string $module        模块
     * @param string $relative_path 相对路径
     * @param string $file_name     文件名
     */
    private function className(string $module, string $relative_path, string $file_name): string
    {
        if (Str::startsWith($module, 'module.')) {
            $m         = Str::after($module, 'module.');
            $className = ucfirst(Str::camel($m));
        }
        else {
            $m = Str::after($module, 'weiran.');
            if (Str::contains($m, 'ext-')) {
                $className = 'Weiran\\Extension\\' . ucfirst(Str::camel(Str::after($m, 'ext-')));
            }
            else {
                $className = 'Weiran\\' . ucfirst(Str::camel($m));
            }
        }
        $paths = explode('/', $relative_path);
        foreach ($paths as $path) {
            if (Str::contains($relative_path, ['Provider/en_', 'Provider/zh_'])) {
                $className .= '\\' . $path;
            }
            else {
                $className .= '\\' . ucfirst(Str::camel($path));
            }

        }
        $basename  = pathinfo($file_name);
        $className .= '\\' . $basename['filename'];

        return str_replace('\\\\', '\\', $className);
    }

    /**
     * 是否驼峰类型
     *
     * @param string $str 字符串
     */
    private function isCamelCase(string $str): bool
    {
        return Str::camel($str) === $str;
    }
}
