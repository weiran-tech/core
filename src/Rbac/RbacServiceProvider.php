<?php

declare(strict_types = 1);

namespace Weiran\Core\Rbac;

use Blade;
use Illuminate\Support\ServiceProvider;
use Weiran\Core\Rbac\Permission\PermissionManager;

class RbacServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the module services.
     */
    public function boot()
    {
        // rbac
        $this->bootRbacBladeDirectives();
    }

    /**
     * Register the module services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRbac();
        $this->registerPermission();
    }

    public function provides(): array
    {
        return [
            'weiran.core.rbac',
            'weiran.core.permission',
        ];
    }

    /**
     * register rbac and alias
     */
    private function registerRbac()
    {
        $this->app->bind('weiran.core.rbac', function ($app) {
            return new Rbac($app);
        });
        $this->app->alias('weiran.core.rbac', Rbac::class);
    }

    private function registerPermission()
    {
        $this->app->singleton('weiran.core.permission', function ($app) {
            return new PermissionManager();
        });
    }

    /**
     * Register the blade directives
     *
     * @return void
     */
    private function bootRbacBladeDirectives()
    {
        // Call to Entrust::hasRole
        Blade::directive('role', function ($expression) {
            return "<?php if (\\Rbac::hasRole({$expression})) : ?>";
        });

        Blade::directive('endrole', function ($expression) {
            return '<?php endif; // Rbac::hasRole ?>';
        });

        // Call to Entrust::capable
        Blade::directive('permission', function ($expression) {
            return "<?php if (\\Rbac::capable({$expression})) : ?>";
        });

        Blade::directive('endpermission', function ($expression) {
            return '<?php endif; // Rbac::capable ?>';
        });

        // Call to Entrust::ability
        Blade::directive('ability', function ($expression) {
            return "<?php if (\\Rbac::ability({$expression})) : ?>";
        });

        Blade::directive('endability', function ($expression) {
            return '<?php endif; // Rbac::ability ?>';
        });
    }
}
