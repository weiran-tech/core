<?php

declare(strict_types = 1);

namespace Weiran\Core;

use Illuminate\Console\Scheduling\Schedule;
use Weiran\Core\Listeners\WeiranOptimized\ClearCacheListener;
use Weiran\Framework\Events\WeiranOptimized;
use Weiran\Framework\Events\WeiranSchedule;
use Weiran\Framework\Exceptions\ModuleNotFoundException;
use Weiran\Framework\Support\WeiranServiceProvider;

class ServiceProvider extends WeiranServiceProvider
{

    protected array $listens = [
        // poppy
        WeiranOptimized::class => [
            ClearCacheListener::class,
        ],
    ];

    /**
     * Bootstrap the module services.
     * @return void
     * @throws ModuleNotFoundException
     */
    public function boot(): void
    {
        parent::boot('weiran.core');

        // 注册 api 文档配置
        $this->publishes([
            __DIR__ . '/../resources/config/doctum-config.php' => storage_path('doctum/config.php'),
        ], 'weiran');

        $this->publishes([
            __DIR__ . '/../resources/swagger-ui/' => public_path('docs/swagger-ui/'),
        ], 'weiran-assets');
    }

    /**
     * Register the module services.
     * @return void
     */
    public function register(): void
    {
        // 合并配置
        $this->mergeConfigFrom(__DIR__ . '/../resources/config/core.php', 'weiran.core');

        $this->app->register(Module\ModuleServiceProvider::class);
        $this->app->register(Rbac\RbacServiceProvider::class);
        $this->app->register(Http\MiddlewareServiceProvider::class);

        $this->registerConsole();

        $this->registerSchedule();
    }


    private function registerSchedule(): void
    {
        app('events')->listen(WeiranSchedule::class, function (Schedule $schedule) {

        });
    }


    private function registerConsole(): void
    {
        // system
        $this->commands([
            Commands\PermissionCommand::class,
            Commands\DocCommand::class,
            Commands\DbCommand::class,
            Commands\OpCommand::class,
            Commands\InspectCommand::class,
            Commands\PersistCommand::class,
        ]);
    }
}