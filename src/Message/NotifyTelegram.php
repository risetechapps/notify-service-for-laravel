<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de Telegram enviada ao servidor via /api/v1/send/telegram.
 *
 * Suporta os tipos de conteúdo do servidor (texto, foto, localização, contato,
 * enquete) e as opções comuns (silencioso, proteção de conteúdo, resposta,
 * tópico de grupo, preview de link).
 *
 * Um envio carrega UM tipo de conteúdo. Se mais de um for definido, o servidor
 * usa a precedência dele — prefira setar apenas um.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Exemplos:
 * ──────────────────────────────────────────────────────────────────────────────
 *
 *   // Texto com botão e preview desabilitado
 *   (new NotifyTelegram)
 *       ->chatId($chatId)
 *       ->message('🚀 Deploy concluído!')
 *       ->parseMode('Markdown')
 *       ->disablePreview()
 *       ->button('Ver logs', 'https://app.com/logs');
 *
 *   // Localização
 *   (new NotifyTelegram)->chatId($chatId)->location(-23.5614, -46.6559);
 *
 *   // Contato
 *   (new NotifyTelegram)->chatId($chatId)->contact('+5511999998888', 'Suporte', 'NotifyApp');
 *
 *   // Enquete
 *   (new NotifyTelegram)->chatId($chatId)->poll('Gostou?', ['Sim', 'Não']);
 *
 *   // Silencioso e protegido
 *   (new NotifyTelegram)->chatId($chatId)->message('Aviso')->silent()->protectContent();
 */
class NotifyTelegram
{
    // ── Alvo ────────────────────────────────────────────────────────────────────
    protected string $chatId = '';

    // ── Conteúdo (um por envio) ─────────────────────────────────────────────────
    protected string $message = '';
    protected ?string $imageUrl = null;
    protected ?array $location = null;
    protected ?array $contact = null;
    protected ?array $poll = null;

    // ── Formatação / botões ─────────────────────────────────────────────────────
    protected string $parseMode = 'Markdown';
    protected array $buttons = [];

    // ── Opções comuns ─────────────────────────────────────────────────────────
    protected ?bool $disableNotification = null;
    protected ?bool $protectContent = null;
    protected ?int $replyToMessageId = null;
    protected ?int $messageThreadId = null;
    protected ?bool $disablePreview = null;

    // ── Servidor ──────────────────────────────────────────────────────────────
    protected array $tags = [];
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    // ── Alvo ────────────────────────────────────────────────────────────────────

    /** ID numérico do chat ou @username. Write-once: o primeiro valor prevalece. */
    public function chatId(string $chatId): static
    {
        if (blank($this->chatId)) {
            $this->chatId = $chatId;
        }

        return $this;
    }

    // ── Conteúdo ──────────────────────────────────────────────────────────────

    /** Mensagem de texto. Máx: 4096 chars. */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /** Envia uma foto a partir de uma URL. */
    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    /** Envia uma localização (pino no mapa). */
    public function location(float $latitude, float $longitude): static
    {
        $this->location = [
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];

        return $this;
    }

    /** Envia um cartão de contato. */
    public function contact(string $phoneNumber, string $firstName, ?string $lastName = null): static
    {
        $this->contact = array_filter([
            'phone_number' => $phoneNumber,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
        ], fn ($v) => !is_null($v));

        return $this;
    }

    /** Envia uma enquete. */
    public function poll(string $question, array $options, bool $isAnonymous = true): static
    {
        $this->poll = [
            'question'     => $question,
            'options'      => array_values($options),
            'is_anonymous' => $isAnonymous,
        ];

        return $this;
    }

    // ── Formatação / botões ─────────────────────────────────────────────────────

    /** Markdown | MarkdownV2 | HTML. */
    public function parseMode(string $mode): static
    {
        $this->parseMode = $mode;

        return $this;
    }

    /**
     * Adiciona um botão inline (cada chamada vira uma linha).
     * Exemplo: ->button('Ver pedido', 'https://app.com/pedidos/1')
     */
    public function button(string $text, string $url): static
    {
        $this->buttons[][] = ['text' => $text, 'url' => $url];

        return $this;
    }

    /**
     * Define a grade completa de botões inline.
     * Array externo = linhas; interno = botões.
     * Exemplo: [[ ['text' => 'Sim', 'url' => '...'], ['text' => 'Não', 'url' => '...'] ]]
     */
    public function buttons(array $buttons): static
    {
        $this->buttons = $buttons;

        return $this;
    }

    // ── Opções comuns ─────────────────────────────────────────────────────────

    /** Envio silencioso (sem som/vibração). */
    public function silent(bool $silent = true): static
    {
        $this->disableNotification = $silent;

        return $this;
    }

    /** Impede encaminhamento e cópia da mensagem. */
    public function protectContent(bool $protect = true): static
    {
        $this->protectContent = $protect;

        return $this;
    }

    /** Responde a uma mensagem existente (use o message_id do Telegram). */
    public function replyTo(int $messageId): static
    {
        $this->replyToMessageId = $messageId;

        return $this;
    }

    /** Envia para um tópico específico de um grupo/fórum. */
    public function threadId(int $threadId): static
    {
        $this->messageThreadId = $threadId;

        return $this;
    }

    /** Desabilita o preview de links no texto. */
    public function disablePreview(bool $disable = true): static
    {
        $this->disablePreview = $disable;

        return $this;
    }

    // ── Servidor ──────────────────────────────────────────────────────────────

    /**
     * Adiciona tag(s) para agrupar/filtrar. Aceita string ou array; acumula
     * entre chamadas e é mesclada com as tags default do config.
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
        $tags = array_values(array_unique(array_merge(
            (array) config('notify.telegram.tags', []),
            $this->tags
        )));

        return array_filter([
            // Alvo
            'chat_id'              => $this->chatId,

            // Conteúdo
            'message'              => $this->message ?: null,
            'image_url'            => $this->imageUrl,
            'location'             => $this->location,
            'contact'              => $this->contact,
            'poll'                 => $this->poll,

            // Formatação / botões
            'parse_mode'           => $this->parseMode,
            'buttons'              => $this->buttons ?: null,

            // Opções comuns
            'disable_notification' => $this->disableNotification ?: null,
            'protect_content'      => $this->protectContent ?: null,
            'reply_to_message_id'  => $this->replyToMessageId,
            'message_thread_id'    => $this->messageThreadId,
            'disable_preview'      => $this->disablePreview ?: null,

            // Servidor
            'tag'                  => $tags ?: null,
            'config_id'            => $this->configId ?: config('notify.telegram.config_id'),
            'webhook_url'          => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
