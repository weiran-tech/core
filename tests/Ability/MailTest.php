<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Ability;

use Mail;
use Weiran\Framework\Application\TestCase;
use Weiran\Framework\Exceptions\ApplicationException;
use Weiran\System\Mail\MaintainMail;
use Weiran\System\Mail\TestMail;
use Throwable;

class MailTest extends TestCase
{

    private $mail;

    /**
     * @throws ApplicationException
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->mail = config('weiran.core.op_mail');
        if (!$this->mail) {
            throw new ApplicationException('配置 `weiran.core.op_mail` 尚未设置');
        }
    }

    /**
     * 发送邮件
     */
    public function testTest(): void
    {
        $content = '测试邮件发送';

        try {
            Mail::to($this->mail)->send(new TestMail($content));
            $this->assertTrue(true);
        } catch (Throwable $e) {
            $this->assertFalse(false, $e->getMessage());
        }
    }

    /**
     * 发送维护邮件
     */
    public function testMaintain(): void
    {
        try {
            Mail::to($this->mail)->send(new MaintainMail('Mail Title', 'Mail Content'));
            $this->assertTrue(true);
        } catch (Throwable $e) {
            $this->assertFalse(false, $e->getMessage());
        }
    }
}