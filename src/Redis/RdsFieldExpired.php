<?php

declare(strict_types = 1);

namespace Weiran\Core\Redis;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Weiran\Core\Classes\WeiranCoreDef;
use Throwable;

/**
 * field过期处理
 */
class RdsFieldExpired
{

    public const TYPE_HASH = 'hash';
    public const TYPE_SET  = 'set';
    public const TYPE_ZSET = 'zset';

    /**
     * 分隔符号
     * @var string $stripTag
     */
    private static string $stripTag = '@@';

    /**
     * @var null|RdsDb
     */
    private ?RdsDb $rds = null;

    /**
     * 清理过期的field
     * @return bool
     */
    public function clearExpiredField(): bool
    {
        // 需要清理的field
        $fields = sys_tag('weiran-core')->zRangeByScore(WeiranCoreDef::ckRdsKeyFieldExpired(), 0, time());
        $this->convertClearFields($fields);
        if ($fields) {
            sys_tag('weiran-core')->zRem(WeiranCoreDef::ckRdsKeyFieldExpired(), $fields);
        }

        return true;
    }

    public function __destruct()
    {
        try {
            if ($this->rds) {
                $this->rds->disconnect();
                $this->rds = null;
            }
            sys_tag('weiran-core')->disconnect();
        } catch (Throwable $e) {
        }
    }

    /**
     * 设置过期时间, 这里设置缓存 KEY 的过期时间
     * @param string     $database   数据库
     * @param string     $key        缓存key
     * @param int|string $field      field
     * @param string     $type       缓存类型
     * @param float|int  $expireTime 有效期
     * @return bool
     */
    public static function setFieldExpireTime(string $key, $field, string $type, string $database = 'default', $expireTime = 3600 * 24): bool
    {
        // "{$database}@@{$cacheKey}@@{$field}@@{$type}"
        $index = implode(self::$stripTag, [$database, $key, $field, $type]);

        $expiredAt = Carbon::now()->timestamp + $expireTime;
        sys_tag('weiran-core')->zAdd(WeiranCoreDef::ckRdsKeyFieldExpired(), [
            $index => $expiredAt,
        ]);
        return true;
    }

    /**
     * 清理hash
     * @param string $key    key
     * @param array  $fields 要清理的field
     * @return bool
     */
    protected function clearHash($key, $fields): bool
    {
        $this->rds->hdel($key, $fields);

        return true;
    }

    /**
     * 清理集合
     * @param string $key    key
     * @param array  $fields 要清理的field
     * @return bool
     */
    protected function clearSet($key, $fields): bool
    {
        $this->rds->srem($key, $fields);

        return true;
    }

    /**
     * 清理有序集合
     * @param string $key    key
     * @param array  $fields 要清理的field
     * @return bool
     */
    protected function clearZset($key, $fields): bool
    {
        $this->rds->zrem($key, $fields);

        return true;
    }

    /**
     * @param $fields
     * @return void
     */
    private function convertClearFields($fields)
    {
        $clearFields = [];

        foreach ($fields as $field) {
            try {
                [$database, $key, $field, $type] = explode(self::$stripTag, $field);
            } catch (Throwable $e) {
                continue;
            }

            $clearFields[] = compact('database', 'key', 'field', 'type');
        }

        if (!$clearFields) {
            return;
        }

        $this->groupClearFields($clearFields);
    }

    /**
     * 清理过期字段
     * @param $clearFields
     * @return bool
     */
    private function groupClearFields($clearFields): bool
    {
        $this->rds = new RdsDb();

        collect($clearFields)->groupBy('database')
            ->map(function ($fields, $database) {
                $this->rds->select($database);

                $fields->groupBy('key')->each(function ($field, $key) {
                    $fieldIndex = $field->pluck('field')->toArray();

                    $item = $field->first();
                    $type = $item['type'] ?? '';
                    if (!$item || !$fieldIndex || !$type || !$key) {
                        return true;
                    }

                    $method = Str::camel('clear_' . $type);
                    if (method_exists($this, $method)) {
                        try {
                            $this->$method($key, $fieldIndex);
                        } catch (Throwable $e) {

                        }
                    }
                });
            });

        return true;
    }
}