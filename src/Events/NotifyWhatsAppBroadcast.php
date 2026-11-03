<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Retransmite um NotifyWhatsAppEvent para o front-end pelo Broadcasting do
 * Laravel (notify.broadcast). Assim os atendentes recebem as novidades por
 * WebSocket (Reverb, Pusher…) e o front não consulta o servidor em laço.
 *
 * Canais (privados; autorize em routes/channels.php):
 *   {channel}                       todos os eventos da conta
 *   {channel}.{configuration_id}    só os de um número
 *
 * Nome do evento no front: o tipo sem "whatsapp." (ex.: "message.received").
 * Com Laravel Echo: .listen('.message.received', e => …). O conteúdo é o mesmo
 * do webhook: event, event_id, occurred_at, conversation, message, changes.
 */
class NotifyWhatsAppBroadcast implements ShouldBroadcast
{
    /** Fila do broadcasting (null = a padrão; 'sync' transmite na hora). */
    public ?string $connection = null;

    public ?string $queue = null;

    public function __construct(public NotifyWhatsAppEvent $source)
    {
        $this->connection = config('notify.broadcast.connection') ?: null;
        $this->queue = config('notify.broadcast.queue') ?: null;
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        $channel = (string) config('notify.broadcast.channel', 'notify.whatsapp');
        $channels = [new PrivateChannel($channel)];

        if ($configurationId = self::configurationId($this->source)) {
            $channels[] = new PrivateChannel("{$channel}.{$configurationId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->source->type();
    }

    public function broadcastWith(): array
    {
        return [
            ...$this->source->payload,
            'event' => $this->source->event,
            'event_id' => $this->source->eventId,
        ];
    }

    /** Transmite o evento se notify.broadcast estiver ligado e o tipo for dos escolhidos. */
    public static function relay(NotifyWhatsAppEvent $event): void
    {
        if (! config('notify.broadcast.enabled', false)) {
            return;
        }

        $only = array_map(
            fn (string $type) => str_starts_with($type, 'whatsapp.') ? substr($type, 9) : $type,
            (array) config('notify.broadcast.events', []),
        );

        if ($only !== [] && ! in_array($event->type(), $only, true)) {
            return;
        }

        broadcast(new static($event));
    }

    public static function configurationId(NotifyWhatsAppEvent $event): ?string
    {
        $id = $event->payload['configuration_id'] ?? $event->conversation()['configuration_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
