<?php

namespace RiseTechApps\Notify\Events;

use Exception;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;

class NotifyFailedEvent
{
    use Dispatchable;

    public function __construct(
        public $notifiable,
        public Notification $notification,
        public Exception $exception,
        public string $channel,
    ) {}
}
