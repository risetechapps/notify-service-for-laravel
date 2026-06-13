<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de Microsoft Teams enviada ao servidor via /api/v1/send/teams.
 *
 * Monta um card (título, cor do tema, facts e botões de ação). O servidor enfileira
 * (resposta 202 com notification_id) e devolve status pelo webhook.
 *
 * - config_id: se não definido, usa o default de config('notify.teams.config_id').
 * - tag: as tags da mensagem são mescladas com as default de config('notify.teams.tags').
 *
 *   (new NotifyTeams)
 *       ->title('Relatório semanal')
 *       ->message('O relatório de vendas está pronto.')
 *       ->color('0078D4')
 *       ->fact('Período', 'Mar/2026')
 *       ->action('Ver relatório', 'https://app.com/reports')
 *       ->tag('relatorios');
 */
class NotifyTeams
{
    protected string $message = '';
    protected ?string $title = null;
    protected ?string $color = null;
    protected array $facts = [];
    protected array $actions = [];
    protected array $tags = [];
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
     * de config('notify.teams.config_id').
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
            (array) config('notify.teams.tags', []),
            $this->tags
        )));

        return array_filter([
            'message'     => $this->message ?: null,
            'title'       => $this->title,
            'color'       => $this->color,
            'facts'       => $this->facts ?: null,
            'actions'     => $this->actions ?: null,
            'tag'         => $tags ?: null,
            'config_id'   => $this->configId ?: config('notify.teams.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
