<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyApns;

class NotifyChannelApns extends NotifyChannel
{
    /**
     * @throws Exception
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            $message = $notification->toNotifyApns($notifiable);

            if (!$message instanceof NotifyApns) {
                return null;
            }

            if (!$message->token && $to = $notifiable->routeNotificationFor('apns', $notification)) {
                $message->token($to);
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'apns'));

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/apns", $message->toArray());

            if ($response->failed()) {
                throw new Exception('Error sending notification: ' . $response->body());
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'apns'));

            logglyInfo()->performedOn(self::class)
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification, 'response' => $responseJson])->log("Notification sent");

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'apns'));

            logglyError()
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification])
                ->exception($exception)->log("Error by sending notification");

            report($exception);

            return null;
        }
    }
}
