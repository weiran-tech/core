<?php

declare(strict_types = 1);

namespace Weiran\Core\Services\Contracts;

/**
 * 返回 Html数据
 */
interface ServiceHtml
{
    /**
     * 输出
     * @return mixed
     */
    public function output();
}