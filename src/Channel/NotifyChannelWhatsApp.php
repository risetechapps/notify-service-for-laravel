<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyWhatsApp;

/**
 * Canal 'notify.whatsapp'. O número vem de routeNotificationForWhatsapp() do
 * notifiable, a não ser que a mensagem já traga ->to().
 */
class NotifyChannelWhatsApp extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'whatsapp', function () use ($notifiable, $notification) {
            $message = $notification->toNotifyWhatsApp($notifiable);

            if (! $message instanceof NotifyWhatsApp) {
                return null;
            }

            if (! $message->hasRecipient() && $to = $notifiable->routeNotificationFor('whatsapp', $notification)) {
                $message->to($to);
            }

            return $message;
        });
    }
}
