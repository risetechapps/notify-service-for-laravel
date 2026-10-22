<?php

namespace RiseTechApps\Notify\Server;

/** Histórico das entregas de webhook que o servidor fez para você, com reenvio. */
class WebhookDeliveries extends Resource
{
    /** Filtros: success (bool), event, notification_id, per_page, page. */
    public function all(array $filters = []): array
    {
        return $this->result($this->http()->get('/api/v1/webhook-deliveries', $filters));
    }

    public function get(string $id): array
    {
        return $this->result($this->http()->get("/api/v1/webhook-deliveries/{$id}"));
    }

    /** Reenvia (com assinatura nova). */
    public function resend(string $id): array
    {
        return $this->result($this->http()->post("/api/v1/webhook-deliveries/{$id}/resend"));
    }
}
