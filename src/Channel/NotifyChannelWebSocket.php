<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyWebSocket;

class NotifyChannelWebSocket extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'websocket', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyWebSocket($notifiable);

            if (! $message instanceof NotifyWebSocket) {
                return null;
            }

            return $message;
        });
    }
}
