<?php

namespace RiseTechApps\Notify\Message;

class NotifyPush
{
    public string|array $token = '';
    protected string $topic = '';
    protected string $title = '';
    protected string $body = '';
    protected ?string $imageUrl = null;
    protected array $data = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function token(string|array $token): static
    {
        if (blank($this->token)) {
            $this->token = $token;
        }

        return $this;
    }

    public function topic(string $topic): static
    {
        $this->topic = $topic;

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

    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function data(array $data): static
    {
        $this->data = $data;

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
            'token'       => $this->token ?: null,
            'topic'       => $this->topic ?: null,
            'title'       => $this->title,
            'body'        => $this->body,
            'image_url'   => $this->imageUrl,
            'data'        => count($this->data) > 0 ? $this->data : null,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
