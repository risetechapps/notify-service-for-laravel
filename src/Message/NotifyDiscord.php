<?php

namespace RiseTechApps\Notify\Message;

class NotifyDiscord
{
    protected string $message = '';
    protected ?string $username = null;
    protected ?string $title = null;
    protected ?int $color = null;
    protected array $fields = [];
    protected ?string $imageUrl = null;
    protected ?string $thumbnail = null;
    protected ?string $footer = null;
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function username(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Cor do embed em decimal.
     * Dica: ->color(0x2ecc71) para verde, ->color(0xe74c3c) para vermelho.
     */
    public function color(int $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Adiciona um campo ao embed.
     * Exemplo: ->field('Pedido', '#1234')
     */
    public function field(string $label, string $value, bool $inline = true): static
    {
        $this->fields[] = ['label' => $label, 'value' => $value, 'inline' => $inline];

        return $this;
    }

    /**
     * Define todos os campos de uma vez.
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function thumbnail(string $url): static
    {
        $this->thumbnail = $url;

        return $this;
    }

    public function footer(string $footer): static
    {
        $this->footer = $footer;

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
            'username'    => $this->username,
            'title'       => $this->title,
            'color'       => $this->color,
            'fields'      => count($this->fields) > 0 ? $this->fields : null,
            'image_url'   => $this->imageUrl,
            'thumbnail'   => $this->thumbnail,
            'footer'      => $this->footer,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
