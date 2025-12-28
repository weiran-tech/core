<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Redis;

use Illuminate\Support\Str;
use Weiran\Framework\Exceptions\ApplicationException;

class RdsHashTest extends RdsBaseTest
{
    public function testHSet()
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

    public function testHSetNx()
    {
        $key    = $this->key('h-set-nx');
        $field  = $this->faker()->userName;
        $result = $this->rds->hSetNx($key, $field, $field);
        $this->assertEquals(1, $result);
        $result = $this->rds->hSetNx($key, $field, $field);
        $this->assertEquals(0, $result);
        $this->rds->del($key);
    }

    public function testHGet()
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

    public function testHLen()
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

    public function testHStrLen()
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

    public function testHIncrBy()
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

    public function testHMSet()
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

    public function testHMGet()
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

    public function testHKeys()
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

    public function testHVals()
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

    public function testHGetAll()
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

    /**
     * @throws ApplicationException
     */
    public function testHScan(): void
    {
        $key = $this->key('h-scan');
        $this->rds->del($key);
        $randMax = $this->faker()->randomNumber(2);
        $values  = [];
        for ($i = 0; $i < $randMax; $i++) {
            $values[$this->faker()->userName . '-' . $i] = $this->faker()->url . '?q=' . Str::random(64);
        }
        $this->rds->hMSet($key, $values);

        $vals = $this->rds->hScan($key, 0, [
            'count' => 8,
        ]);
        $this->assertGreaterThan(0, $vals[0]);
    }
}
