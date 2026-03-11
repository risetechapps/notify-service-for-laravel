<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifySms;

class NotifyChannelSms extends NotifyChannel
{

    /**
     * @throws Exception
     */
    public function send($notifiable, Notification $notification)
    {

        try {
            if (!$to = $notifiable->routeNotificationFor('sms', $notification)) {
                return null;
            }

            $message = $notification->toNotifySms($notifiable);

            if (!$message instanceof NotifySms) {
                return null;
            }

            $message->to($to);
            $message->from(config('app.name'));


            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'sms'));

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
            ])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/sms", $message->toArray());

            if ($response->failed()) {
                throw new Exception('Error sending notification: ' . $response->body());
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'sms'));

            logglyInfo()->performedOn(self::class)
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification, 'response' => $responseJson])->log("Notification sent");

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'sms'));

            logglyError()
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification])
                ->exception($exception)->log("Error by sending notification");

            report($exception);

            return null;
        }
    }

}
