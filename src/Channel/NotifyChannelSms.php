<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifySms;

class NotifyChannelSms extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'sms', function () use ($notifiable, $notification) {
            if (! $to = $notifiable->routeNotificationFor('sms', $notification)) {
                return null;
            }

            $message = $notification->toNotifySms($notifiable);

            if (! $message instanceof NotifySms) {
                return null;
            }

            $message->to($to);

            return $message;
        });
    }
}
