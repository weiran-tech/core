<?php

declare(strict_types = 1);

namespace Weiran\Core\Events;

use Illuminate\Support\Collection;
use Weiran\Framework\Application\Event;

class PermissionInitEvent extends Event
{
    /**
     * @var Collection
     */
    public Collection $permissions;

    public function __construct($permissions)
    {
        $this->permissions = $permissions;
    }
}
