<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o servidor envia um callback de status de uma notificação
 * individual para o webhook do pacote.
 *
 * O pacote não persiste nada localmente — escute este evento para reagir
 * (atualizar seu próprio banco, capturar o provider_id, etc.).
 *
 * Exemplo de listener:
 *
 *   public function handle(NotifyWebhookEvent $event): void
 *   {
 *       match ($event->status) {
 *           'delivered' => /* ... *\/,
 *           'error'     => /* ... *\/,
 *           default     => null,
 *       };
 *
 *       // provider_id = id da mensagem no provedor (ex.: message_id do Telegram)
 *       $messageId = $event->providerId;
 *   }
 */
class NotifyWebhookEvent
{
    use Dispatchable;

    public function __construct(
        public ?string $event,
        public ?string $notificationId,
        public ?string $providerId,
        public ?string $status,
        public ?string $type,
        public array $payload = [],
        public bool $test = false,
    ) {}

    /**
     * Nome do evento sem o prefixo `notification.` (sent, failed, delivered…).
     * O servidor passou a mandar `notification.sent`; o servidor 1.0.0, `sent`.
     */
    public function name(): ?string
    {
        return $this->event === null ? null : preg_replace('/^notification\./', '', $this->event);
    }

    /** Compara o evento com ou sem o prefixo: is('sent') e is('notification.sent'). */
    public function is(string ...$events): bool
    {
        $name = $this->name();

        foreach ($events as $event) {
            if ($name !== null && $name === preg_replace('/^notification\./', '', $event)) {
                return true;
            }
        }

        return false;
    }
}
