<?php

namespace RiseTechApps\Notify\Message;

/**
 * Notificação via webhook HTTP genérico, enviada ao servidor via /api/v1/send/webhook.
 *
 * O servidor faz a requisição ao `url` (ou ao default_url da config) e devolve o status
 * pelo callback (->webhookUrl()). Não confundir o `url` (destino da requisição) com o
 * `webhook_url` (seu callback de status).
 *
 * Obs.: o canal webhook não tem bloco de defaults em config/notify.php (a chave
 * `notify.webhook` já é o callback global de status) — `tag`/`config_id` vão por mensagem.
 */
class NotifyWebhook
{
    protected string $url = '';
    protected string $method = 'POST';
    protected array $payload = [];
    protected array $headers = [];
    protected string $authType = 'none';
    protected ?string $authToken = null;
    protected ?string $authUser = null;
    protected ?string $authPassword = null;
    protected int $timeout = 10;
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
        $this->authType  = 'bearer';
        $this->authToken = $token;

        return $this;
    }

    public function basicAuth(string $user, string $password): static
    {
        $this->authType     = 'basic';
        $this->authUser     = $user;
        $this->authPassword = $password;

        return $this;
    }

    public function apiKeyAuth(string $token): static
    {
        $this->authType  = 'api_key';
        $this->authToken = $token;

        return $this;
    }

    public function hmacAuth(): static
    {
        $this->authType = 'hmac';

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
            'url'           => $this->url ?: null,
            'method'        => $this->method !== 'POST' ? $this->method : null,
            'payload'       => count($this->payload) > 0 ? $this->payload : null,
            'headers'       => count($this->headers) > 0 ? $this->headers : null,
            'auth_type'     => $this->authType !== 'none' ? $this->authType : null,
            'auth_token'    => $this->authToken,
            'auth_user'     => $this->authUser,
            'auth_password' => $this->authPassword,
            'timeout'       => $this->timeout !== 10 ? $this->timeout : null,
            'tag'           => $this->tags ?: null,
            'config_id'     => $this->configId,
            'webhook_url'   => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
