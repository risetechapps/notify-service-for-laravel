<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de SMS enviada ao servidor via /api/v1/send/sms.
 *
 * Campos do servidor: to, content, tag, config_id, webhook_url.
 *
 * - config_id: se não for definido na mensagem, usa o default de config('notify.sms.config_id').
 *   Definir ->configId() na notificação sobrescreve o default na hora do envio.
 * - tag: as tags da mensagem são mescladas com as default de config('notify.sms.tags').
 */
class NotifySms
{
    protected string $content = '';
    protected string $to = '';
    protected array $tags = [];
    protected ?string $configId = null;
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
     * Provedor/credencial específico (UUID ou label). Sobrescreve o default
     * de config('notify.sms.config_id').
     */
    public function configId(string $configId): static
    {
        $this->configId = $configId;

        return $this;
    }

    public function webhookUrl(string $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    public function toArray(): array
    {
        $tags = array_values(array_unique(array_merge(
            (array) config('notify.sms.tags', []),
            $this->tags
        )));

        return array_filter([
            'to'          => $this->to,
            'content'     => $this->content,
            'tag'         => $tags ?: null,
            'config_id'   => $this->configId ?: config('notify.sms.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
