<?php

namespace RiseTechApps\Notify\Message;

class NotifyWebSocket
{
    protected string $channel = '';
    protected string $event = '';
    protected array $data = [];
    protected bool $private = false;
    protected bool $presence = false;
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

    public function configId(string $configId): static
    {
        $this->configId = $configId;

        return $this;
    }

    public function webhookUrl(string $url): static
    {
        $this->webhookUrl = $url;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'channel'     => $this->channel,
            'event'       => $this->event,
            'data'        => $this->data,
            'private'     => $this->private ?: null,
            'presence'    => $this->presence ?: null,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
