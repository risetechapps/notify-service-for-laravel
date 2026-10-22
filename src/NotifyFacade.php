<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Facade;

/**
 * @see NotifyQuery
 */
class NotifyFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotifyQuery::class;
    }
}
