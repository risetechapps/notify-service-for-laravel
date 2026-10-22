<?php

namespace RiseTechApps\Notify\Message;

use RiseTechApps\Notify\Message\Concerns\HasSendOptions;
use RiseTechApps\Notify\Message\Concerns\HasTemplate;

/**
 * Mensagem de WhatsApp enviada ao servidor via /api/v1/send/whatsapp, pelo
 * número conectado da conta (config_id escolhe qual, se houver mais de um).
 *
 * Texto, mídia por URL pública (imagem, vídeo, documento ou áudio, com o texto
 * como legenda) ou enquete. Formatação do WhatsApp: *negrito*, _itálico_.
 *
 *   (new NotifyWhatsApp)->content('Seu pedido *#1042* saiu para entrega.');
 *   (new NotifyWhatsApp)->document('https://cdn.loja.com/boleto.pdf', 'boleto.pdf')->content('Segue o boleto');
 *   (new NotifyWhatsApp)->poll('Confirma a consulta?', ['Sim', 'Preciso remarcar']);
 *
 * Imagens que não são JPEG/PNG e vídeos que não são MP4 são convertidos pelo
 * servidor. Os envios seguem a proteção do número (ritmo, limites diários):
 * recusado por ela, o servidor responde 429 com `reason`.
 */
class NotifyWhatsApp
{
    use HasSendOptions, HasTemplate;

    public const MEDIA_TYPES = ['image', 'video', 'document', 'audio'];

    protected string $to = '';

    protected string $content = '';

    protected ?string $mediaUrl = null;

    protected ?string $mediaType = null;

    protected ?string $fileName = null;

    protected ?array $poll = null;

    protected array $tags = [];

    protected ?string $configId = null;

    protected ?string $webhookUrl = null;

    /** Número com DDI e DDD (ex.: 5521999998888). Número brasileiro sem o 55 ganha o 55 no servidor. */
    public function to(string $to): static
    {
        if (blank($this->to)) {
            $this->to = $to;
        }

        return $this;
    }

    /** Se a mensagem já tem destino (->to()); o canal só usa o do notifiable quando não tem. */
    public function hasRecipient(): bool
    {
        return $this->to !== '';
    }

    /** Texto (ou a legenda da mídia). Até 4.096 caracteres. */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /** Mídia por URL pública. $type: image | video | document | audio. */
    public function media(string $url, string $type, ?string $fileName = null): static
    {
        if (! in_array($type, self::MEDIA_TYPES, true)) {
            throw new \InvalidArgumentException('Tipo de mídia inválido: use '.implode(', ', self::MEDIA_TYPES).'.');
        }

        $this->mediaUrl = $url;
        $this->mediaType = $type;
        $this->fileName = $fileName;

        return $this;
    }

    public function image(string $url): static
    {
        return $this->media($url, 'image');
    }

    public function video(string $url): static
    {
        return $this->media($url, 'video');
    }

    public function audio(string $url): static
    {
        return $this->media($url, 'audio');
    }

    public function document(string $url, ?string $fileName = null): static
    {
        return $this->media($url, 'document', $fileName);
    }

    /**
     * Enquete no lugar do texto (de 2 a 12 opções). O voto chega no webhook
     * como whatsapp.poll_vote.
     */
    public function poll(string $question, array $options, bool $multiple = false): static
    {
        $this->poll = array_filter([
            'question' => $question,
            'options' => array_values($options),
            'multiple' => $multiple ?: null,
        ], fn ($value) => $value !== null);

        return $this;
    }

    public function tag(string|array $tag): static
    {
        $this->tags = array_values(array_unique(array_merge($this->tags, (array) $tag)));

        return $this;
    }

    /** O número que envia (UUID ou label da configuração). Sobrescreve config('notify.whatsapp.config_id'). */
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
        $tags = array_values(array_unique(array_merge((array) config('notify.whatsapp.tags', []), $this->tags)));

        return array_filter([
            'to' => $this->to,
            'content' => $this->poll ? null : ($this->content !== '' ? $this->content : null),
            'media_url' => $this->mediaUrl,
            'media_type' => $this->mediaType,
            'file_name' => $this->fileName,
            'poll' => $this->poll,
            'tag' => $tags ?: null,
            'config_id' => $this->configId ?: config('notify.whatsapp.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => ! is_null($value));
    }
}
