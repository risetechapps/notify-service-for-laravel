<?php

namespace RiseTechApps\Notify\Message;

use RiseTechApps\Notify\Message\Concerns\HasSendOptions;

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
    use HasSendOptions;

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

    /** Mídia por URL: tipo (document, video, audio, voice, animation) => URL. */
    protected array $media = [];

    protected ?string $filename = null;

    protected ?string $mediaBase64 = null;

    protected ?string $mediaType = null;

    protected ?bool $hasSpoiler = null;

    protected ?bool $captionAbove = null;

    protected array $mediaGroup = [];

    protected ?bool $allowWithoutReply = null;

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
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        return $this;
    }

    /** Envia um cartão de contato. */
    public function contact(string $phoneNumber, string $firstName, ?string $lastName = null): static
    {
        $this->contact = array_filter([
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ], fn ($v) => ! is_null($v));

        return $this;
    }

    /**
     * Envia uma enquete (até 12 opções). $extra aceita allows_multiple_answers,
     * open_period (5 a 600 s) e, num quiz, type, correct_option_id e explanation
     * — ou use quiz().
     */
    public function poll(string $question, array $options, bool $isAnonymous = true, array $extra = []): static
    {
        $this->poll = [
            'question' => $question,
            'options' => array_values($options),
            'is_anonymous' => $isAnonymous,
            ...$extra,
        ];

        return $this;
    }

    /** Enquete com mais de uma resposta por pessoa (não vale em quiz). Chame depois de poll(). */
    public function pollMultipleAnswers(bool $multiple = true): static
    {
        return $this->pollOption('allows_multiple_answers', $multiple);
    }

    /** Fecha a enquete depois de $seconds segundos (5 a 600). Chame depois de poll() ou quiz(). */
    public function pollOpenPeriod(int $seconds): static
    {
        return $this->pollOption('open_period', $seconds);
    }

    protected function pollOption(string $key, mixed $value): static
    {
        if ($this->poll === null) {
            throw new \LogicException("Chame poll() ou quiz() antes de definir {$key}.");
        }

        $this->poll[$key] = $value;

        return $this;
    }

    /** Quiz: enquete com uma resposta certa (índice a partir de 0). */
    public function quiz(string $question, array $options, int $correctOptionId, ?string $explanation = null, bool $isAnonymous = true): static
    {
        return $this->poll($question, $options, $isAnonymous, array_filter([
            'type' => 'quiz',
            'correct_option_id' => $correctOptionId,
            'explanation' => $explanation,
        ], fn ($v) => $v !== null));
    }

    /** Documento por URL; o message vira a legenda. */
    public function documentUrl(string $url, ?string $filename = null): static
    {
        $this->media['document'] = $url;
        $this->filename = $filename ?? $this->filename;

        return $this;
    }

    public function videoUrl(string $url): static
    {
        $this->media['video'] = $url;

        return $this;
    }

    public function audioUrl(string $url): static
    {
        $this->media['audio'] = $url;

        return $this;
    }

    /** Mensagem de voz (OGG/Opus). */
    public function voiceUrl(string $url): static
    {
        $this->media['voice'] = $url;

        return $this;
    }

    /** GIF ou vídeo curto sem som. */
    public function animationUrl(string $url): static
    {
        $this->media['animation'] = $url;

        return $this;
    }

    /**
     * Arquivo no corpo da requisição (até 8 MB), sem URL pública: conteúdo
     * cru (o pacote converte para base64). Tipo: photo, document, video,
     * audio, voice ou animation.
     */
    public function media(string $content, string $type, ?string $filename = null): static
    {
        $this->mediaBase64 = base64_encode($content);
        $this->mediaType = $type;
        $this->filename = $filename ?? $this->filename;

        return $this;
    }

    /** Arquivo local; o tipo é deduzido do mime quando não informado. */
    public function mediaFile(string $path, ?string $type = null): static
    {
        $mime = (string) mime_content_type($path);
        $type ??= match (true) {
            str_starts_with($mime, 'image/gif') => 'animation',
            str_starts_with($mime, 'image/') => 'photo',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/ogg') => 'voice',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };

        return $this->media((string) file_get_contents($path), $type, basename($path));
    }

    /**
     * Álbum de 2 a 10 itens: [['type' => 'photo|video|document|audio', 'url' => ..., 'caption' => ...]].
     * Foto e vídeo podem ir juntos; documento e áudio, só com o mesmo tipo.
     */
    public function mediaGroup(array $items): static
    {
        $this->mediaGroup = array_values($items);

        return $this;
    }

    /** Foto, vídeo ou animação com spoiler (embaçados até tocar). */
    public function spoiler(bool $spoiler = true): static
    {
        $this->hasSpoiler = $spoiler;

        return $this;
    }

    /** Legenda acima da mídia. */
    public function captionAbove(bool $above = true): static
    {
        $this->captionAbove = $above;

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
     * Adiciona um botão inline de link. Cada chamada vira uma linha; com
     * $sameRow, o botão entra na linha do anterior.
     * Exemplo: ->button('Ver pedido', 'https://app.com/pedidos/1')
     */
    public function button(string $text, string $url, bool $sameRow = false): static
    {
        return $this->addButton(['text' => $text, 'url' => $url], $sameRow);
    }

    /** Abre um Mini App do Telegram (Web App) nesta URL. Só em chat privado. */
    public function webAppButton(string $text, string $url, bool $sameRow = false): static
    {
        return $this->addButton(['text' => $text, 'web_app' => ['url' => $url]], $sameRow);
    }

    /** Login no seu site pelo Telegram (Telegram Login). O domínio precisa estar ligado ao bot no BotFather. */
    public function loginButton(string $text, string $url, bool $sameRow = false): static
    {
        return $this->addButton(['text' => $text, 'login_url' => ['url' => $url]], $sameRow);
    }

    /** Copia $copy para a área de transferência de quem toca (até 256 caracteres). */
    public function copyButton(string $text, string $copy, bool $sameRow = false): static
    {
        return $this->addButton(['text' => $text, 'copy_text' => ['text' => $copy]], $sameRow);
    }

    /**
     * Abre a busca inline do bot com $query. Por padrão o usuário escolhe o
     * chat; com $currentChat, a busca abre no chat atual.
     */
    public function switchInlineButton(string $text, string $query = '', bool $currentChat = false, bool $sameRow = false): static
    {
        return $this->addButton(['text' => $text, $currentChat ? 'switch_inline_query_current_chat' : 'switch_inline_query' => $query], $sameRow);
    }

    protected function addButton(array $button, bool $sameRow): static
    {
        if ($sameRow && $this->buttons !== []) {
            $this->buttons[array_key_last($this->buttons)][] = $button;
        } else {
            $this->buttons[][] = $button;
        }

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
    /** Responde a uma mensagem; com $allowWithoutReply, envia mesmo se ela tiver sido apagada. */
    public function replyTo(int $messageId, bool $allowWithoutReply = false): static
    {
        $this->replyToMessageId = $messageId;
        $this->allowWithoutReply = $allowWithoutReply ?: null;

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
            'chat_id' => $this->chatId,

            // Conteúdo
            'message' => $this->message ?: null,
            'image_url' => $this->imageUrl,
            'document_url' => $this->media['document'] ?? null,
            'video_url' => $this->media['video'] ?? null,
            'audio_url' => $this->media['audio'] ?? null,
            'voice_url' => $this->media['voice'] ?? null,
            'animation_url' => $this->media['animation'] ?? null,
            'media_base64' => $this->mediaBase64,
            'media_type' => $this->mediaType,
            'filename' => $this->filename,
            'media_group' => $this->mediaGroup ?: null,
            'has_spoiler' => $this->hasSpoiler,
            'show_caption_above_media' => $this->captionAbove,
            'location' => $this->location,
            'contact' => $this->contact,
            'poll' => $this->poll,

            // Formatação / botões
            'parse_mode' => $this->parseMode,
            'buttons' => $this->buttons ?: null,

            // Opções comuns
            'disable_notification' => $this->disableNotification ?: null,
            'protect_content' => $this->protectContent ?: null,
            'reply_to_message_id' => $this->replyToMessageId,
            'allow_sending_without_reply' => $this->allowWithoutReply,
            'message_thread_id' => $this->messageThreadId,
            'disable_preview' => $this->disablePreview ?: null,

            // Servidor
            'tag' => $tags ?: null,
            'config_id' => $this->configId ?: config('notify.telegram.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => ! is_null($value));
    }
}
