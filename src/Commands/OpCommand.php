<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;

use Illuminate\Console\Command;
use Mail;
use Weiran\System\Mail\MaintainMail;
use Throwable;

/**
 * User
 */
class OpCommand extends Command
{
    /**
     * @var string 名称
     */
    protected $signature = 'wr-core:op
        {do : Maintain type}
        {--title= : Mail title}
        {--content= : Mail content}
        {--file= : Mail attachment file}
    ';

    /**
     * @var string 描述
     */
    protected $description = 'Maintain Tool.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $do = $this->argument('do');
        switch ($do) {
            case 'mail':
                $title   = $this->option('title') ?: 'No Title';
                $content = $this->option('content') ?: 'No Content';
                $file    = $this->option('file');
                if (!config('poppy.core.op_mail')) {
                    $this->error(sys_gen_mk(self::class, 'Config `poppy.core.op_mail` not set. Can not send Op Mail'));
                    return 1;
                }
                try {
                    Mail::to(config('poppy.core.op_mail'))->send(new MaintainMail($title, $content, $file));
                } catch (Throwable $e) {
                    $this->error(sys_gen_mk(self::class, $e->getMessage()));
                    return 1;
                }
                break;
            case 'clear':
                sys_tag('wr-core')->clear();
                $this->info(sys_gen_mk(self::class, 'Clear Core Cache'));
                break;
            default:
                $this->warn('Error type in maintain tool.');
                return 1;
        }

        return 0;
    }
}