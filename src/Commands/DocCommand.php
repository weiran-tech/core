<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;

use Exception;
use Illuminate\Console\Command;
use Weiran\Core\Events\ApidocGeneratedEvent;
use Symfony\Component\Process\Process;

/**
 * 使用命令行生成 api 文档
 */
class DocCommand extends Command
{

    protected $signature = 'wr-core:doc
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
            case 'api':
                if (!command_exist('apidoc')) {
                    $this->error("apidoc 命令不存在\n");
                    return 1;
                }
                else {
                    $catalog = config('poppy.core.apidoc');
                    if (!$catalog) {
                        $this->error('尚未配置 apidoc 生成目录');

                        return 1;
                    }
                    // 多少个任务
                    $bar = $this->output->createProgressBar(count($catalog));

                    foreach ($catalog as $key => $dir) {
                        $this->performTask($key);
                        // 一个任务处理完了，可以前进一点点了
                        $bar->advance();
                    }
                    $bar->finish();
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

    /**
     * @param string $key 需要处理的 key
     */
    private function performTask(string $key): void
    {
        $path = base_path();
        $aim  = base_path('public/docs/' . $key);

        if (!file_exists($path)) {
            $this->error('Err > 目录 `' . $path . '` 不存在');
            return;
        }

        $def = config('poppy.core.apidoc.' . $key);
        if ($def['match'] ?? '') {
            $match = $def['match'];
            $type  = $key;
        }
        else {
            $matches = [
                'web' => 'Api.*/Web|ApiWeb|ApiV1|Api.*/Web',
                'dev' => 'api.*/dev|ApiDev|api/dev',
            ];
            $type    = $def['type'] ?? 'web';
            $match   = $matches[$type] ?? $matches['web'];
        }

        $arrMatches = explode('|', $match);
        $f          = array_map(function ($mt) {
            $f = ' -f "modules/.*/src/Http/Request/' . $mt . '/.*\.php$"';
            $f .= ' -f "poppy/.*/src/Http/Request/' . $mt . '/.*\.php$"';
            $f .= ' -f "vendor/poppy/.*/src/Http/Request/' . $mt . '/.*\.php$"';
            return $f;
        }, $arrMatches);
        $f          = implode(' ', $f);

        $lower = strtolower($key);
        $shell = 'apidoc -i ' . $path . '  -o ' . $aim . ' ' . $f;
        $this->info($shell);
        $process = Process::fromShellCommandline($shell);
        $process->start();
        $process->wait(function ($type, $buffer) use ($lower) {
            if (Process::ERR === $type) {
                $this->error('ERR > ' . $buffer . " [$lower]\n");
            }
        });

        try {
            event(new ApidocGeneratedEvent($type));
        } catch (Exception $e) {
            $this->warn($e->getMessage());
        }
        $this->info($process->getOutput());
    }
}