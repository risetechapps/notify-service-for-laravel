<?php

namespace RiseTechApps\Notify\Message;

/**
 * Evento WebSocket (tempo real) enviado ao servidor via /api/v1/send/websocket.
 *
 * Dispara um evento em um canal público, privado ou de presença. O servidor enfileira
 * (resposta 202 com notification_id) e devolve status pelo webhook.
 *
 * - config_id: se não definido, usa o default de config('notify.websocket.config_id').
 * - tag: as tags da mensagem são mescladas com as default de config('notify.websocket.tags').
 *
 *   (new NotifyWebSocket)
 *       ->channel("private-user.{$id}")
 *       ->event('OrderStatusUpdated')
 *       ->data(['order_id' => 1234, 'status' => 'shipped'])
 *       ->private()
 *       ->tag('pedidos');
 */
class NotifyWebSocket
{
    protected string $channel = '';
    protected string $event = '';
    protected array $data = [];
    protected bool $private = false;
    protected bool $presence = false;
    protected array $tags = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function channel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function event(string $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Marca o canal como privado (prefixo "private-" aplicado automaticamente pelo servidor).
     */
    public function private(bool $private = true): static
    {
        $this->private = $private;

        return $this;
    }

    /**
     * Marca o canal como presence (prefixo "presence-" aplicado automaticamente pelo servidor).
     */
    public function presence(bool $presence = true): static
    {
        $this->presence = $presence;

        return $this;
    }

    /**
     * Adiciona tag(s) para agrupar/filtrar. Aceita string ou array; acumula
     * entre chamadas e é mesclada com as tags default do config.
     */
    public function tag(string|array $tag): static
    {
        $this->tags = array_values(array_unique(array_merge($this->tags, (array) $tag)));

        return $this;
    }

    /**
     * Credencial específica (UUID ou label). Sobrescreve o default
     * de config('notify.websocket.config_id').
     */
    public function configId(string $configId): static
    {
        $this->configId = $configId;

        return $this;
    }

    /** URL de callback de status (sobrescreve o webhook global do config). */
    public function webhookUrl(string $url): static
    {
        $this->webhookUrl = $url;

        return $this;
    }

    public function toArray(): array
    {
        $tags = array_values(array_unique(array_merge(
            (array) config('notify.websocket.tags', []),
            $this->tags
        )));

        return array_filter([
            'channel'     => $this->channel ?: null,
            'event'       => $this->event ?: null,
            'data'        => $this->data ?: null,
            'private'     => $this->private ?: null,
            'presence'    => $this->presence ?: null,
            'tag'         => $tags ?: null,
            'config_id'   => $this->configId ?: config('notify.websocket.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
