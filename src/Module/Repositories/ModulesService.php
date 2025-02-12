<?php

declare(strict_types = 1);

namespace Weiran\Core\Module\Repositories;

use Illuminate\Support\Collection;
use Weiran\Core\Classes\PyCoreDef;
use Weiran\Framework\Support\Abstracts\Repository;

/**
 * 定义的服务项
 */
class ModulesService extends Repository
{

    /**
     * Initialize.
     * @param Collection $data 集合
     */
    public function initialize(Collection $data)
    {
        $this->items = sys_tag('weiran-core')->remember(
            PyCoreDef::ckModule('service'),
            PyCoreDef::MIN_HALF_DAY * 60,
            function () use ($data) {
                $collection = collect();
                $data->each(function ($items) use ($collection) {
                    $items = collect($items);
                    $items->each(function ($item, $key) use ($collection) {
                        $collection->put($key, $item);
                    });
                });

                return $collection->all();
            }
        );
    }
}
