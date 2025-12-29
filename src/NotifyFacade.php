<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RiseTechApps\Notify\Skeleton\SkeletonClass
 */
class NotifyFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'notify';
    }
}
