<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de Slack enviada ao servidor via /api/v1/send/slack.
 *
 * Três modos de corpo (prioridade no servidor):
 *   1. blocks (Block Kit) → message vira fallback
 *   2. attachment (quando há title/fields) → card colorido
 *   3. texto simples (só message)
 *
 * Dois modos de transporte (definidos pela credencial / pelos campos):
 *   - Bot Token (chat.postMessage): qualquer canal, retorna ts; habilita thread,
 *     upload de arquivo e editar/apagar.
 *   - Incoming Webhook (->slackWebhookUrl()): canal fixo, mais simples.
 *
 * - config_id: se não definido, usa o default de config('notify.slack.config_id').
 * - tag: as tags da mensagem são mescladas com as default de config('notify.slack.tags').
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Exemplos:
 * ──────────────────────────────────────────────────────────────────────────────
 *
 *   // Texto simples
 *   (new NotifySlack)->channel('#deploys')->message('Deploy concluído 🚀')->tag('deploys');
 *
 *   // Attachment com campos
 *   (new NotifySlack)
 *       ->channel('#alertas')->title('CPU 90%')->message('Alerta')->color('#FF0000')
 *       ->field('Servidor', 'web-01', short: true);
 *
 *   // Block Kit
 *   (new NotifySlack)->channel('#deploys')->message('Deploy v1.2.0')->blocks([
 *       ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*Deploy v1.2.0* :rocket:']],
 *       ['type' => 'divider'],
 *   ]);
 *
 *   // Menções + thread
 *   (new NotifySlack)->channel('#incidentes')->message('Incidente aberto.')
 *       ->mentions(['here', 'U024BE7LH']);
 *   (new NotifySlack)->channel('#deploys')->message('Deploy ✅')->thread('1718150400.123456');
 *
 *   // Incoming Webhook + aparência
 *   (new NotifySlack)->message('Backup ok.')
 *       ->slackWebhookUrl('https://hooks.slack.com/services/T/B/x')
 *       ->username('Backup Bot')->iconEmoji(':floppy_disk:');
 *
 *   // Upload de arquivo (Bot Token)
 *   (new NotifySlack)->channel('#relatorios')->message('Relatório em anexo:')
 *       ->file('https://storage.empresa.com/maio.pdf', 'Maio/2026');
 */
class NotifySlack
{
    // ── Conteúdo ──────────────────────────────────────────────────────────────
    protected string $message = '';
    protected ?string $channel = null;
    protected ?string $title = null;
    protected ?string $color = null;
    protected ?string $footer = null;
    protected array $fields = [];
    protected ?array $blocks = null;

    // ── Threads / menções ───────────────────────────────────────────────────────
    protected ?string $threadTs = null;
    protected array $mentions = [];

    // ── Aparência (Incoming Webhook / bot) ──────────────────────────────────────
    protected ?string $username = null;
    protected ?string $iconEmoji = null;
    protected ?string $iconUrl = null;

    // ── Arquivo (Bot Token) ─────────────────────────────────────────────────────
    protected ?string $fileUrl = null;
    protected ?string $fileTitle = null;
    protected ?string $filename = null;

    // ── Transporte / servidor ───────────────────────────────────────────────────
    protected ?string $slackWebhookUrl = null;
    protected array $tags = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    // ── Conteúdo ──────────────────────────────────────────────────────────────

    /** Texto principal. Máx 4000 chars (obrigatório, salvo com blocks ou file_url). */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /** Canal destino (Bot Token), ex.: "#deploys" ou "C0123ABC". */
    public function channel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    /** Título do attachment (ativa o card rico). */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** Cor hex da barra lateral do attachment (ex.: "#FF0000"). */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /** Rodapé do attachment. */
    public function footer(string $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    /**
     * Adiciona um campo ao attachment.
     * @param bool|null $short  true = campo curto (lado a lado)
     */
    public function field(string $label, string $value, ?bool $short = null): static
    {
        $this->fields[] = array_filter([
            'label' => $label,
            'value' => $value,
            'short' => $short,
        ], fn ($v) => !is_null($v));

        return $this;
    }

    /**
     * Define todos os campos de uma vez (substitui os anteriores).
     * Exemplo: ->fields([['label' => 'Status', 'value' => 'Aprovado', 'short' => true]])
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /** Block Kit cru (seções, botões, dividers, imagens). message vira fallback. */
    public function blocks(array $blocks): static
    {
        $this->blocks = $blocks;

        return $this;
    }

    // ── Threads / menções ───────────────────────────────────────────────────────

    /** Responde em thread, usando o `ts` da mensagem-pai. */
    public function thread(string $ts): static
    {
        $this->threadTs = $ts;

        return $this;
    }

    /**
     * Adiciona menção(ões). Aceita string ou array; acumula entre chamadas.
     * Valores: user id ("U123"), canal ("C123"), "channel", "here".
     */
    public function mentions(string|array $mentions): static
    {
        $this->mentions = array_values(array_unique(array_merge($this->mentions, (array) $mentions)));

        return $this;
    }

    // ── Aparência ─────────────────────────────────────────────────────────────

    /** Sobrescreve o nome do remetente. */
    public function username(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /** Ícone por emoji (ex.: ":rocket:"). */
    public function iconEmoji(string $emoji): static
    {
        $this->iconEmoji = $emoji;

        return $this;
    }

    /** Ícone por URL. */
    public function iconUrl(string $url): static
    {
        $this->iconUrl = $url;

        return $this;
    }

    // ── Arquivo ───────────────────────────────────────────────────────────────

    /** Anexa um arquivo por URL (Bot Token). title/filename opcionais. */
    public function file(string $url, ?string $title = null, ?string $filename = null): static
    {
        $this->fileUrl = $url;
        $this->fileTitle = $title;
        $this->filename = $filename;

        return $this;
    }

    // ── Transporte / servidor ───────────────────────────────────────────────────

    /**
     * URL de um Incoming Webhook do Slack para entregar esta mensagem.
     * Quando informada, força o modo Incoming Webhook (o channel é ignorado).
     * Não confundir com ->webhookUrl(), que é o callback de status.
     */
    public function slackWebhookUrl(string $url): static
    {
        $this->slackWebhookUrl = $url;

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
     * de config('notify.slack.config_id').
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
            (array) config('notify.slack.tags', []),
            $this->tags
        )));

        return array_filter([
            // Conteúdo
            'message'           => $this->message ?: null,
            'channel'           => $this->channel,
            'title'             => $this->title,
            'color'             => $this->color,
            'footer'            => $this->footer,
            'fields'            => $this->fields ?: null,
            'blocks'            => $this->blocks,

            // Threads / menções
            'thread_ts'         => $this->threadTs,
            'mentions'          => $this->mentions ?: null,

            // Aparência
            'username'          => $this->username,
            'icon_emoji'        => $this->iconEmoji,
            'icon_url'          => $this->iconUrl,

            // Arquivo
            'file_url'          => $this->fileUrl,
            'file_title'        => $this->fileTitle,
            'filename'          => $this->filename,

            // Transporte / servidor
            'slack_webhook_url' => $this->slackWebhookUrl,
            'tag'               => $tags ?: null,
            'config_id'         => $this->configId ?: config('notify.slack.config_id'),
            'webhook_url'       => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
