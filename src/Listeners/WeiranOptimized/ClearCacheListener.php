<?php

declare(strict_types = 1);

namespace Weiran\Core\Listeners\WeiranOptimized;

use Weiran\Framework\Events\WeiranOptimizedEvent;

/**
 * 清除缓存
 */
class ClearCacheListener
{
    /**
     * @param WeiranOptimizedEvent $event 框架优化
     */
    public function handle(WeiranOptimizedEvent $event): void
    {
        sys_tag('weiran-core')->clear();

        // clear console logs
        $logs  = glob(storage_path('logs/console-*.log'));
        $count = count($logs);
        collect($logs)->each(function ($file, $idx) use ($count) {
            if ($idx + 5 < $count) {
                app('files')->delete($file);
            }
        });

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}
