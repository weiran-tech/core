<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Illuminate\Support\Str;
use Random\RandomException;

class RdsHashTest extends RdsBaseTest
{
    public function testHSet(): void
    {
        $key    = $this->key('h-set');
        $field  = $this->faker()->userName;
        $result = $this->rds->hSet($key, $field, $field);
        $this->assertEquals(1, $result);
        // 存在更新成功返回 0
        $result = $this->rds->hSet($key, $field, $field);
        $this->assertEquals(0, $result);
        $this->rds->del($key);
    }

    /**
     * 测试存储 500 万条数据的内存占用
     * 模拟 sys_parent_id 的存储场景：field 为用户 ID，value 为父级 ID
     * @throws RandomException
     */
    public function testHSet500w(): void
    {
        $this->markTestSkipped('耗时较长，默认跳过，需要时手动执行');

        $key = $this->key('h-set-500w');
        $this->rds->del($key);

        $totalCount = 5000000; // 500 万
        $batchSize  = 10000;    // 每批次 1 万条

        echo "\n开始写入 {$totalCount} 条数据...\n";
        $startTime   = microtime(true);
        $startMemory = memory_get_usage(true);

        // 获取写入前的 Redis 内存使用
        $redis        = app('redis')->connection();
        $beforeInfo   = $redis->command('info', ['memory']);
        $beforeMemory = (int) $beforeInfo['used_memory'];

        // 分批写入数据
        for ($i = 0; $i < $totalCount; $i += $batchSize) {
            $batch = [];
            for ($j = 0; $j < $batchSize && ($i + $j) < $totalCount; $j++) {
                $userId = $i + $j + 1;
                // 模拟父级 ID：假设每 10 个用户有一个父级用户
                $parentId                = (int) (($userId - 1) / 10) + 1;
                $batch[(string) $userId] = (string) $parentId;
            }
            $this->rds->hMSet($key, $batch);

            // 每 50 万条输出一次进度
            if (($i + $batchSize) % 500000 === 0) {
                $progress = ($i + $batchSize) / $totalCount * 100;
                $elapsed  = microtime(true) - $startTime;
                echo sprintf("进度: %.1f%% (已用时: %.2fs)\n", $progress, $elapsed);
            }
        }

        $endTime   = microtime(true);
        $endMemory = memory_get_usage(true);

        // 获取写入后的 Redis 内存使用
        $afterInfo   = $redis->command('info', ['memory']);
        $afterMemory = (int) $afterInfo['used_memory'];

        // 验证数据条数
        $count = $this->rds->hLen($key);
        $this->assertEquals($totalCount, $count);

        // 计算并输出统计信息
        $timeCost        = $endTime - $startTime;
        $phpMemoryCost   = $endMemory - $startMemory;
        $redisMemoryCost = $afterMemory - $beforeMemory;

        echo "\n=== 存储 500 万条数据统计 ===\n";
        echo sprintf("总条数: %s\n", number_format($totalCount));
        echo sprintf("耗时: %.2f 秒\n", $timeCost);
        echo sprintf("QPS: %s 条/秒\n", number_format($totalCount / $timeCost));
        echo sprintf("PHP 内存增加: %s\n", $this->formatBytes($phpMemoryCost));
        echo sprintf("Redis 内存增加: %s\n", $this->formatBytes($redisMemoryCost));
        echo sprintf("平均每条数据: %s\n", $this->formatBytes($redisMemoryCost / $totalCount));
        echo sprintf("Redis 内存总使用: %s\n", $this->formatBytes($afterMemory));
        echo sprintf("Redis 内存峰值: %s\n", $this->formatBytes((int) $afterInfo['used_memory_peak']));
        echo "\n";

        // 随机测试几个查询
        echo "=== 随机查询测试 ===\n";
        $queryStartTime = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $userId           = random_int(1, $totalCount);
            $parentId         = $this->rds->hGet($key, (string) $userId);
            $expectedParentId = (int) (($userId - 1) / 10) + 1;
            $this->assertEquals((string) $expectedParentId, $parentId);
        }
        $queryEndTime = microtime(true);
        $queryTime    = $queryEndTime - $queryStartTime;
        echo sprintf("1000 次随机查询耗时: %.4f 秒\n", $queryTime);
        echo sprintf("平均查询耗时: %.4f 毫秒\n", $queryTime * 1000 / 1000);
        echo "\n";

