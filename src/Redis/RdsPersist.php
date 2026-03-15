<?php

declare(strict_types = 1);

namespace Weiran\Core\Redis;

use DB;
use Illuminate\Support\Str;
use JsonException;
use Weiran\Core\Classes\WeiranCoreDef;
use Weiran\Framework\Classes\Number;
use Weiran\Framework\Classes\Traits\AppTrait;
use Weiran\Framework\Exceptions\ApplicationException;
use Weiran\Framework\Exceptions\TransactionException;
use Weiran\Framework\Helper\ArrayHelper;

/**
 * Redis 持久化数据
 */
class RdsPersist
{
    use AppTrait;

    /**
     * @var string 新增
     */
    public const TYPE_INSERT = 'insert';

    /**
     * @var string 修改
     */
    public const TYPE_UPDATE = 'update';

    /**
     * 获取当前缓存的 where 条件的数据
     *
     * @throws JsonException
     */
    public static function where($table, array $where = []): array
    {
        $rdsDb     = sys_tag('weiran-core-persist');
        $rdsKey    = WeiranCoreDef::ckPersistPersist($table . '_' . self::TYPE_UPDATE);
        $whereJson = self::whereCondition($where);
        // 当前key的所有list数据
        $exists = $rdsDb->hExists($rdsKey, $whereJson);

        if ($exists) {
            // 获取存储的数据
            return $rdsDb->hGet($rdsKey, $whereJson);
        }

        return [];
    }

    /**
     * 将redis中的所有数据持久化到数据库
     * 执行将所有表的数据都写入数据库中可使用该方法
     *
     * @throws TransactionException
     * @throws JsonException
     */
    public static function exec(): void
    {
        $rdsDb = sys_tag('weiran-core-persist');
        // 所有新增数据的key
        $insertKeys = [];
        // 所有修改数据的key
        $updateKeys = [];

        $keys = $rdsDb->keys(WeiranCoreDef::ckPersistPersist('*'));

        foreach ($keys as $_key) {
            $keyName = substr($_key, strrpos($_key, ':') + 1);
            if (Str::endsWith($keyName, '_' . self::TYPE_INSERT)) {
                $insertKeys[] = $keyName;
            }
            if (Str::endsWith($keyName, '_' . self::TYPE_UPDATE)) {
                $updateKeys[] = $keyName;
            }
        }

        // 将类型为新增的数据持久化数据库
        self::execInsert($insertKeys);
        // 将类型为修改的数据持久化数据库
        self::execUpdate($updateKeys);
    }

    /**
     * 将redis中的指定表的数据持久化到数据库
     * 单独持久化某个表的时候可以使用该方法
     *
     * @throws TransactionException
     * @throws JsonException
     */
    public static function execTable(string $table = ''): void
    {
        // 将类型为新增的数据持久化数据库
        self::execInsert([$table . '_' . self::TYPE_INSERT]);
        // 将类型为修改的数据持久化数据库
        self::execUpdate([$table . '_' . self::TYPE_UPDATE]);
    }

    /**
     * 进行库的更新计算
     */
    public static function calcUpdate(array $former = [], array $update = []): array
    {
        if (empty($update)) {
            return $former;
        }
        foreach ($update as $k => $v) {
            preg_match('/(?<column>\w+)(\[(?<operator>\+|-|>|<|\.)])?/i', $k, $match);
            $column   = $match['column'];
            $operator = $match['operator'] ?? '';
            if (isset($former[$column])) {
                $ori = $former[$column];
                switch ($operator) {
                    case '+':
                        if (is_int($v)) {
                            $value = (int) (new Number($ori, 0))->add($v)->getValue();
                        }
                        else {
                            $value = (new Number($ori, 2))->add($v)->getValue();
                        }
                        break;
                    case '.':
                        $value = $ori . $v;
                        break;
                    case '-':
                        if (is_int($v)) {
                            $value = (int) (new Number($ori, 0))->subtract($v)->getValue();
                        }
                        else {
                            $value = (new Number($ori, 2))->subtract($v)->getValue();
                        }
                        break;
                        // preserve former
                    case '>':
                        $value = $ori;
                        break;
                        // preserve current
                    case '<':
                    default:
                        $value = $v;
                        break;
                }
                $former[$column] = $value;
            }
            else {
                $former[$column] = $v;
            }
        }

        return $former;
    }

