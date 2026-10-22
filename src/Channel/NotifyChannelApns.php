<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyApns;

class NotifyChannelApns extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'apns', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyApns($notifiable);

            if (! $message instanceof NotifyApns) {
                return null;
            }

            if (! $message->token && $to = $notifiable->routeNotificationFor('apns', $notification)) {
                $message->token($to);
            }

            return $message;
        });
    }
}
