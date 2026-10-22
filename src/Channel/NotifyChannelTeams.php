<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyTeams;

class NotifyChannelTeams extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'teams', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyTeams($notifiable);

            if (! $message instanceof NotifyTeams) {
                return null;
            }

            return $message;
        });
    }
}
