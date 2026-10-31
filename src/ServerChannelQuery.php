<?php

namespace RiseTechApps\Notify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ServerChannelQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $resource,
        protected ?string $id = null,
    ) {}

    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;

        return $this;
    }

    public function untagged(bool $untagged = true): static
    {
        $this->params['untagged'] = $untagged;

        return $this;
    }

    public function status(string $status): static
    {
        $this->params['status'] = $status;

        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;

        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;

        return $this;
    }

    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/{$this->resource}/{$this->id}")->json() ?? [];
        }

        return $this->http()->get("/api/v1/{$this->resource}", $this->params)->json() ?? [];
    }

    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource).' id is required to cancel.', 'notification_id' => null, 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/{$this->resource}/{$id}/cancel");

        return [
            'status' => $response->json('status', false),
            'message' => $response->json('message'),
            'notification_id' => $response->json('notification_id'),
            'current_status' => $response->json('current_status'),
            'http' => $response->status(),
        ];
    }

    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, $this->resource, $id);
    }

    /**
     * Edita o texto da mensagem enviada (Telegram, Slack, Discord e WhatsApp).
     * $extra leva os demais campos do canal (title, fields, embeds, buttons…).
     * No WhatsApp, só texto e até 15 minutos depois do envio.
     */
    public function edit(string $text, ?string $parseMode = null, array $extra = []): array
    {
        $field = $this->resource === 'whatsapp' ? 'content' : 'message';

        return $this->action('edit', [$field => $text, 'parse_mode' => $parseMode, ...$extra]);
    }

    /** Apaga a mensagem enviada (Telegram, Slack, Discord e WhatsApp; no WhatsApp, até 2 dias). */
    public function delete(): array
    {
        return $this->action('delete');
    }

    /** Telegram: edita a legenda da mídia. */
    public function editCaption(string $caption, ?string $parseMode = null): array
    {
        return $this->action('edit-caption', ['message' => $caption, 'parse_mode' => $parseMode]);
    }

    /** Telegram: troca a mídia (photo, video, document, audio ou animation), com legenda opcional. */
    public function editMedia(string $url, string $type, ?string $caption = null, bool $spoiler = false): array
    {
        return $this->action('edit-media', ['media_url' => $url, 'media_type' => $type, 'message' => $caption, 'has_spoiler' => $spoiler ?: null]);
    }

    /** Telegram: troca os botões; [] remove todos. */
    public function editButtons(array $buttons): array
    {
        return $this->action('edit-buttons', ['buttons' => $buttons]);
    }

    /** Fixa a mensagem (Telegram e Slack). $disableNotification vale só no Telegram. */
    public function pin(bool $disableNotification = false): array
    {
        return $this->action('pin', $this->resource === 'telegram' ? ['disable_notification' => $disableNotification] : []);
    }

    /** Desafixa a mensagem (Telegram e Slack). */
    public function unpin(): array
    {
        return $this->action('unpin');
    }

    /** Slack: reage com um emoji (ex.: thumbsup); $remove tira a reação. */
    public function react(string $emoji, bool $remove = false): array
    {
        return $this->action('react', ['emoji' => $emoji, 'remove' => $remove ?: null]);
    }

    /** POST /api/v1/{canal}/messages/{acao} sobre a notificação deste query. */
    protected function action(string $action, array $payload = []): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource)." id is required to {$action}.", 'http' => 0];
        }

        $response = $this->http()->post(
            "/api/v1/{$this->resource}/messages/{$action}",
            ['notification_id' => $this->id, ...array_filter($payload, fn ($v) => $v !== null)],
        );

        return [
            'status' => $response->json('status', false),
            'message' => $response->json('message'),
            'errors' => $response->json('errors'),
            'http' => $response->status(),
        ];
    }

    public function preview(?string $format = null): string|array
    {
        $id = $this->id;

        if ($id === null) {
            return ['error' => ucfirst($this->resource).' id is required for preview.'];
        }

        $query = $format !== null ? ['format' => $format] : [];

        $response = $this->http()->get("/api/v1/{$this->resource}/{$id}/preview", $query);

        if ($response->failed()) {
            return ['error' => $response->body()];
        }

        if ($format === 'json') {
            return $response->json() ?? [];
        }

        return $response->body();
    }

    public function previewLink(): array
    {
        $id = $this->id;

        if ($id === null) {
            return ['error' => ucfirst($this->resource).' id is required for preview link.'];
        }

        $response = $this->http()->get("/api/v1/{$this->resource}/{$id}/preview/link");

        if ($response->failed()) {
            return ['error' => $response->body()];
        }

        return $response->json() ?? [];
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
