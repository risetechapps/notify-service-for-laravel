<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyPush;

class NotifyChannelPush extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'push', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyPush($notifiable);

            if (!$message instanceof NotifyPush) {
                return null;
            }

            if (!$message->token && $to = $notifiable->routeNotificationFor('push', $notification)) {
                $message->token($to);
            }

            return $message;
        });
    }
}
