<?php

namespace RiseTechApps\Notify\Message;

class NotifyTelegram
{
    protected string $chatId = '';
    protected string $message = '';
    protected string $parseMode = 'Markdown';
    protected ?string $imageUrl = null;
    protected array $buttons = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function chatId(string $chatId): static
    {
        if (blank($this->chatId)) {
            $this->chatId = $chatId;
        }

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function parseMode(string $mode): static
    {
        $this->parseMode = $mode;

        return $this;
    }

    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    /**
     * Adiciona um botão inline.
     * Exemplo: ->button('Ver pedido', 'https://app.com/pedidos/1')
     */
    public function button(string $text, string $url): static
    {
        $this->buttons[][] = ['text' => $text, 'url' => $url];

        return $this;
    }

    /**
     * Define a grade completa de botões inline.
     * Cada elemento do array externo é uma linha; cada elemento interno é um botão.
     * Exemplo: [[ ['text' => 'Sim', 'url' => '...'], ['text' => 'Não', 'url' => '...'] ]]
     */
    public function buttons(array $buttons): static
    {
        $this->buttons = $buttons;

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
            'chat_id'     => $this->chatId,
            'message'     => $this->message,
            'parse_mode'  => $this->parseMode,
            'image_url'   => $this->imageUrl,
            'buttons'     => count($this->buttons) > 0 ? $this->buttons : null,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
