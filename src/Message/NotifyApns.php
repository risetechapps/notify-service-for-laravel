<?php

namespace RiseTechApps\Notify\Message;

use RiseTechApps\Notify\Message\Concerns\HasSendOptions;

/**
 * Mensagem de push APNs (Apple) enviada ao servidor via /api/v1/send/apns.
 *
 * Aceita token único ou lista de tokens. O servidor enfileira (resposta 202 com
 * notification_id) e devolve status pelo webhook.
 *
 * - config_id: se não for definido na mensagem, usa o default de config('notify.apns.config_id').
 *   Definir ->configId() na notificação sobrescreve o default na hora do envio.
 * - tag: as tags da mensagem são mescladas com as default de config('notify.apns.tags').
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Exemplos:
 * ──────────────────────────────────────────────────────────────────────────────
 *
 *   // Simples
 *   (new NotifyApns)->token($apns)->title('Olá!')->body('Nova mensagem.');
 *
 *   // Completo
 *   (new NotifyApns)
 *       ->token([$token1, $token2])
 *       ->title('Pedido a caminho 🚚')
 *       ->subtitle('Pedido #123')
 *       ->body('Seu pedido saiu para entrega.')
 *       ->badge(1)
 *       ->sound('default')
 *       ->category('ORDER')
 *       ->threadId('pedidos')
 *       ->tag(['entregas', 'transacional'])
 *       ->data(['order_id' => '123']);
 */
class NotifyApns
{
    use HasSendOptions;

    public string|array $token = '';

    protected ?string $title = null;

    protected ?string $body = null;

    protected ?string $subtitle = null;

    protected ?int $badge = null;

    protected string|array|null $sound = null;

    protected array $data = [];

    protected ?string $category = null;

    protected ?string $threadId = null;

    protected ?string $image = null;

    protected ?string $interruptionLevel = null;

    protected ?float $relevanceScore = null;

    protected ?int $ttl = null;

    protected ?string $environment = null;

    protected ?string $collapseId = null;

    protected ?string $topic = null;

    protected ?int $priority = null;

    protected ?bool $contentAvailable = null;

    protected ?bool $mutableContent = null;

    protected array $tags = [];

    protected ?string $configId = null;

    protected ?string $webhookUrl = null;

    /** Device token(s) Apple. Write-once: o primeiro valor não-vazio prevalece. */
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

    /** Número no badge do ícone do app (0 limpa o badge). */
    public function badge(int $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /** Nome do arquivo de som (ex.: 'default'). */
    public function sound(string $sound): static
    {
        $this->sound = $sound;

        return $this;
    }

    /** Payload customizado entregue ao app. */
    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /** Categoria para action buttons no iOS. */
    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    /** Agrupa notificações relacionadas no device. */
    public function threadId(string $threadId): static
    {
        $this->threadId = $threadId;

        return $this;
    }

    /**
     * Som crítico (toca mesmo no silencioso; exige o entitlement da Apple).
     * Volume de 0 a 1.
     */
    public function criticalSound(string $name = 'default', float $volume = 1.0): static
    {
        $this->sound = ['name' => $name, 'critical' => true, 'volume' => $volume];

        return $this;
    }

    /** Imagem (URL), lida pela Notification Service Extension do app. Liga o mutable-content. */
    public function image(string $url): static
    {
        $this->image = $url;

        return $this;
    }

    /** passive, active, time-sensitive ou critical. */
    public function interruptionLevel(string $level): static
    {
        $this->interruptionLevel = $level;

        return $this;
    }

    /** De 0 a 1: ordem no resumo de notificações. */
    public function relevanceScore(float $score): static
    {
        $this->relevanceScore = $score;

        return $this;
    }

    /** Segundos que a Apple guarda o push com o aparelho desligado (até 30 dias). */
    public function ttl(int $seconds): static
    {
        $this->ttl = $seconds;

        return $this;
    }

    /** sandbox ou production: escolhe o servidor da Apple neste envio. */
    public function environment(string $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    /** Push com o mesmo collapse_id substitui o anterior no aparelho (até 64). */
    public function collapseId(string $collapseId): static
    {
        $this->collapseId = $collapseId;

        return $this;
    }

    /** Bundle ID, no lugar do da configuração. */
    public function topic(string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    /** 10 (imediato) ou 5 (economiza bateria). */
    public function priority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /** Push silencioso (sem title e body). */
    public function contentAvailable(bool $contentAvailable = true): static
    {
        $this->contentAvailable = $contentAvailable;

        return $this;
    }

    public function mutableContent(bool $mutableContent = true): static
    {
        $this->mutableContent = $mutableContent;

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
     * de config('notify.apns.config_id').
     */
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
        $tags = array_values(array_unique(array_merge(
            (array) config('notify.apns.tags', []),
            $this->tags
        )));

        return array_filter([
            'token' => $this->token ?: null,
            'title' => $this->title,
            'body' => $this->body,
            'subtitle' => $this->subtitle,
            'badge' => $this->badge,
            'sound' => $this->sound,
            'data' => $this->data ?: null,
            'category' => $this->category,
            'thread_id' => $this->threadId,
            'image' => $this->image,
            'interruption_level' => $this->interruptionLevel,
            'relevance_score' => $this->relevanceScore,
            'ttl' => $this->ttl,
            'environment' => $this->environment,
            'collapse_id' => $this->collapseId,
            'topic' => $this->topic,
            'priority' => $this->priority,
            'content_available' => $this->contentAvailable,
            'mutable_content' => $this->mutableContent,
            'tag' => $tags ?: null,
            'config_id' => $this->configId ?: config('notify.apns.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => ! is_null($value));
    }
}
