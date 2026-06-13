<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;

abstract class NotifyChannel
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = \RiseTechApps\Notify\Notify::BASE_URL;
        $this->apiKey = config('notify.key', '');
    }

    abstract public function send($notifiable, Notification $notification);
}
