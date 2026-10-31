<?php

namespace RiseTechApps\Notify\Message;

use RiseTechApps\Notify\Message\Concerns\HasSendOptions;

/**
 * Notificação via webhook HTTP genérico, enviada ao servidor via /api/v1/send/webhook.
 *
 * O servidor faz a requisição ao `url` (ou ao default_url da sua configuração do canal)
 * e devolve o status pelo callback (->webhookUrl()). Sem `url`, é preciso ter uma
 * configuração com default_url: o servidor não usa nada da plataforma neste canal. Não confundir o `url` (destino da requisição) com o
 * `webhook_url` (seu callback de status).
 *
 * Obs.: o canal webhook não tem bloco de defaults em config/notify.php (a chave
 * `notify.webhook` já é o callback global de status) — `tag`/`config_id` vão por mensagem.
 */
class NotifyWebhook
{
    use HasSendOptions;

    protected string $url = '';

    protected string $method = 'POST';

    protected array $payload = [];

    protected array $headers = [];

    protected string $authType = 'none';

    protected ?string $authToken = null;

    protected ?string $authUser = null;

    protected ?string $authPassword = null;

    protected ?string $apiKeyHeader = null;

    protected ?string $secret = null;

    protected int $timeout = 10;

    protected ?string $bodyFormat = null;

    protected ?bool $notifyMeta = null;

    protected array $tags = [];

    protected ?string $configId = null;

    protected ?string $webhookUrl = null;

    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function method(string $method): static
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function payload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function headers(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function bearerAuth(string $token): static
    {
        $this->authType = 'bearer';
        $this->authToken = $token;

        return $this;
    }

    public function basicAuth(string $user, string $password): static
    {
        $this->authType = 'basic';
        $this->authUser = $user;
        $this->authPassword = $password;

        return $this;
    }

    /** Chave num header (padrão X-API-KEY, ou o da configuração). */
    public function apiKeyAuth(string $token, ?string $header = null): static
    {
        $this->authType = 'api_key';
        $this->authToken = $token;
        $this->apiKeyHeader = $header;

        return $this;
    }

    /**
     * Assina o corpo com HMAC-SHA256 (X-Notify-Signature e X-Hub-Signature-256).
     * Sem $secret, usa o da sua configuração do canal (sem nenhum dos dois, 422).
     * Não vale com GET nem com asForm().
     */
    public function hmacAuth(?string $secret = null): static
    {
        $this->authType = 'hmac';
        $this->secret = $secret;

        return $this;
    }

    /** Corpo como formulário (application/x-www-form-urlencoded). Não vale com hmacAuth(). */
    public function asForm(): static
    {
        $this->bodyFormat = 'form';

        return $this;
    }

    /** false: o servidor não acrescenta o `_notify` ao payload. */
    public function notifyMeta(bool $enabled = true): static
    {
        $this->notifyMeta = $enabled;

        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Adiciona tag(s) para agrupar/filtrar no histórico. Aceita string ou array;
     * acumula entre chamadas.
     */
    public function tag(string|array $tag): static
    {
        $this->tags = array_values(array_unique(array_merge($this->tags, (array) $tag)));

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
            'url' => $this->url ?: null,
            'method' => $this->method !== 'POST' ? $this->method : null,
            'body_format' => $this->bodyFormat,
            'payload' => count($this->payload) > 0 ? $this->payload : null,
            'notify_meta' => $this->notifyMeta === false ? false : null,
            'headers' => count($this->headers) > 0 ? $this->headers : null,
            'auth_type' => $this->authType !== 'none' ? $this->authType : null,
            'auth_token' => $this->authToken,
            'auth_user' => $this->authUser,
            'auth_password' => $this->authPassword,
            'api_key_header' => $this->apiKeyHeader,
            'secret' => $this->secret,
            'timeout' => $this->timeout !== 10 ? $this->timeout : null,
            'tag' => $this->tags ?: null,
            'config_id' => $this->configId,
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => ! is_null($value));
    }
}
