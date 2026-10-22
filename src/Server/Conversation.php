<?php

namespace RiseTechApps\Notify\Server;

use Illuminate\Support\Facades\Http;

/**
 * Uma conversa do chat: mensagens e ações. As ações saem em nome de quem o
 * Chat representa (as() / asMember()); sem isso, em nome da conta.
 */
class Conversation extends Resource
{
    /** `webhook_url` para a próxima ação (ver webhook()). */
    protected array $webhook = [];

    public function __construct(protected string $id, array $headers = [], protected ?string $memberId = null)
    {
        parent::__construct($headers);
    }

    /**
     * Webhook só desta conversa: os eventos dela (respostas do contato
     * incluídas) passam a ir só para ele, até encerrar. Vai junto da próxima
     * resposta ou transferência; null volta ao webhook do número.
     *
     *   $chat->conversation($id)->webhook('https://crm.exemplo.com/whatsapp')->reply('Olá!');
     */
    public function webhook(?string $url): static
    {
        $this->webhook = ['webhook_url' => $url];

        return $this;
    }

    public function get(): array
    {
        return $this->result($this->http()->get($this->path()));
    }

    /**
     * Mensagens: as 50 mais recentes; $before (id de mensagem) traz as
     * anteriores a ela (rolar para cima).
     */
    public function messages(?string $before = null, ?int $limit = null): array
    {
        return $this->result($this->http()->get($this->path('/messages'), self::filled(['before' => $before, 'limit' => $limit])));
    }

    /** Responde com texto. $quotedId: id do WhatsApp (provider_id) da mensagem citada. */
    public function reply(string $content, ?string $quotedId = null): array
    {
        return $this->result($this->http()->post($this->path('/messages'), $this->body(['content' => $content, 'quoted_id' => $quotedId])));
    }

    /** Responde com um arquivo (imagem, vídeo, áudio ou documento); $caption vira a legenda. */
    public function replyFile(string $path, ?string $caption = null, ?string $fileName = null): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("Arquivo não encontrado ou sem leitura: {$path}");
        }

        $request = $this->http()->attach('file', file_get_contents($path), $fileName ?? basename($path));

        return $this->result($request->post($this->path('/messages'), $this->body(['content' => $caption])));
    }

    /** Responde com uma enquete (de 2 a 12 opções). */
    public function replyPoll(string $question, array $options, bool $multiple = false): array
    {
        return $this->result($this->http()->post($this->path('/messages'), $this->body([
            'poll' => self::filled(['question' => $question, 'options' => array_values($options), 'multiple' => $multiple ?: null]),
        ])));
    }

    /** Zera as não lidas (e manda o ✓✓ azul, se o número estiver configurado para isso). */
    public function read(): array
    {
        return $this->result($this->http()->post($this->path('/read')));
    }

    /** Assume a conversa (do bot ou da fila). */
    public function handoff(): array
    {
        return $this->result($this->http()->post($this->path('/handoff'), $this->body([])));
    }

    /** Transfere para alguém da equipe (id de team()->members()), ou null para devolver à fila. */
    public function assign(?string $memberId): array
    {
        return $this->result($this->http()->post($this->path('/assign'), $this->body(['member_id' => $memberId], keepNull: true)));
    }

    public function close(): array
    {
        return $this->result($this->http()->post($this->path('/close')));
    }

    public function reopen(): array
    {
        return $this->result($this->http()->post($this->path('/reopen')));
    }

    /** Dá nome ao contato (vale para todas as conversas dele). */
    public function rename(?string $name): array
    {
        return $this->result($this->http()->patch($this->path('/contact'), ['name' => $name]));
    }

    protected function body(array $data, bool $keepNull = false): array
    {
        $data += self::filled(['agent_id' => $this->memberId]);

        // Depois do filtro: webhook_url null é pedido (volta ao do número).
        return [...($keepNull ? $data : self::filled($data)), ...$this->webhook];
    }

    /**
     * Baixa a mídia de uma mensagem (o `media.url` assinado que vem nas
     * mensagens e nos eventos; vale por 60 min). Devolve o conteúdo, ou null
     * quando não há mídia baixada ou o link expirou: leia a mensagem de novo
     * para um link novo.
     */
    public function downloadMedia(array $message): ?string
    {
        $url = $message['media']['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        $response = Http::get($url);

        return $response->successful() ? $response->body() : null;
    }

    protected function path(string $suffix = ''): string
    {
        return "/api/v1/chat/conversations/{$this->id}{$suffix}";
    }
}
