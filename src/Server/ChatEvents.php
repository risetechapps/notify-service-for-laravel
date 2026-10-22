<?php

namespace RiseTechApps\Notify\Server;

/**
 * Eventos do chat em ordem (GET /chat/events): message.received, message.sent,
 * message.failed, message.status, message.updated, conversation.opened,
 * conversation.updated e conversation.closed. Os mesmos que saem pelo webhook
 * do chat; aqui servem para conferir o que o webhook perdeu.
 *
 *   $cursor = $chat->events()->latest();             // primeira vez
 *   $page = $chat->events()->after($cursor)->get();  // { data, next, has_more, retry_after? }
 *
 * O servidor responde na hora. Sem novidade (ou no 429), vem `retry_after`:
 * espere esses segundos antes de chamar de novo. Para rodar como processo,
 * veja o comando notify:whatsapp-events.
 */
class ChatEvents extends Resource
{
    protected array $params = [];

    public function after(int|string $cursor): static
    {
        $this->params['after'] = (string) $cursor;

        return $this;
    }

    /**
     * @deprecated O servidor não faz mais long polling e ignora a espera.
     *             Respeite o `retry_after` da resposta.
     */
    public function wait(int $seconds = 25): static
    {
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

    /**
     * `retry_after` vem quando não há eventos novos e no 429 (o cabeçalho
     * Retry-After do servidor, em segundos).
     *
     * @return array{status: bool, data: list<array{id: int, type: string, configuration_id: string, conversation_id: ?string, occurred_at: string, payload: array}>, next: int, has_more: bool, retry_after?: int}
     */
    public function get(): array
    {
        $response = $this->http()->get('/api/v1/chat/events', $this->params);
        $retryAfter = $response->header('Retry-After');

        return $this->result($response) + (is_numeric($retryAfter) ? ['retry_after' => (int) $retryAfter] : []);
    }

    /** O cursor atual (para começar a acompanhar a partir de agora). */
    public function latest(): int
    {
        return (int) ($this->result($this->http()->get('/api/v1/chat/events', ['after' => 'latest']))['next'] ?? 0);
    }
}
