<?php

namespace RiseTechApps\Notify\Server;

/**
 * Chat do WhatsApp: conversas, eventos e autoatendimento.
 *
 *   $chat = NotifyQuery::server()->chat();
 *   $chat->conversations()->status('queue')->get();
 *   $chat->as($user->id, $user->name)->conversation($id)->reply('Bom dia!');
 *   $chat->events()->after($cursor)->wait(25)->get();
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

    /** Números com o chat ligado. */
    public function numbers(): array
    {
        return $this->result($this->http()->get('/api/v1/chat/numbers'));
    }

    public function conversations(): ConversationQuery
    {
        return new ConversationQuery($this->headers);
    }

    public function conversation(string $id): Conversation
    {
        return new Conversation($id, $this->headers, $this->memberId);
    }

    /** Começa (ou retoma) uma conversa com um número e manda a primeira mensagem. */
    public function start(string $configurationId, string $to, string $content): array
    {
        return $this->result($this->http()->post('/api/v1/chat/conversations', self::filled([
            'configuration_id' => $configurationId,
            'to' => $to,
            'content' => $content,
            'agent_id' => $this->memberId,
        ])));
    }

    /** Eventos do chat desde um cursor (GET /chat/events), com long polling. */
    public function events(): ChatEvents
    {
        return new ChatEvents($this->headers);
    }

    public function botFlows(): BotFlows
    {
        return new BotFlows($this->headers);
    }
}
