<?php

namespace RiseTechApps\Notify\Message;

class NotifyTeams
{
    protected string $message = '';
    protected ?string $title = null;
    protected ?string $color = null;
    protected array $facts = [];
    protected array $actions = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Cor do tema em hex sem #.
     * Exemplo: ->color('2ecc71') para verde.
     */
    public function color(string $color): static
    {
        $this->color = ltrim($color, '#');

        return $this;
    }

    /**
     * Adiciona um fact (par chave/valor) ao card.
     * Exemplo: ->fact('Pedido', '#1234')
     */
    public function fact(string $label, string $value): static
    {
        $this->facts[] = ['label' => $label, 'value' => $value];

        return $this;
    }

    /**
     * Define todos os facts de uma vez.
     */
    public function facts(array $facts): static
    {
        $this->facts = $facts;

        return $this;
    }

    /**
     * Adiciona um botão de ação ao card.
     * Exemplo: ->action('Ver pedido', 'https://app.com/pedidos/1')
     */
    public function action(string $label, string $url): static
    {
        $this->actions[] = ['label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * Define todas as ações de uma vez.
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;

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
            'title'       => $this->title,
            'color'       => $this->color,
            'facts'       => count($this->facts) > 0 ? $this->facts : null,
            'actions'     => count($this->actions) > 0 ? $this->actions : null,
            'config_id'   => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
