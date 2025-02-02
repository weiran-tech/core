<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Carbon\Carbon;
use DB;
use Weiran\Core\Redis\RdsPersist;
use Weiran\Framework\Application\TestCase;
use Weiran\Framework\Exceptions\ApplicationException;
use Weiran\Framework\Exceptions\TransactionException;

/**
 * 内存持久化测试
 * 这里需要创建一个表来接收数据库的数据
 * create table sys_test_persist
 * (
 *     id         int unsigned auto_increment primary key,
 *     title      varchar(50) default '' not null comment '描述',
 *     num        int                    not null comment '数量',
 *     girl_num   int         default 0  not null comment 'Girl',
 *     boy_num    int         default 0  not null comment '男生数量',
 *     created_at timestamp              null,
 *     updated_at timestamp              null
 * )
 * charset = utf8;
 */
class RdsPersistTest extends TestCase
{
    /**
     * 写入单条测试
     */
    public function testInsert()
    {
        $maxId = DB::table('sys_test_persist')->max('id');
        $item  = function () {
            return [
                'title'      => 'insert-' . $this->faker()->words(5, true),
                'num'        => $this->faker()->randomNumber(5),
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];
        };

        $items = [];
        $rand  = rand(4, 30);
        for ($i = 0; $i < $rand; $i++) {
            $items[] = $item();
        }

        /* attention: 如果这里不进行数据库提交, 则自增ID 会变大, 导致无法一致
         * ---------------------------------------- */
        RdsPersist::insert('sys_test_persist', $items);
        RdsPersist::execTable('sys_test_persist');
        $this->assertEquals($maxId + $rand, DB::table('sys_test_persist')->max('id'));
    }


    /**
     * 修改测试
     * @throws TransactionException
     * @throws ApplicationException
     */
    public function testUpdate()
    {
        // insert data
        $this->initOne();
        $one   = $this->fetchOne();
        $where = [
            'id' => data_get($one, 'id'),
        ];

        $getGiftNum = function ($where) {
            return DB::table('sys_test_persist')->where($where)->value('num');
        };

        /* 初始化
         * ---------------------------------------- */
        RdsPersist::update('sys_test_persist', $where, [
            'num' => 8,
        ]);
        RdsPersist::update('sys_test_persist', $where, [
            'num[+]' => 8,
        ]);
        RdsPersist::execTable('sys_test_persist');
        $this->assertEquals(8 + 8, $getGiftNum($where));

        /* -8
         * ---------------------------------------- */
        $ori = $getGiftNum($where);
        RdsPersist::update('sys_test_persist', $where, [
            'num[+]' => 8,
        ]);
        RdsPersist::execTable('sys_test_persist');
        $this->assertEquals($ori + 8, $getGiftNum($where));

        /* +8
         * ---------------------------------------- */
        $ori = $getGiftNum($where);
        RdsPersist::update('sys_test_persist', $where, [
            'num[-]' => 8,
        ]);
        RdsPersist::exec();
        $this->assertEquals($ori - 8, $getGiftNum($where));

        /* .8
        * ---------------------------------------- */
        $ori = $getGiftNum($where);
        RdsPersist::update('sys_test_persist', $where, [
            'num[.]' => 8,
        ]);
        RdsPersist::execTable('sys_test_persist');
        $this->assertEquals($ori . '8', $getGiftNum($where));
        DB::commit();
    }

    /**
     * @throws ApplicationException
     */
    public function testUpdateMoreFields()
    {
        $this->initOne();
        $one   = $this->fetchOne();
        $where = [
            'id' => data_get($one, 'id'),
        ];

        RdsPersist::update('sys_test_persist', $where, [
            'num' => 8,
        ]);
        $num = $this->faker()->randomNumber(3);
        RdsPersist::update('sys_test_persist', $where, [
            'boy_num' => $num,
        ]);
        $result = RdsPersist::where('sys_test_persist', $where);
        $this->assertEquals(8, $result['num']);
        $this->assertEquals($num, $result['boy_num']);
    }

    /**
     * 测试解析 Update
     */
    public function testParseUpdate()
    {
        $init = [
            'add'      => 0,
            'subtract' => 0,
            'preserve' => 0,
            'force'    => 0,
        ];

        $update = [
            'add[+]'      => 5,
            'subtract[-]' => 5,
            'force'       => 8,
        ];

        $result = RdsPersist::calcUpdate($init, $update);
        $this->assertEquals('5.00', $result['add']);
        $this->assertEquals('-5.00', $result['subtract']);
        $this->assertEquals(0, $result['preserve']);
        $this->assertEquals(8, $result['force']);


        $purColumn = function ($keys) {
            $columns = [];
            foreach ($keys as $key) {
                preg_match('/(?<column>[a-zA-Z0-9_]+)(\[(?<operator>\+|-|\.)])?/i', $key, $match);
                $columns[] = $match['column'];
            }
            return $columns;
        };
        $columns   = $purColumn(array_keys($update));
        $this->assertEquals('force', $columns[2]);

    }

    public function testParseUpdateWithDiff(): void
    {
        $init = [
            'add' => 0,
        ];

        $update = [
            'append' => 5,
        ];
        $result = RdsPersist::calcUpdate($init, $update);
        $this->assertCount(2, array_keys($result));
    }

    private function initOne(): void
    {
        // insert data
        DB::table('sys_test_persist')->insert([
            'title'      => 'init-' . $this->faker()->words(5, true),
            'num'        => $this->faker()->randomNumber(5),
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    private function fetchOne()
    {
        return DB::table('sys_test_persist')->inRandomOrder()->first();
    }
}