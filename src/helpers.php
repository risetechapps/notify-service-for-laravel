<?php

use RiseTechApps\Notify\NotifyQuery;
use RiseTechApps\Notify\ServerQuery;

if (! function_exists('notifyQuery')) {
    function notifyQuery(): ServerQuery
    {
        return NotifyQuery::server();
    }
}
