<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 使用命令行生成 api 文档
 */
class DocCommand extends Command
{

    protected $signature = 'core:doc
		{type : Document type to run. [api]}
	';

    protected $description = 'Generate Api Doc Document';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $type = $this->argument('type');
        switch ($type) {
            case 'api':
                $weiranDirs = app('files')->glob(app('path.weiran') . '/*/src/Http');
                $moduleDirs = app('files')->glob(app('path.module') . '/*/src/Http');
                $openapi    = Generator::scan(array_merge($weiranDirs, $moduleDirs));

                try {
                    app('files')->ensureDirectoryExists(public_path('docs/swagger-ui/'));
                    app('files')->put(public_path('docs/swagger-ui/weiran.json'), $openapi?->toJson());
                    $this->info(
                        'Output swagger api doc, view ' . $this->laravel['config']->get('app.url') . '/docs/swagger-ui/'
                    );
                } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
                    $this->error($e->getMessage());
                }
                break;
            case 'cs':
                $this->info(
                    'Please Run Command:' . "\n" .
                    'php-cs-fixer fix --config=' . framework_path('.php_cs') . ' --diff --dry-run --verbose --diff-format=udiff'
                );
                break;
            case 'cs-pf':
                $this->info(
                    'Please Run Command:' . "\n" .
                    'php-cs-fixer fix ' . framework_path() . ' --config=' . framework_path('.php_cs') . ' --diff --dry-run --verbose --diff-format=udiff'
                );
                break;
            case 'php':
                $doctum = storage_path('doctum/doctum.phar');
                $config = storage_path('doctum/config.php');
                if (!file_exists($config)) {
                    $this->warn(
                        'Please Run Command To Publish Config:' . "\n" .
                        'php artisan vendor:publish '
                    );
                }
                if (file_exists($doctum)) {
                    $this->info(
                        'Please Run Command:' . "\n" .
                        'php ' . $doctum . ' update ' . $config
                    );
                }
                else {
                    $this->warn(
                        'Please Run Command To Install doctum.phar:' . "\n" .
                        'curl https://doctum.long-term.support/releases/latest/doctum.phar --output ' . $doctum
                    );
                }
                break;
            case 'log':
                $this->info(
                    'Please Run Command:' . "\n" .
                    'tail -20f storage/logs/laravel-`date +%F`.log'
                );
                break;
            default:
                $this->comment('Type is now allowed.');
                break;
        }
    }
}