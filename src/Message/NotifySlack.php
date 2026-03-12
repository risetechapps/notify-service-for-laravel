<?php

namespace RiseTechApps\Notify\Message;

class NotifySlack
{
    protected string $message = '';
    protected ?string $channel = null;
    protected ?string $title = null;
    protected ?string $color = null;
    protected array $fields = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function channel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Adiciona um campo ao attachment.
     * Exemplo: ->field('Status', 'Aprovado')
     */
    public function field(string $label, string $value): static
    {
        $this->fields[] = ['label' => $label, 'value' => $value];

        return $this;
    }

    /**
     * Define todos os campos de uma vez.
     * Exemplo: ->fields([['label' => 'Status', 'value' => 'Aprovado']])
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

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
            'message'     => $this->message,
            'channel'     => $this->channel,
            'title'       => $this->title,
            'color'       => $this->color,
            'fields'      => count($this->fields) > 0 ? $this->fields : null,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
