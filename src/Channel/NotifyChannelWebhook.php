<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyWebhook;

class NotifyChannelWebhook extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'webhook', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyWebhook($notifiable);

            if (!$message instanceof NotifyWebhook) {
                return null;
            }

            return $message;
        });
    }
}
