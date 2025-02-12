<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Weiran\Core\Redis\RdsDb;

class RdsDbTest extends RdsBaseTest
{
    public function testExists(): void
    {
        $key   = $this->key('exists');
        $keyNx = $this->key('exists-nx');
        $this->rds->set($key, 'exists');
        $nx = $this->rds->exists($keyNx);
        $this->assertFalse($nx);
        $ex = $this->rds->exists($key);
        $this->assertTrue($ex);
        $this->rds->del($key);
    }

    public function testType(): void
    {
        $keyNx   = $this->key('type-nx');
        $keyStr  = $this->key('type-string');
        $keyList = $this->key('type-list');
        $keySet  = $this->key('type-set');
        $keyZSet = $this->key('type-zset');
        $keyHash = $this->key('type-hash');
        $this->rds->del([
            $keyNx, $keyStr, $keyList, $keySet, $keyZSet, $keyHash,
        ]);
        $this->assertEquals('none', $this->rds->type($keyNx));
        $this->rds->set($keyStr, 'string');
        $this->assertEquals('string', $this->rds->type($keyStr));
        $this->rds->lPush($keyList, 'list');
        $this->assertEquals('list', $this->rds->type($keyList));
        $this->rds->sAdd($keySet, 'set');
        $this->assertEquals('set', $this->rds->type($keySet));
        $this->rds->zAdd($keyZSet, ['zset' => 10086]);
        $this->assertEquals('zset', $this->rds->type($keyZSet));
        $this->rds->hSet($keyHash, 'set', 3101);
        $this->assertEquals('hash', $this->rds->type($keyHash));
    }

    public function testRename(): void
    {
        $ori    = $this->key('rename-ori');
        $dist   = $this->key('rename-dist');
        $distEx = $this->key('rename-dist-ex');

        $this->rds->del([
            $ori, $dist,
        ]);

        $this->rds->set($ori, 'ori');
        $this->rds->set($distEx, 'ori');
        $res = $this->rds->rename($ori, $dist);
        $this->assertTrue($res);

        $ex = $this->rds->renameNx($dist, $distEx);
        $this->assertFalse($ex);
        $res = $this->rds->renameNx($dist, $ori);
        $this->assertTrue($res);

        $this->rds->del([
            $ori, $distEx, $dist,
        ]);
    }

    public function testDel(): void
    {
        $this->rds->set('del-0', 'del');
        $this->rds->set('del-1', 'del');
        $int = $this->rds->del(['del-0', 'del-1']);
        $this->assertEquals(2, $int);
    }


    public function testTag(): void
    {
        $Tag = sys_tag('weiran-core');
        $Tag->hSet('testing-tag-h', 'a', 1);
        $Tag->hMSet('testing-tag-h', [
            'b' => 2,
            'c' => 3,
        ]);

        $all = RdsDb::instance()->hGetAll('tag:weiran-core:testing-tag-h');
        $this->assertCount(3, $all);

        $Tag->set('testing-tag-s', 'abc');
        $Tag->set('any-s', 'abc');

        $Tag->clear('testing-tag*');

        $this->assertEquals(null, $Tag->get('testing-tag-s'));
        $this->assertEquals('abc', $Tag->get('any-s'));

        $Tag->del('any-s');
    }
}