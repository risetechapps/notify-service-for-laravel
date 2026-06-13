<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o servidor envia um callback de progresso/status de uma
 * campanha para o webhook do pacote.
 *
 * O pacote não persiste nada localmente — escute este evento para reagir.
 * O payload completo (total, sent, failed, contact_updates, etc.) fica em $payload.
 */
class NotifyCampaignWebhookEvent
{
    use Dispatchable;

    public function __construct(
        public ?string $campaignId,
        public ?string $status,
        public array $payload = [],
    ) {}
}
