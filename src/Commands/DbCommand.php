<?php

declare(strict_types = 1);

namespace Weiran\Core\Commands;


use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Weiran\Core\Classes\Inspect\CommentParser;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Framework\Helper\UtilHelper;
use ReflectionClass;
use Throwable;

/**
 * Db
 */
class DbCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'weiran:core:db
		{do : Action}
	';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Db Maintain Tool';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $do = $this->argument('do');

        switch ($do) {
            case 'fields':
                $this->dbFields();
                break;
            case 'suggest':
                $this->dbSuggest();
                break;
            case 'friendly':
                $this->dbFriendly();
                break;
        }

        return 0;
    }

    public function dbFriendly(): void
    {
        $modelDb = [];
        app('weiran')->enabled()->each(function ($module, $slug) use (&$modelDb) {
            $path  = weiran_path($slug, '/src/Models/*.php');
            $files = glob($path);
            foreach ($files as $file) {
                if (preg_match('/Models\/(?<model>[A-Za-z]+)\.php/', $file, $matches)) {
                    $classes = weiran_class($slug, 'Models\\' . $matches['model']);
                    $key     = weiran_friendly($classes);

                    $modelDb[] = [
                        'table'   => (new $classes)->getTable(),
                        'key'     => $key,
                        'perfect' => UtilHelper::isChinese($key) ? 'Y' : '-',
                    ];

                }
            }
        });
        $this->table(['Table', 'KEY'], $modelDb);
    }

    private function dbFields(): void
    {
        $seoDb = [];
        app('weiran')->enabled()->each(function ($module, $slug) use (&$seoDb) {
            $path  = weiran_path($slug, '/src/Models/*.php');
            $files = glob($path);
            try {
                foreach ($files as $file) {
                    if (preg_match('/Models\/(?<model>[A-Za-z]+)\.php/', $file, $matches)) {
                        $key        = Str::snake($matches['model']);
                        $className  = weiran_class($slug, 'Models\\' . $matches['model']);
                        $ref        = new ReflectionClass($className);
                        $docComment = $ref->getDocComment();
                        if (!$docComment) {
                            continue;
                        }
                        $CommentParser = new CommentParser();

                        $comments = $CommentParser->parseMethod($docComment);
                        $params   = $comments['params'] ?? [];
                        $fields   = [];
                        collect($params)->where('type', 'property')->each(function ($item) use (&$fields) {
                            $desc  = $item['var_desc'] ?? '';
                            $field = str_replace('$', '', $item['var_name']);
                            if (preg_match('/(?<input_value>\[.*?])/', $desc ?? '', $match)) {
                                $desc = str_replace($match['input_value'], '', $desc);
                            }
                            if (preg_match('/(?<input_value>\(.*?\))/', $desc ?? '', $match)) {
                                $desc = str_replace($match['input_value'], '', $desc);
                            }
                            $fields[$field] = $desc;
                        });
                        $seoDb[$key] = $fields;
                    }
                }
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
        });

        sys_tag('weiran-core')->hMSet(PyCoreDef::ckLangModels(), $seoDb);
        $this->info('Cached models Success!');
    }

    /**
     * 检查数据库配置
     */
    private function dbSuggest(): void
    {
        $tables = array_map('reset', DB::select('show tables'));

        $suggestString   = function ($col) {
            if (strpos($col['Type'], 'char') !== false) {
                if ($col['Null'] === 'YES') {
                    return '(Char-null)';
                }
                if ($col['Default'] !== '' && $col['Default'] !== null) {
                    if (!is_string($col['Default'])) {
                        return '(Char-default)';
                    }
                }
            }

            return '';
        };
        $suggestInt      = function ($col) {
            if (strpos($col['Type'], 'int') !== false) {
                switch ($col['Key']) {
                    case 'PRI':
                        // 主键不能为Null (Allow Null 不可选)
                        // Default 不可填入值
                        // 所以无任何输出
                        break;
                    default:
                        if (!is_numeric($col['Default'])) {
                            return '(Int-default)';
                        }
                        if ($col['Null'] === 'YES') {
                            return '(Int-Null)';
                        }
                        break;
                }
            }

            return '';
        };
        $suggestDecimal  = function ($col) {
            if (strpos($col['Type'], 'decimal') !== false) {
                if ($col['Default'] !== '0.00') {
                    return '(Decimal-default)';
                }
                if ($col['Null'] === 'YES') {
                    return '(Decimal-Null)';
                }
            }

            return '';
        };
        $suggestDatetime = function ($col) {
            if (strpos($col['Type'], 'datetime') !== false) {
                if ($col['Default'] !== null) {
                    return '(Datetime-default)';
                }
                if ($col['Null'] === 'NO') {
                    return '(Datetime-null)';
                }
            }

            return '';
        };
        $suggestFloat    = function ($col) {
            if (strpos($col['Type'], 'float') !== false) {
                return '(Float-set)';
            }

            return '';
        };

        $suggest = [];
        foreach ($tables as $table) {
            $columns = DB::select('show full columns from ' . $table);
            /*
             * column 字段
             * Field      : account_no
             * Type       : varchar(100)
             * Collation  : utf8_general_ci
             * Null       : NO
             * Key        : ""
             * Default    : ""
             * Extra      : ""
             * Privileges : select,insert,update,references
             * Comment    : 账号
             * ---------------------------------------- */

            foreach ($columns as $column) {
                $column            = (array) $column;
                $colSuggest        =
                    $suggestString($column) .
                    $suggestInt($column) .
                    $suggestDecimal($column) .
                    $suggestDatetime($column) .
                    $suggestFloat($column);
                $column['suggest'] = $colSuggest;
                if ($colSuggest) {
                    $suggest[] = [
                        $table,
                        data_get($column, 'Field'),
                        data_get($column, 'Type'),
                        data_get($column, 'Null'),
                        data_get($column, 'suggest'),
                        data_get($column, 'Comment'),
                    ];
                }
            }
        }
        if ($suggest) {
            $this->table(['Table', 'Field', 'Type', 'IsNull', 'Advice', 'Comment'], $suggest);
        }
    }
}