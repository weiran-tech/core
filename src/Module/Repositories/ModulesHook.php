<?php

declare(strict_types = 1);

namespace Weiran\Core\Module\Repositories;

use Illuminate\Support\Collection;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Core\Classes\Traits\CoreTrait;
use Weiran\Framework\Exceptions\ApplicationException;
use Weiran\Framework\Support\Abstracts\Repository;

/**
 * 定义的钩子
 */
class ModulesHook extends Repository
{
    use CoreTrait;

    /**
     * Initialize.
     * @param Collection $data 集合
     * @throws ApplicationException
     */
    public function initialize(Collection $data)
    {
        $this->items = sys_tag('wr-core')->remember(
            PyCoreDef::ckModule('hook'),
            PyCoreDef::MIN_HALF_DAY * 60,
            function () use ($data) {
                $collection = collect();
                $data->each(function ($items) use ($collection) {
                    $items = collect($items);
                    $items->each(function ($item) use ($collection) {
                        $service = $this->coreModule()->services()->get($item['name']);

                        /* 2021-03-18: 当服务为空的时候, 不进行服务追加
                         * ---------------------------------------- */
                        if (empty($service)) {
                            return;
                        }
                        switch ($service['type']) {
                            case 'array':
                            case 'simple-array':
                                $data = (array) $collection->get($item['name']);
                                if (!isset($item['hooks'])) {
                                    throw new ApplicationException("Hook `{$item['name']}` did not has property `hooks`");
                                }
                                if (!is_array($item['hooks'])) {
                                    throw new ApplicationException("Hook `{$item['name']}` 的 `hooks` 必须是数组");
                                }
                                $collection->put($item['name'], array_merge($data, $item['hooks']));
                                break;
                            case 'form':
                                $collection->put($item['name'], $item['builder']);
                                break;
                            case 'html':
                                $data = (array) $collection->get($item['name']);
                                $collection->put($item['name'], array_merge($data, $item['hooks']));
                                break;
                            default:
                                throw new ApplicationException("`{$item['name']}` 的类型 `{$service['type']}` 不支持");
                        }
                    });
                });

                return $collection->all();
            }
        );
    }
}
