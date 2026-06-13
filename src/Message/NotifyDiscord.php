<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de Discord enviada ao servidor via /api/v1/send/discord.
 *
 * Suporta texto, embed rico (atalho via title/color/fields/... ou ->embeds() cru),
 * menções, thread, TTS e upload de arquivo por URL. O servidor enfileira (resposta 202
 * com notification_id) e devolve status pelo webhook.
 *
 * Você integra com a API REST do servidor (JSON) — não toca no formato nativo do
 * Discord (multipart/payload_json); isso é resolvido no servidor.
 *
 * - config_id: se não definido, usa o default de config('notify.discord.config_id').
 * - tag: as tags da mensagem são mescladas com as default de config('notify.discord.tags').
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Exemplos:
 * ──────────────────────────────────────────────────────────────────────────────
 *
 *   // Texto simples
 *   (new NotifyDiscord)->message('Novo cadastro: João')->tag('cadastros');
 *
 *   // Embed (atalho) com campos
 *   (new NotifyDiscord)
 *       ->username('NotifyBot')
 *       ->title('Novo usuário')
 *       ->message('João Silva acabou de se cadastrar.')
 *       ->color(0x2ecc71)
 *       ->field('Email', 'joao@email.com')
 *       ->field('Plano', 'Pro', inline: true);
 *
 *   // Menções (role usa prefixo &) + thread existente
 *   (new NotifyDiscord)->message('Incidente aberto.')
 *       ->mentions(['here', '&987654321', '123456789'])
 *       ->threadId('1112223334445556667');
 *
 *   // Upload de arquivo (o servidor baixa a URL e faz o upload)
 *   (new NotifyDiscord)->message('Log em anexo:')
 *       ->file('https://storage.empresa.com/erro.log', 'erro.log');
 */
class NotifyDiscord
{
    // ── Conteúdo ──────────────────────────────────────────────────────────────
    protected string $message = '';
    protected ?string $username = null;
    protected ?string $avatarUrl = null;
    protected ?string $title = null;
    protected ?int $color = null;
    protected ?string $imageUrl = null;
    protected ?string $thumbnail = null;
    protected ?string $footer = null;
    protected array $fields = [];
    protected ?array $embeds = null;

    // ── Menções ───────────────────────────────────────────────────────────────
    protected array $mentions = [];
    protected ?array $allowedMentions = null;

    // ── Thread / extras ─────────────────────────────────────────────────────────
    protected ?string $threadId = null;
    protected ?string $threadName = null;
    protected ?bool $tts = null;
    protected ?string $fileUrl = null;
    protected ?string $filename = null;

    // ── Transporte / servidor ───────────────────────────────────────────────────
    protected ?string $discordWebhookUrl = null;
    protected array $tags = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    // ── Conteúdo ──────────────────────────────────────────────────────────────

    /** Conteúdo da mensagem. Máx 2000 chars (obrigatório, salvo com embeds ou file_url). */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /** Sobrescreve o nome do webhook. Máx 80 chars. */
    public function username(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /** Sobrescreve o avatar do webhook (URL). */
    public function avatarUrl(string $url): static
    {
        $this->avatarUrl = $url;

        return $this;
    }

    /** Título do embed (atalho). Máx 256 chars. */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Cor do embed em decimal.
     * Dica: ->color(0x2ecc71) para verde, ->color(0xe74c3c) para vermelho.
     */
    public function color(int $color): static
    {
        $this->color = $color;

        return $this;
    }

    /** Imagem grande do embed (URL). */
    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    /** Miniatura do embed (URL). */
    public function thumbnail(string $url): static
    {
        $this->thumbnail = $url;

        return $this;
    }

    /** Rodapé do embed. Máx 2048 chars. */
    public function footer(string $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    /**
     * Adiciona um campo ao embed (máx 25).
     * Exemplo: ->field('Pedido', '#1234')
     */
    public function field(string $label, string $value, bool $inline = true): static
    {
        $this->fields[] = ['label' => $label, 'value' => $value, 'inline' => $inline];

        return $this;
    }

    /** Define todos os campos do embed de uma vez (substitui os anteriores). */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Embeds crus do Discord (máx 10). Têm prioridade sobre o embed-atalho
     * (title/color/fields/...).
     */
    public function embeds(array $embeds): static
    {
        $this->embeds = $embeds;

        return $this;
    }

    // ── Menções ───────────────────────────────────────────────────────────────

    /**
     * Adiciona menção(ões). Aceita string ou array; acumula entre chamadas.
     * Valores: "123" (user), "&456" (role/cargo — prefixo &), "everyone", "here".
     * O servidor monta as tags e o allowed_mentions sozinho.
     */
    public function mentions(string|array $mentions): static
    {
        $this->mentions = array_values(array_unique(array_merge($this->mentions, (array) $mentions)));

        return $this;
    }

    /**
     * Override cru do allowed_mentions (formato nativo do Discord), ex.:
     * ['parse' => ['everyone'], 'users' => ['123'], 'roles' => ['456']].
     * Sobrescreve o derivado de ->mentions().
     */
    public function allowedMentions(array $allowedMentions): static
    {
        $this->allowedMentions = $allowedMentions;

        return $this;
    }

    // ── Thread / extras ─────────────────────────────────────────────────────────

    /** Posta numa thread já existente (canal de texto). */
    public function threadId(string $threadId): static
    {
        $this->threadId = $threadId;

        return $this;
    }

    /** Cria uma thread nova (só em canais de fórum/mídia). Máx 100 chars. */
    public function threadName(string $threadName): static
    {
        $this->threadName = $threadName;

        return $this;
    }

    /** Text-to-speech. */
    public function tts(bool $tts = true): static
    {
        $this->tts = $tts;

        return $this;
    }

    /**
     * Anexa um arquivo por URL (o servidor baixa e faz o upload pro Discord).
     * Não é multipart — você passa apenas a URL pública.
     */
    public function file(string $url, ?string $filename = null): static
    {
        $this->fileUrl = $url;
        $this->filename = $filename;

        return $this;
    }

    // ── Transporte / servidor ───────────────────────────────────────────────────

    /**
     * Webhook de destino do Discord (override da config). Não confundir com
     * ->webhookUrl(), que é o callback de status.
     */
    public function discordWebhookUrl(string $url): static
    {
        $this->discordWebhookUrl = $url;

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
     * de config('notify.discord.config_id').
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
            (array) config('notify.discord.tags', []),
            $this->tags
        )));

        return array_filter([
            // Conteúdo
            'message'             => $this->message ?: null,
            'username'            => $this->username,
            'avatar_url'          => $this->avatarUrl,
            'title'               => $this->title,
            'color'               => $this->color,
            'image_url'           => $this->imageUrl,
            'thumbnail'           => $this->thumbnail,
            'footer'              => $this->footer,
            'fields'              => $this->fields ?: null,
            'embeds'              => $this->embeds,

            // Menções
            'mentions'            => $this->mentions ?: null,
            'allowed_mentions'    => $this->allowedMentions,

            // Thread / extras
            'thread_id'           => $this->threadId,
            'thread_name'         => $this->threadName,
            'tts'                 => $this->tts ?: null,
            'file_url'            => $this->fileUrl,
            'filename'            => $this->filename,

            // Transporte / servidor
            'discord_webhook_url' => $this->discordWebhookUrl,
            'tag'                 => $tags ?: null,
            'config_id'           => $this->configId ?: config('notify.discord.config_id'),
            'webhook_url'         => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