    /**
     * 修改队列中的数据，根据条件没有找到的话就创建一条
     *
     * @param string $table  数据表名称
     * @param array  $where  查询条件(一维数组)
     * @param array  $update 修改条件(一维数组) <br>
     *                       此 update 条件支持 [+] 数据 + , [.] 数据组合, [>] 数据保留之前, [<] 将之前的数据覆盖
     *
     * @throws ApplicationException
     * @throws JsonException
     */
    public static function update(string $table = '', array $where = [], array $update = [])
    {
        $rdsKey = WeiranCoreDef::ckPersistPersist($table . '_' . self::TYPE_UPDATE);
        $rdsDb  = sys_tag('weiran-core-persist');

        if (empty($where)) {
            return;
        }

        ksort($where);
        array_walk($where, function (&$value) {
            $value = (string) $value;
        });

        $whereJson = json_encode($where, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        // 当前key的所有list数据
        $exists = $rdsDb->hexists($rdsKey, $whereJson);

        $db = DB::table($table)->where($where);
        if ($exists) {
            // 对之前的数据进行计算
            $former = (array) $rdsDb->hget($rdsKey, $whereJson);
            // diff fields
            $formerKey = self::pureKeys(array_keys($former));
            $updateKey = self::pureKeys(array_keys($update));
            $diffKeys  = array_diff($updateKey, $formerKey);
            if (count($diffKeys)) {
                $formerDiff = (array) (clone $db)->select($diffKeys)->first();
                if (!$formerDiff) {
                    throw new ApplicationException('数据持久化失败, 数据库中不存在相应数据');
                }
                $former = array_merge($former, $formerDiff);
            }
            $values = self::calcUpdate($former, $update);
        }
        else {
            $updateKeys = array_keys($update);
            $exists     = (clone $db)->exists();
            if (!$exists) {
                DB::table($table)->insert($where);
            }
            $former = (clone $db)->select(self::pureKeys($updateKeys))->first();
            $values = self::calcUpdate((array) $former, $update);
        }
        $rdsDb->hset($rdsKey, $whereJson, $values);
    }

    /**
     * 往队列中插入一条数据
     *
     * @param string $table  数据表名称
     * @param array  $values 需要插入的数据
     */
    public static function insert(string $table = '', array $values = []): bool
    {
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $rdsKey    = WeiranCoreDef::ckPersistPersist($table . '_' . self::TYPE_INSERT);
        $arrValues = [];
        foreach ($values as $value) {
            $arrValues[] = $value;
        }
        sys_tag('weiran-core-persist')->rPush($rdsKey, $arrValues);

        return true;
    }

    /**
     * 返回 Where 条件
     *
     * @throws JsonException
     */
    private static function whereCondition($where): false|string|null
    {
        if (empty($where)) {
            return null;
        }

        ksort($where);
        array_walk($where, function (&$value) {
            $value = (string) $value;
        });

        return json_encode($where, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 将类型为新增的数据持久化到数据库
     *
     * @param array $insert_keys 类型为新增的数据的keys,二维数组
     *
     * @throws TransactionException
     */
    private static function execInsert(array $insert_keys = [])
    {
        $rdsDb = sys_tag('weiran-core-persist');
        foreach ($insert_keys as $_key) {

            $rdsKey = WeiranCoreDef::ckPersistPersist($_key);
            // 当前key的所有list数据
            $_keyData = $rdsDb->lrange($rdsKey, 0, -1);
            $_arrData = [];

            foreach ($_keyData as $_item) {
                $_arrData[] = unserialize($_item);
            }

            // 从key中去取出表名
            $_tableName = Str::before($_key, '_' . self::TYPE_INSERT);

            // 插入成功
            if (!DB::table($_tableName)->insert($_arrData)) {
                throw new TransactionException('Insert 数据持久化失败, ' . $_tableName . ArrayHelper::toKvStr($_arrData));
            }

            // 从缓冲中删除key
            $rdsDb->del([$rdsKey]);
        }

    }

    /**
     * 将类型为修改的数据持久化到数据库
     *
     * @param array $update_keys 类型为修改的数据的keys,二维数组
     *
     * @throws TransactionException
     * @throws JsonException
     */
    private static function execUpdate(array $update_keys = []): void
    {
        $rdsDb = sys_tag('weiran-core-persist');
        foreach ($update_keys as $_key) {
            $rdsKey = WeiranCoreDef::ckPersistPersist($_key);
            // 当前key的所有list数据
            $keys = $rdsDb->hkeys($rdsKey);

            // 从key中去取出表名
            $tableName = Str::before($_key, '_' . self::TYPE_UPDATE);

            foreach ($keys as $where) {
                $arrWhere = json_decode($where, true, 512, JSON_THROW_ON_ERROR);
                $arrValue = $rdsDb->hget($rdsKey, $where);

                // 修改成功
                if (!DB::table($tableName)->where($arrWhere)->update($arrValue)) {
                    throw new TransactionException('Update 数据持久化失败, ' . $tableName . '' . ArrayHelper::toKvStr($arrWhere));
                }

                // 从缓冲中删除key
                $rdsDb->hdel($rdsKey, [$where]);
            }
        }
    }

    /**
     * 返回Column
     */
    private static function pureKeys($keys): array
    {
        $columns = [];
        foreach ($keys as $key) {
            preg_match('/(?<column>\w+)(\[(?<operator>\+|-|>|<|\.)])?/i', $key, $match);
            $columns[] = $match['column'];
        }

        return $columns;
    }
}
