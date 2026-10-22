<?php

namespace RiseTechApps\Notify\Channel;

use Closure;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Exceptions\NotifyDeliveryException;
use RiseTechApps\Notify\Notify;

abstract class NotifyChannel
{
    protected string $apiUrl;

    protected ?string $apiKey = null;

    public function __construct()
    {
        $this->apiUrl = Notify::BASE_URL;
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
     * Identifica o envio no log sem gravar os objetos: o notifiable inteiro
     * (um User, por exemplo) levava e-mail, telefone e o resto dos atributos
     * para o log a cada falha.
     */
    protected function logContext($notifiable, Notification $notification, string $channel): array
    {
        return [
            'channel' => $channel,
            'notification' => $notification::class,
            'notification_id' => $notification->id ?? null,
            'notifiable' => is_object($notifiable) ? $notifiable::class : gettype($notifiable),
            'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
        ];
    }

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
     * Devolve o JSON do servidor, o corpo do erro, ou ['error' => ...]. Numa
     * notificação com fila, falha passageira (conexão, 5xx, 429, 409) lança
     * NotifyDeliveryException para a fila tentar de novo (ver shouldThrow()).
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
                ...$this->idempotencyHeader($notification, $channel, $data),
                ...(method_exists($message, 'requestHeaders') ? $message->requestHeaders() : []),
            ];

            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/{$channel}", $data);
        } catch (ConnectionException $exception) {
            $this->failed($notifiable, $notification, $channel, $exception, ['exception' => $exception]);

            if ($this->shouldThrow($notification)) {
                throw new NotifyDeliveryException($channel, 0, previous: $exception);
            }

            report($exception);

            return ['error' => $exception->getMessage()];
        } catch (Exception $exception) {
            $this->failed($notifiable, $notification, $channel, $exception, ['exception' => $exception]);

            report($exception);

            return ['error' => $exception->getMessage()];
        }

        if ($response->failed()) {
            $errorBody = $response->json() ?: ['error' => $response->body()];

            $this->failed($notifiable, $notification, $channel, new Exception($response->body()), [
                'http' => $response->status(),
                'response' => $errorBody,
            ]);

            if ($this->shouldThrow($notification) && NotifyDeliveryException::isRetryable($response->status())) {
                throw new NotifyDeliveryException($channel, $response->status(), (array) $errorBody);
            }

            return $errorBody;
        }

        $responseJson = $response->json();

        Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, $channel));

        return $responseJson;
    }

    /**
     * Só a notificação com fila lança: lá a exceção vira nova tentativa. No
     * envio síncrono não há quem tente de novo, e a exceção só derrubaria a
     * requisição de quem chamou; o canal devolve o erro, como sempre fez.
     */
    protected function shouldThrow(Notification $notification): bool
    {
        return $notification instanceof ShouldQueue && config('notify.retry_failures', true);
    }

    /**
     * Idempotency-Key automática: a mesma notificação, no mesmo canal e com o
     * mesmo corpo, gera a mesma chave. Assim a nova tentativa da fila, depois
     * de um timeout em que o servidor já tinha aceitado, recebe a resposta
     * original em vez de enviar de novo. O corpo entra na chave porque o
     * servidor recusa (422) a mesma chave com outro corpo, o que aconteceria
     * com uma notificação enviada a vários notifiables. A ->idempotencyKey()
     * da mensagem, quando há, vence esta.
     */
    protected function idempotencyHeader(Notification $notification, string $channel, array $data): array
    {
        if (empty($notification->id)) {
            return [];
        }

        return ['Idempotency-Key' => 'notify:'.hash('sha256', $notification->id.'|'.$channel.'|'.json_encode($data))];
    }

    protected function failed($notifiable, Notification $notification, string $channel, Exception $exception, array $context): void
    {
        Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, $channel));

        Log::error('Error by sending notification', [
            ...$this->logContext($notifiable, $notification, $channel),
            ...$context,
        ]);
    }
}
