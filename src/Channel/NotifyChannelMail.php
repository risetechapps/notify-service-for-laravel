<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyMail;

class NotifyChannelMail extends NotifyChannel
{

    public function send($notifiable, Notification $notification)
    {
        try {
            if (!$to = $notifiable->routeNotificationFor('mail', $notification)) {
                return null;
            }

            $message = $notification->toNotifyMail($notifiable);

            if (!$message instanceof NotifyMail) {
                return null;
            }

            $message->to($to, config('app.name'));

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'mail'));

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/mail", $message->toArray());

            if ($response->failed()) {
                throw new Exception('Error sending notification: ' . $response->body());
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'mail'));

            logglyInfo()->performedOn(self::class)
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification, 'response' => $responseJson])->log("Notification sent");

            return $responseJson;

        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'mail'));

            logglyError()
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification])
                ->exception($exception)->log("Error by sending notification");
            report($exception);

            return null;
        }
    }
}
