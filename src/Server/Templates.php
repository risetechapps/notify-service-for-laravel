<?php

namespace RiseTechApps\Notify\Server;

/**
 * Modelos de mensagem salvos (e-mail, SMS e WhatsApp). Use pelo nome ou id
 * nos envios (->template('Pedido enviado', [...])) e nas campanhas.
 */
class Templates extends Resource
{
    public function all(?string $channel = null): array
    {
        return $this->result($this->http()->get('/api/v1/templates', self::filled(['channel' => $channel])));
    }

    public function get(string $id): array
    {
        return $this->result($this->http()->get("/api/v1/templates/{$id}"));
    }

    /** $channel: email | sms | whatsapp. $subject só no e-mail. */
    public function create(string $channel, string $name, string $body, ?string $subject = null): array
    {
        return $this->result($this->http()->post('/api/v1/templates', self::filled(compact('channel', 'name', 'body', 'subject'))));
    }

    public function update(string $id, array $fields): array
    {
        return $this->result($this->http()->put("/api/v1/templates/{$id}", $fields));
    }

    public function delete(string $id): array
    {
        return $this->result($this->http()->delete("/api/v1/templates/{$id}"));
    }
}
