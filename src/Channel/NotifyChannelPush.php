<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyPush;

class NotifyChannelPush extends NotifyChannel
{
    /**
     * @throws Exception
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            $message = $notification->toNotifyPush($notifiable);

            if (!$message instanceof NotifyPush) {
                return null;
            }

            if (!$message->token && $to = $notifiable->routeNotificationFor('push', $notification)) {
                $message->token($to);
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'push'));

            $data = $message->toArray();

            if($data['webhook_url'] === null){
                $data['webhook_url'] = config('notify.webhook');
            }

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/push", $data);

            if ($response->failed()) {
                throw new Exception('Error sending notification: ' . $response->body());
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'push'));

            logglyInfo()->performedOn(self::class)
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification, 'response' => $responseJson])->log("Notification sent");

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'push'));

            logglyError()
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification])
                ->exception($exception)->log("Error by sending notification");

            report($exception);

            return null;
        }
    }
}
