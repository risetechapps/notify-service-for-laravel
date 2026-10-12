<?php

namespace RiseTechApps\Notify\Channel;

use Closure;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;

abstract class NotifyChannel
{
    protected string $apiUrl;
    protected ?string $apiKey = null;

    public function __construct()
    {
        $this->apiUrl = \RiseTechApps\Notify\Notify::BASE_URL;
    }

    protected function apiKey(): string
    {
        if ($this->apiKey === null) {
            $this->apiKey = config('notify.key', '');
        }

        return $this->apiKey;
    }

    abstract public function send($notifiable, Notification $notification);

    /**
     * Envio comum a todos os canais: monta a mensagem ($build devolve a
     * mensagem pronta, ou null para não enviar), dispara os eventos
     * NotifySending/Sent/Failed e faz o POST em /api/v1/send/{$channel}.
     *
     * O corpo é o toArray() da mensagem mais as opções de envio
     * (HasSendOptions, HasTemplate) e o webhook padrão do config; os
     * cabeçalhos levam a chave de API mais Idempotency-Key / X-Notify-Test.
     *
     * $adjust (opcional) recebe o corpo montado e devolve o corpo final.
     *
     * Devolve o JSON do servidor, o corpo do erro, ou ['error' => ...].
     */
    protected function deliver($notifiable, Notification $notification, string $channel, Closure $build, ?Closure $adjust = null): mixed
    {
        try {
            $message = $build();

            if ($message === null) {
                return null;
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, $channel));

            $data = array_merge(
                $message->toArray(),
                method_exists($message, 'templateOptions') ? $message->templateOptions() : [],
                method_exists($message, 'sendOptions') ? $message->sendOptions() : [],
            );

            // Ajuste do canal no corpo já montado (ex.: Slack completa o canal pelo notifiable).
            if ($adjust) {
                $data = $adjust($data);
            }

            if (($data['webhook_url'] ?? null) === null) {
                $data['webhook_url'] = config('notify.webhook');
            }

            $headers = [
                'X-API-KEY' => $this->apiKey(),
                ...(method_exists($message, 'requestHeaders') ? $message->requestHeaders() : []),
            ];

            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/{$channel}", $data);

            if ($response->failed()) {
                $errorBody = $response->json() ?: ['error' => $response->body()];

                Event::dispatch(new NotifyFailedEvent($notifiable, $notification, new Exception($response->body()), $channel));

                Log::error('Error by sending notification', [
                    'notifiable' => $notifiable,
                    'notification' => $notification,
                    'response' => $errorBody,
                ]);

                return $errorBody;
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, $channel));

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, $channel));

            Log::error('Error by sending notification', [
                'notifiable' => $notifiable,
                'notification' => $notification,
                'exception' => $exception,
            ]);

            report($exception);

            return ['error' => $exception->getMessage()];
        }
    }
}
