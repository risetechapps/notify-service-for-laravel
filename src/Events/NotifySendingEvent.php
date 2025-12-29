<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;


class NotifySendingEvent
{
    use Dispatchable;

    public $notifiable;
    public $notification;

    public $channel;

    public function __construct($notifiable, Notification $notification, string $channel)
    {
        $this->notifiable = $notifiable;
        $this->notification = $notification;
        $this->channel = $channel;
    }
}
