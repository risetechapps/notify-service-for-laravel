<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyMail;

class NotifyChannelMail extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        return $this->deliver($notifiable, $notification, 'mail', function () use ($notifiable, $notification) {
            if (!$to = $notifiable->routeNotificationFor('mail', $notification)) {
                return null;
            }

            $message = $notification->toNotifyMail($notifiable);

            if (!$message instanceof NotifyMail) {
                return null;
            }

            // routeNotificationFor('mail') pode devolver "email" ou ["email" => "Nome"].
            if (is_array($to)) {
                $email = array_key_first($to);
                $name  = is_string($to[$email] ?? null) ? $to[$email] : '';
                $message->to($email, $name);
            } else {
                $message->to($to);
            }

            return $message;
        });
    }
}
