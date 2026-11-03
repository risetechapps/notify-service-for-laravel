<?php

namespace RiseTechApps\Notify\Server;

/**
 * Chat do WhatsApp: conversas, eventos e autoatendimento.
 *
 *   $chat = NotifyQuery::server()->chat();
 *   $chat->conversations()->status('queue')->get();
 *   $chat->as($user->id, $user->name)->conversation($id)->reply('Bom dia!');
 *   $chat->events()->after($cursor)->get();
 *
 * as(): age como um atendente do SEU sistema (X-Agent-Id / X-Agent-Name):
 * a resposta sai assinada com o nome dele, ele assume a conversa e o contato
 * é avisado. No primeiro uso com nome ele é cadastrado no servidor, sem login.
 * asMember(): o mesmo, pelo id de alguém da equipe (GET /team/members).
 */
class Chat extends Resource
{
    public function __construct(array $headers = [], protected ?string $memberId = null)
    {
        parent::__construct($headers);
    }

    /** Age como um atendente do seu sistema (id dele lá e, no primeiro uso, o nome). */
    public function as(string|int $externalId, ?string $name = null): static
    {
        return new static(array_filter([...$this->headers, 'X-Agent-Id' => (string) $externalId, 'X-Agent-Name' => $name]), null);
    }

    /** Age como alguém da equipe pelo id do servidor (de team()->members()). */
    public function asMember(string $memberId): static
    {
        return new static($this->headers, $memberId);
    }

    /**
     * Números com o chat ligado. Guardado por notify.cache.ttl segundos (o
     * `state` da conexão pode estar até esse tempo atrasado); $fresh = true
     * busca na hora.
     */
    public function numbers(bool $fresh = false): array
    {
        return $this->remember('chat.numbers', fn () => $this->result($this->http()->get('/api/v1/chat/numbers')), $fresh);
    }

    public function conversations(): ConversationQuery
    {
        return new ConversationQuery($this->headers);
    }

    /**
     * Contadores para o painel do atendimento, numa chamada só:
     * queue (abertas sem responsável, esperando atendente), mine (com quem o
     * Chat representa, via as()), bot (no autoatendimento) e unread (bot ou
     * abertas com mensagem não lida). Sem as(), mine conta as da conta.
     *
     * @return array{status: bool, data?: array{queue: int, mine: int, bot: int, unread: int, me: ?string, server_time: ?string}}
     */
    public function summary(): array
    {
        $page = $this->conversations()->perPage(1)->get();

        if (isset($page['http'])) {
            return $page;
        }

        return ['status' => true, 'data' => [
            'queue' => (int) ($page['counts']['queue'] ?? 0),
            'mine' => (int) ($page['counts']['mine'] ?? 0),
            'bot' => (int) ($page['counts']['bot'] ?? 0),
            'unread' => (int) ($page['unread'] ?? 0),
            'me' => $page['me'] ?? null,
            'server_time' => $page['server_time'] ?? null,
        ]];
    }

    public function conversation(string $id): Conversation
    {
        return new Conversation($id, $this->headers, $this->memberId);
    }

    /**
     * Começa (ou retoma) uma conversa com um número e manda a primeira
     * mensagem. $webhookUrl: webhook só desta conversa, até ela encerrar.
     * $quotedId: id do WhatsApp da mensagem citada.
     */
    public function start(string $configurationId, string $to, string $content, ?string $webhookUrl = null, ?string $quotedId = null): array
    {
        return $this->result($this->http()->post('/api/v1/chat/conversations', self::filled([
            'configuration_id' => $configurationId,
            'to' => $to,
            'content' => $content,
            'quoted_id' => $quotedId,
            'agent_id' => $this->memberId,
            'webhook_url' => $webhookUrl,
        ])));
    }

    /** Começa (ou retoma) uma conversa com uma enquete (de 2 a 12 opções). */
    public function startPoll(string $configurationId, string $to, string $question, array $options, bool $multiple = false, ?string $webhookUrl = null): array
    {
        return $this->result($this->http()->post('/api/v1/chat/conversations', self::filled([
            'configuration_id' => $configurationId,
            'to' => $to,
            'poll' => self::filled(['question' => $question, 'options' => array_values($options), 'multiple' => $multiple ?: null]),
            'agent_id' => $this->memberId,
            'webhook_url' => $webhookUrl,
        ])));
    }

    /** Eventos do chat desde um cursor (GET /chat/events). */
    public function events(): ChatEvents
    {
        return new ChatEvents($this->headers);
    }

    public function botFlows(): BotFlows
    {
        return new BotFlows($this->headers);
    }
}
