<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyDiscord;

class NotifyChannelDiscord extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'discord', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyDiscord($notifiable);

            if (!$message instanceof NotifyDiscord) {
                return null;
            }

            return $message;
        });
    }
}
