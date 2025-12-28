<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Ability;

use JsonException;
use Mail;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;
use Weiran\Framework\Application\TestCase;
use Weiran\Framework\Exceptions\ApplicationException;
use Weiran\System\Classes\WeiranSystemDef;
use Weiran\System\Exceptions\SettingKeyNotMatchException;
use Weiran\System\Exceptions\SettingValueOutOfRangeException;
use Weiran\System\Mail\MaintainMail;
use Weiran\System\Mail\TestMail;

class MailTest extends TestCase
{
    private string $mail;

    /**
     * @throws ApplicationException
     * @throws JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws SettingKeyNotMatchException
     * @throws SettingValueOutOfRangeException
     */
    protected function setUp(): void
    {
        parent::setUp();
        WeiranSystemDef::fillMailConfig();
        $this->mail = (string) config('weiran.core.op_mail');
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
        }
        catch (Throwable $e) {
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
        }
        catch (Throwable $e) {
            $this->assertFalse(false, $e->getMessage());
        }
    }
}
