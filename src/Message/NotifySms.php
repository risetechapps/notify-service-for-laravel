<?php

namespace RiseTechApps\Notify\Message;

class NotifySms
{
    protected string $content = "";
    protected string $to = "";
    protected string $from = "";
    protected ?string $webhookUrl = null;

    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function to(string $to): static
    {
        if (blank($this->to)) {
            $this->to = $to;
        }
        return $this;
    }

    public function from(string $from): static
    {
        if (blank($this->from)) {
            $this->from = $from;
        }
        return $this;
    }

    public function webhookUrl(string $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'to' => $this->to,
            'from' => $this->from,
            'webhook_url' => $this->webhookUrl,
        ];
    }
}
