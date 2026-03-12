<?php

namespace RiseTechApps\Notify\Message;

class NotifyApns
{
    public string|array $token = '';
    protected string $title = '';
    protected string $body = '';
    protected ?string $subtitle = null;
    protected ?int $badge = null;
    protected string $sound = 'default';
    protected array $data = [];
    protected ?string $category = null;
    protected ?string $threadId = null;
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function token(string|array $token): static
    {
        if (blank($this->token)) {
            $this->token = $token;
        }

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function badge(int $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function sound(string $sound): static
    {
        $this->sound = $sound;

        return $this;
    }

    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function threadId(string $threadId): static
    {
        $this->threadId = $threadId;

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
            'token'       => $this->token,
            'title'       => $this->title,
            'body'        => $this->body,
            'subtitle'    => $this->subtitle,
            'badge'       => $this->badge,
            'sound'       => $this->sound !== 'default' ? $this->sound : null,
            'data'        => count($this->data) > 0 ? $this->data : null,
            'category'    => $this->category,
            'thread_id'   => $this->threadId,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
