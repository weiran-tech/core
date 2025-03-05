<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Support;

use Artisan;
use Carbon\Carbon;
use Exception;
use JsonException;
use Throwable;
use Weiran\Framework\Application\TestCase;
use Weiran\Framework\Classes\Resp;
use Weiran\System\Models\PamAccount;

class FunctionTest extends TestCase
{

    public function testSysCacher(): void
    {
        for ($i = 0; $i <= 2; $i++) {
            $timestamp = Carbon::now()->timestamp;
            $core      = sys_cacher('weiran.core.action.verification-clear', function () {
                return Carbon::now()->timestamp;
            }, 2);
            if ($i === 0) {
                $this->assertEquals($timestamp, $core);
            }
            // 第一秒 未过期
            if ($i === 1) {
                $this->assertEquals($timestamp - 1, $core);
            }

            // 第二秒已经过期
            if ($i === 2) {
                $this->assertEquals($timestamp, $core);
            }
            sleep(1);
        }
    }

    /**
     * 缓存测试, 带标签的使用 Flush 来清除标签缓存
     */
    public function testSysCache(): void
    {
        sys_tag('weiran-core')->set('test.sys.cache', 'sys_cache');
        $value = sys_tag('weiran-core')->get('test.sys.cache');
        $this->assertEquals('sys_cache', $value);

        sys_tag('weiran-core')->set('test.sys_cache', 5);
        $this->assertEquals(5, sys_tag('weiran-core')->get('test.sys_cache'));
        sys_tag('weiran-core')->clear();
        $this->assertEquals(null, sys_tag('weiran-core')->get('test.sys_cache'));
    }

    public function testSysDb(): void
    {
        Artisan::call('weiran:optimize');
        $dbClass = sys_db(PamAccount::class);
        $dbTable = sys_db('pam_account');

        $this->assertEquals($dbClass, $dbTable);


        $arrClassEmail = sys_db(PamAccount::class, ['email']);
        $arrDbEmail    = sys_db('pam_account', ['email']);
        $this->assertEquals($arrClassEmail, $arrDbEmail);

        $strClassEmail = sys_db(PamAccount::class, 'email');
        $strDbEmail    = sys_db('pam_account', 'email');
        $this->assertEquals($strClassEmail, $strDbEmail);


    }

    /**
     * @return void
     * @throws JsonException
     */
    public function testSysFn(): void
    {
        $exception  = new Exception('Test Exception');
        $queryError = null;
        try {
            PamAccount::whereNotNull('column_not_exist')->first();
        } catch (Throwable $e) {
            $queryError = $e;
        }
        $resp = new Resp(112233, $this->faker()->words(12, true));

        $params = [
            $this->faker()->words(18, true),
            $exception,
            $queryError,
            $resp,
        ];

        // 当前支持的参数和非参数
        array_map(function ($param) {
            $this->outputVariables(sys_gen_mk(self::class, $param));
            sys_error('testing', $param);
            sys_info('testing', $param);
            sys_debug('testing', $param);
            sys_warning('testing', $param);
            sys_error('testing', $param, true);
            sys_info('testing', $param, true);
            sys_debug('testing', $param, true);
            sys_warning('testing', $param, true);
        }, $params);

        // 兼容之前的写法
        sys_error(self::class, $queryError);
        $this->assertTrue(true);
    }

}