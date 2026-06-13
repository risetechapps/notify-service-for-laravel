<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifySlack;

class NotifyChannelSlack extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            $message = $notification->toNotifySlack($notifiable);

            if (!$message instanceof NotifySlack) {
                return null;
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'slack'));

            $data = $message->toArray();

            // Só usa o canal do Notifiable quando a mensagem não trouxe alvo
            // (nem channel explícito nem Incoming Webhook).
            if (empty($data['channel']) && empty($data['slack_webhook_url'])
                && $channel = $notifiable->routeNotificationFor('slack', $notification)) {
                $data['channel'] = $channel;
            }

            if (($data['webhook_url'] ?? null) === null) {
                $data['webhook_url'] = config('notify.webhook');
            }

            $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/slack", $data);

            if ($response->failed()) {
                throw new Exception('Error sending notification: ' . $response->body());
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'slack'));

            logglyInfo()->performedOn(self::class)
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification, 'response' => $responseJson])
                ->log("Notification sent");

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'slack'));

            logglyError()
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification])
                ->exception($exception)->log("Error by sending notification");

            report($exception);

            return null;
        }
    }
}
