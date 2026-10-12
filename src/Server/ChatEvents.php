<?php

namespace RiseTechApps\Notify\Server;

/**
 * Eventos do chat em ordem (GET /chat/events): message.received, message.sent,
 * message.failed, message.status, message.updated, conversation.opened,
 * conversation.updated e conversation.closed. Os mesmos que saem pelo webhook
 * do chat; aqui servem para conferir o que o webhook perdeu.
 *
 *   $cursor = $chat->events()->latest();                    // primeira vez
 *   $page = $chat->events()->after($cursor)->wait(25)->get(); // { data, next, has_more }
 *
 * Para rodar como processo, veja o comando notify:whatsapp-events.
 */
class ChatEvents extends Resource
{
    protected array $params = [];

    public function after(int|string $cursor): static
    {
        $this->params['after'] = (string) $cursor;

        return $this;
    }

    /** Long polling: sem novidade, o servidor espera até $seconds (máx. 25) e responde assim que algo acontecer. */
    public function wait(int $seconds = 25): static
    {
        $this->params['wait'] = max(0, min(25, $seconds));

        return $this;
    }

    /** Só estes tipos (ex.: ['message.received', 'conversation.updated']). */
    public function types(array $types): static
    {
        $this->params['types'] = implode(',', $types);

        return $this;
    }

    public function number(string $configurationId): static
    {
        $this->params['configuration_id'] = $configurationId;

        return $this;
    }

    /** Até 500 por chamada (padrão 100). */
    public function limit(int $limit): static
    {
        $this->params['limit'] = $limit;

        return $this;
    }

    /** @return array{status: bool, data: list<array{id: int, type: string, configuration_id: string, conversation_id: ?string, occurred_at: string, payload: array}>, next: int, has_more: bool} */
    public function get(): array
    {
        // Com long polling, o tempo de resposta passa da espera pedida.
        $timeout = (int) ($this->params['wait'] ?? 0) + 15;

        return $this->result($this->http()->timeout($timeout)->get('/api/v1/chat/events', $this->params));
    }

    /** O cursor atual (para começar a acompanhar a partir de agora). */
    public function latest(): int
    {
        return (int) ($this->result($this->http()->get('/api/v1/chat/events', ['after' => 'latest']))['next'] ?? 0);
    }
}
