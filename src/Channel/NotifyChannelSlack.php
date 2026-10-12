<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifySlack;

class NotifyChannelSlack extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'slack', function () use ($notifiable, $notification) {
            $message = $notification->toNotifySlack($notifiable);

            if (!$message instanceof NotifySlack) {
                return null;
            }

            return $message;
        }, function (array $data) use ($notifiable, $notification) {
            // Só usa o canal do Notifiable quando a mensagem não trouxe alvo
            // (nem channel explícito nem Incoming Webhook).
            if (empty($data['channel']) && empty($data['slack_webhook_url'])
                && $channel = $notifiable->routeNotificationFor('slack', $notification)) {
                $data['channel'] = $channel;
            }

            return $data;
        });
    }
}
