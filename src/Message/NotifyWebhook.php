<?php

namespace RiseTechApps\Notify\Message;

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
            'url'           => $this->url,
            'method'        => $this->method !== 'POST' ? $this->method : null,
            'payload'       => count($this->payload) > 0 ? $this->payload : null,
            'headers'       => count($this->headers) > 0 ? $this->headers : null,
            'auth_type'     => $this->authType !== 'none' ? $this->authType : null,
            'auth_token'    => $this->authToken,
            'auth_user'     => $this->authUser,
            'auth_password' => $this->authPassword,
            'timeout'       => $this->timeout !== 10 ? $this->timeout : null,
            'config_id'     => $this->configId,
            'webhook_url'   => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
