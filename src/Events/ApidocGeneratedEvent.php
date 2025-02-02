<?php

declare(strict_types = 1);

namespace Weiran\Core\Events;

use Weiran\Framework\Application\Event;

class ApidocGeneratedEvent extends Event
{

    /**
     * 文档的类型
     * @var string
     */
    public string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }
}
