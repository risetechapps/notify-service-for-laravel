<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o servidor envia um callback de progresso/status de uma
 * campanha para o webhook do pacote.
 *
 * O pacote não persiste nada localmente — escute este evento para reagir.
 * $event traz o nome (campaign.created, campaign.started, campaign.completed,
 * campaign.paused…). O payload completo (total, sent, failed, progress e, no
 * completed, skipped, started_at e finished_at) fica em $payload.
 */
class NotifyCampaignWebhookEvent
{
    use Dispatchable;

    public function __construct(
        public ?string $campaignId,
        public ?string $status,
        public array $payload = [],
        public ?string $event = null,
    ) {}
}
