<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

abstract class NotifyChannel
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = "https://notifykit.app.br";
        $this->apiKey = config('notify.key', '');
    }

    abstract public function send($notifiable, Notification $notification);

    /**
     * Tipo do notifiable para o NotifyLog.
     * Envios on-demand (Notification::route()) usam AnonymousNotifiable — sem model/chave.
     */
    protected function notifiableType($notifiable): string
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return 'anonymous';
        }

        return get_class($notifiable);
    }

    /**
     * Chave do notifiable, ou null quando ele não é um model Eloquent
     * (ex.: AnonymousNotifiable de um envio on-demand).
     */
    protected function notifiableId($notifiable): ?string
    {
        if (method_exists($notifiable, 'getKey')) {
            $key = $notifiable->getKey();

            return is_null($key) ? null : (string) $key;
        }

        return null;
    }
}