        // 清理测试数据
        $this->rds->del($key);
    }

    public function testHSetNx(): void
    {
        $key    = $this->key('h-set-nx');
        $field  = $this->faker()->userName;
        $result = $this->rds->hSetNx($key, $field, $field);
        $this->assertEquals(1, $result);
        $result = $this->rds->hSetNx($key, $field, $field);
        $this->assertEquals(0, $result);
        $this->rds->del($key);
    }

    public function testHGet(): void
    {
        $key   = $this->key('h-get');
        $field = $this->faker()->userName;
        $this->rds->hSet($key, $field, $field);

        $result = $this->rds->hGet($key, $field . '-null');
        $this->assertNull($result);

        $result = $this->rds->hGet($key, $field);
        $this->assertEquals($field, $result);

        $this->rds->hSet($key, $field . '-array', [$field]);
        $result = $this->rds->hGet($key, $field . '-array');
        $this->assertEquals([$field], $result);
        $this->rds->del($key);
    }

    public function testHExists()
    {
        $key   = $this->key('h-exists');
        $field = $this->faker()->userName;
        $this->rds->hSet($key, $field, $field);

        $result = $this->rds->hExists($key, $field . '-null');
        $this->assertFalse($result);
        $result = $this->rds->hExists($key, $field);
        $this->assertTrue($result);
        $this->rds->del($key);
    }

    public function testHDel()
    {
        $key   = $this->key('h-del');
        $field = $this->faker()->userName;
        $this->rds->hSet($key, $field, $field);
        $this->rds->hSet($key, $field . '-1', $field);
        $this->rds->hSet($key, $field . '-2', $field);

        $result = $this->rds->hDel($key, [
            $field . '-1',
            $field . '-null',
        ]);
        $this->assertEquals(1, $result);
        $this->rds->del($key);
    }

    public function testHLen(): void
    {
        $key     = $this->key('h-len');
        $field   = $this->faker()->userName;
        $randMax = $this->faker()->randomNumber(3);
        $len     = $this->rds->hlen($key);
        $this->assertEquals(0, $len);
        for ($i = 0; $i < $randMax; $i++) {
            $this->rds->hSet($key, $field . '-' . $i, $field . '-' . $randMax);
        }
        $len = $this->rds->hlen($key);
        $this->assertEquals($randMax, $len);
        $this->rds->del($key);
    }

    public function testHStrLen(): void
    {
        $key   = $this->key('h-str-len');
        $value = (string) $this->faker()->randomNumber(8);
        $len   = $this->rds->hStrLen($key, 'no-str');
        $this->assertEquals(0, $len);
        $this->rds->hSet($key, 'str-1', $value);
        $len = $this->rds->hStrLen($key, 'str-1');
        $this->assertEquals(strlen($value), $len);
        $this->rds->del($key);
    }

    public function testHIncrBy(): void
    {
        $key = $this->key('h-incr-by');
        $this->rds->del($key);
        $fault = $this->rds->hIncrBy($key, 'default');
        $this->assertEquals(1, $fault);
        $fault = $this->rds->hIncrBy($key, 'default', 30);
        $this->assertEquals(31, $fault);
        $fault = $this->rds->hIncrBy($key, 'default', (int) 5e3);
        $this->assertEquals(5031, $fault);
        $fault = $this->rds->hIncrByFloat($key, 'default', '0.01');
        $this->assertEquals(5031.01, $fault);
        $fault = $this->rds->hIncrByFloat($key, 'default', '0.01');
        $this->assertEquals(5031.02, $fault);
        $this->rds->del($key);
    }

    public function testHMSet(): void
    {
        $key = $this->key('h-m-set');
        // clear key
        $this->rds->del([$key, $key . '-array']);
        $randMax = $this->faker()->randomNumber(3);
        $len     = $this->rds->hlen($key);
        $this->assertEquals(0, $len);
        $values = [];
        $array  = [];
        for ($i = 0; $i < $randMax; $i++) {
            $values['str-' . $i]  = [$i];
            $array['array-' . $i] = [$i];
        }
        $this->rds->hMSet($key, $values);
        $this->rds->hMSet($key . '-array', $array);
        $len = $this->rds->hLen($key);
        $this->assertEquals($randMax, $len);
        $this->rds->del([$key, $key . '-array']);
    }

    public function testHMGet(): void
    {
        $key = $this->key('h-m-get');
        $this->rds->del([$key]);
        $value = [
            'str'   => $this->faker()->userName,
            'array' => [$this->faker()->userName],
        ];
        $this->rds->hMSet($key, $value);
        $get = $this->rds->hMGet($key, ['str', 'array']);
        $this->assertEquals($value, $get);
        $this->rds->del($key);
    }

    public function testHKeys(): void
    {
        $key = $this->key('h-keys');
        $this->rds->del($key);
        $keys = $this->rds->hkeys($key);
        $this->assertEmpty($keys);
        $value = [
            'str'   => $this->faker()->userName,
            'array' => [$this->faker()->userName],
        ];
        $this->rds->hMSet($key, $value);
        $keys = $this->rds->hkeys($key);
        $this->assertEquals(array_keys($value), $keys);
        $this->rds->del($key);
    }

    public function testHVals(): void
    {
        $key = $this->key('h-vals');
        $this->rds->del($key);
        $vals = $this->rds->hVals($key);
        $this->assertEmpty($vals);
        $value = [
            'str'   => $this->faker()->userName,
            'array' => [$this->faker()->userName],
        ];
        $this->rds->hMSet($key, $value);
        $vals = $this->rds->hVals($key);
        $this->assertEquals(array_values($value), $vals);
        $this->rds->del($key);
    }

    public function testHGetAll(): void
    {
        $key = $this->key('h-get-all');
        $this->rds->del($key);
        $vals = $this->rds->hGetAll($key);
        $this->assertEmpty($vals);
        $value = [
            'str'   => $this->faker()->userName,
            'array' => [$this->faker()->userName],
        ];
        $this->rds->hMSet($key, $value);
        $vals = $this->rds->hGetAll($key);
        $this->assertEquals($value, $vals);
        $this->rds->del($key);
    }

    public function testHScan(): void
    {
        $key = $this->key('h-scan');
        $this->rds->del($key);
        $faker   = $this->faker();
        $randMax = $faker->randomNumber(2);
        $values  = [];
        for ($i = 0; $i < $randMax; $i++) {
            $values[$faker->userName . '-' . $i] = $faker->url . '?q=' . Str::random(64);
        }
        $this->rds->hMSet($key, $values);

        $vals = $this->rds->hScan($key, 0, [
            'count' => 8,
        ]);
        $this->assertGreaterThan(0, $vals[0]);
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return sprintf('%.2f %s', $bytes, $units[$i]);
    }
}
