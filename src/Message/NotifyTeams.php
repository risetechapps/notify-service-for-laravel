<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de Microsoft Teams enviada ao servidor via /api/v1/send/teams.
 *
 * Monta um card (título, subtítulo, cor, imagem, facts e botões de ação) — ou um card
 * cru (MessageCard/Adaptive Card) via ->card(). O servidor enfileira (resposta 202 com
 * notification_id) e devolve status pelo webhook.
 *
 * Destino (precedência): ->teamsWebhookUrl() (URL direta) > ->channel() (alias) >
 * webhook padrão da config.
 *
 * - config_id: se não definido, usa o default de config('notify.teams.config_id').
 * - tag: as tags da mensagem são mescladas com as default de config('notify.teams.tags').
 *
 *   (new NotifyTeams)
 *       ->channel('equipe')
 *       ->title('Relatório semanal')
 *       ->message('O relatório de vendas está pronto.')
 *       ->color('0078D4')
 *       ->fact('Período', 'Mar/2026')
 *       ->action('Ver relatório', 'https://app.com/reports')
 *       ->tag('relatorios');
 */
class NotifyTeams
{
    protected ?string $channel = null;
    protected ?string $teamsWebhookUrl = null;
    protected string $message = '';
    protected ?string $title = null;
    protected ?string $subtitle = null;
    protected ?string $color = null;
    protected ?string $imageUrl = null;
    protected array $facts = [];
    protected array $actions = [];
    protected ?array $card = null;
    protected array $tags = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    /**
     * Alias do canal (resolve para a webhook URL no servidor). Se não for um alias
     * cadastrado, usa o webhook padrão/direto. Não confundir com ->teamsWebhookUrl().
     */
    public function channel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Webhook URL direta de destino (override). Tem prioridade sobre o alias e o
     * padrão da config. Não confundir com ->webhookUrl() (callback de status).
     */
    public function teamsWebhookUrl(string $url): static
    {
        $this->teamsWebhookUrl = $url;

        return $this;
    }

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

    /** Subtítulo do card. Máx 255 chars. */
    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /** Imagem do card (URL). */
    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

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
     * Card cru (MessageCard / Adaptive Card) — passthrough total. Quando definido,
     * o servidor envia este card como está; title/facts/actions/etc. do atalho são
     * ignorados.
     */
    public function card(array $card): static
    {
        $this->card = $card;

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
            'channel'           => $this->channel,
            'teams_webhook_url' => $this->teamsWebhookUrl,
            'message'           => $this->message ?: null,
            'title'             => $this->title,
            'subtitle'          => $this->subtitle,
            'color'             => $this->color,
            'image_url'         => $this->imageUrl,
            'facts'             => $this->facts ?: null,
            'actions'           => $this->actions ?: null,
            'card'              => $this->card,
            'tag'               => $tags ?: null,
            'config_id'         => $this->configId ?: config('notify.teams.config_id'),
            'webhook_url'       => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
