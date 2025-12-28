<?php

declare(strict_types = 1);

namespace Weiran\Core\Module;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Weiran\Framework\Classes\Traits\HasAttributesTrait;

/**
 * Class Module.
 */
class Module implements Arrayable, ArrayAccess, JsonSerializable
{
    use HasAttributesTrait;

    /**
     * Module constructor.
     */
    public function __construct($slug)
    {
        $this->attributes = [
            'directory' => weiran_path($slug),
            'namespace' => weiran_class($slug),
            'slug'      => $slug,
            'enabled'   => app('weiran')->isEnabled($slug),
        ];
    }

    public function directory(): string
    {
        return $this->get('directory');
    }

    public function namespace(): string
    {
        return $this->get('namespace');
    }

    public function slug(): string
    {
        return $this->get('slug');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->offsetGet('enabled');
    }

    public function validate(): bool
    {
        return $this->offsetExists('name')
            && $this->offsetExists('identification')
            && $this->offsetExists('description')
            && $this->offsetExists('authors');
    }
}
