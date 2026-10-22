<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyTelegram;

class NotifyChannelTelegram extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'telegram', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyTelegram($notifiable);

            if (! $message instanceof NotifyTelegram) {
                return null;
            }

            return $message;
        });
    }
}
