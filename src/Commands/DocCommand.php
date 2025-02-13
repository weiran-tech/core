<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;

use Illuminate\Console\Command;

/**
 * 使用命令行生成 api 文档
 */
class DocCommand extends Command
{

    protected $signature = 'weiran-core:doc
		{type : Document type to run. [api]}
	';

    protected $description = 'Generate Api Doc Document';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        switch ($type) {
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

                    return 1;
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
                    return 1;
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

        return 0;
    }
}