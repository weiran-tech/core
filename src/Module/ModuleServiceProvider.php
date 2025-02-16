<?php

declare(strict_types = 1);

namespace Weiran\Core\Module;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Class ModuleServiceProvider.
 */
class ModuleServiceProvider extends ServiceProvider implements DeferrableProvider
{

    /**
     * Register for service provider.
     */
    public function register()
    {
        $this->app->singleton('weiran.core.module', function () {
            return new ModuleManager();
        });
    }

    /**
     * @return array
     */
    public function provides(): array
    {
        return ['weiran.core.module'];
    }
}
