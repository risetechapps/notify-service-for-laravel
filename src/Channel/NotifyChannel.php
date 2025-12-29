<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;

abstract class NotifyChannel
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = "https://notifykit.app.br";
        $this->apiKey = config('notify.key', '');
    }

    abstract public function send($notifiable, Notification $notification);
}
