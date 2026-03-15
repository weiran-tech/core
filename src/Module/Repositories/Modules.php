<?php

declare(strict_types = 1);

namespace Weiran\Core\Module\Repositories;

use Illuminate\Support\Collection;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Weiran\Core\Classes\WeiranCoreDef;
use Weiran\Core\Module\Module;
use Weiran\Framework\Exceptions\LoadConfigurationException;
use Weiran\Framework\Support\Abstracts\Repository;

/**
 * 所有模块的配置信息.
 */
class Modules extends Repository
{
    protected bool $loadFromCache = true;

    /**
     * Initialize.
     *
     * @param Collection $slugs 集合
     *
     * @throws LoadConfigurationException
     */
    public function initialize(Collection $slugs)
    {
        $files       = app('files');
        $this->items = sys_tag('weiran-core')->remember(
            WeiranCoreDef::ckModule('module'),
            WeiranCoreDef::MIN_HALF_DAY * 60,
            function () use ($slugs, $files) {
                // load from file
                $this->loadFromCache = false;
                $collection          = collect();
                $slugs->each(function ($slug) use ($collection, $files) {
                    $module = new Module($slug);
                    if ($files->exists($module->directory() . DIRECTORY_SEPARATOR . 'manifest.json')) {
                        // load config
                        $configurations = $this->loadConfigurations($module->directory());

                        // set config to module
                        $configurations->isNotEmpty() && $configurations->each(function ($value, $item) use ($module) {
                            $module->offsetSet($item, $value);
                        });

                        // is enable
                        $module->offsetSet('enabled', $module->isEnabled());

                        // put all config to repository use key `slug`
                        $collection->put($slug, $module);
                    }
                });

                return $collection->all();
            }
        );
    }

    public function enabled(): Collection
    {
        return $this->filter(function (Module $module) {
            return $module->get('enabled') === true;
        });
    }

    public function loaded(): Collection
    {
        return $this->filter(function (Module $module) {
            return $module->get('initialized') === true;
        });
    }

    public function notLoaded(): Collection
    {
        return $this->filter(function (Module $module) {
            return $module->get('initialized') === false;
        });
    }

    /**
     * Load configuration from module configurations folder.
     *
     * @param string $directory 字典
     *
     * @throws LoadConfigurationException
     */
    protected function loadConfigurations(string $directory): Collection
    {
        $files = app('files');
        $directory .= DIRECTORY_SEPARATOR . 'configurations';
        if ($files->isDirectory($directory)) {
            $configurations = collect();

            // put it in filename key
            collect($files->files($directory))->each(function (SplFileInfo $file) use ($configurations, $files) {
                $name = basename($file->getBasename(), '.yaml');
                if ($name !== 'module' && $files->isReadable($file)) {
                    $configurations->put($name, Yaml::parse(file_get_contents($file->getPathname())));
                }
            });

            return $configurations;
        }

        throw new LoadConfigurationException('Load Module fail: ' . $directory);
    }
}
