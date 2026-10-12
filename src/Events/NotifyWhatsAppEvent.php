<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Acontecimento do WhatsApp vindo do servidor, pelo webhook do chat
 * (POST {prefix}/webhook/whatsapp) ou pelo comando notify:whatsapp-events
 * (GET /chat/events). O mesmo event_id é entregue uma vez só (dispatchOnce),
 * mesmo usando os dois caminhos.
 *
 * $event: whatsapp.message.received, whatsapp.message.sent, whatsapp.message.failed,
 * whatsapp.message.status, whatsapp.message.updated, whatsapp.conversation.opened,
 * whatsapp.conversation.updated, whatsapp.conversation.closed — e, pelos avisos
 * de conexão, whatsapp.disconnected, whatsapp.reconnected e whatsapp.paused.
 *
 *   public function handle(NotifyWhatsAppEvent $event): void
 *   {
 *       if ($event->is('message.received')) {
 *           $texto = $event->message()['body'];
 *           $telefone = $event->conversation()['contact']['phone'];
 *       }
 *   }
 */
class NotifyWhatsAppEvent
{
    use Dispatchable;

    public function __construct(
        public string $event,
        public ?int $eventId,
        public array $payload = [],
    ) {}

    /** Tipo sem o prefixo "whatsapp." (ex.: message.received). */
    public function type(): string
    {
        return str_starts_with($this->event, 'whatsapp.') ? substr($this->event, 9) : $this->event;
    }

    /** Compara o tipo, com ou sem o prefixo (aceita vários). */
    public function is(string ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->type() === (str_starts_with($type, 'whatsapp.') ? substr($type, 9) : $type)) {
                return true;
            }
        }

        return false;
    }

    public function conversation(): ?array
    {
        return $this->payload['conversation'] ?? null;
    }

    public function message(): ?array
    {
        return $this->payload['message'] ?? null;
    }

    /** O que mudou (message.updated e conversation.updated). */
    public function changes(): ?array
    {
        return $this->payload['changes'] ?? null;
    }

    /**
     * Dispara uma vez por event_id (24 h de memória no cache). Sem event_id
     * (ex.: avisos de conexão), dispara sempre.
     */
    public static function dispatchOnce(string $event, ?int $eventId, array $payload): bool
    {
        if ($eventId !== null && ! Cache::add("notify:whatsapp-event:{$eventId}", true, now()->addDay())) {
            return false;
        }

        static::dispatch($event, $eventId, $payload);

        return true;
    }
}
