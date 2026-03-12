<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyDiscord;
use RiseTechApps\Notify\Models\NotifyLog;

class NotifyChannelDiscord extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        $log = null;

        try {
            $message = $notification->toNotifyDiscord($notifiable);

            if (!$message instanceof NotifyDiscord) {
                return null;
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'discord'));

            $data = $message->toArray();

            if (!($data['webhook_url'] ?? null)) {
                $data['webhook_url'] = config('notify.webhook');
            }

            $log = NotifyLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id'   => $notifiable->getKey(),
                'channel'         => 'discord',
                'status'          => 'sending',
                'payload'         => $data,
            ]);

            $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/discord", $data);

            if ($response->failed()) {
                throw new Exception('Error sending notification: ' . $response->body());
            }

            $responseJson = $response->json();

            $log->markAsSent($responseJson['notification_id'] ?? '', $responseJson);

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'discord'));

            logglyInfo()->performedOn(self::class)
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification, 'response' => $responseJson])
                ->log("Notification sent");

            return $responseJson;
        } catch (\Exception $exception) {
            if ($log) {
                $log->markAsFailed($exception->getMessage());
            }

            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'discord'));

            logglyError()
                ->withProperties(['notifiable' => $notifiable, 'notification' => $notification])
                ->exception($exception)->log("Error by sending notification");

            report($exception);

            return null;
        }
    }
}
