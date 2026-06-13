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
    ) {}
}
