<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;


use Illuminate\Console\Command;
use Weiran\Core\Redis\RdsPersist;
use Throwable;

/**
 * Redis 持久化写入到数据库
 */
class PersistCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'weiran-core:persist
		{table : Table to exec. [pam_log...|all]}
	';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Redis Persistence To DataBase;';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');

        // 将所有数据写入数据库
        try {
            if ($table === 'all') {
                RdsPersist::exec();
            }
            else {
                RdsPersist::execTable($table);
            }
        } catch (Throwable $e) {
            $this->error(sys_gen_mk(self::class, $e->getMessage()));
        }

        return 0;
    }
}