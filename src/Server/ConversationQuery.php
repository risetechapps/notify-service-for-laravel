<?php

namespace RiseTechApps\Notify\Server;

/**
 * Lista de conversas.
 *
 *   ->status('queue')   queue (sem responsável) | mine | all | active | bot | open | closed
 *   ->search('ana')     nome ou telefone do contato
 *   ->number($configId) só de um número
 */
class ConversationQuery extends Resource
{
    protected array $params = [];

    public function status(string $status): static
    {
        $this->params['status'] = $status;

        return $this;
    }

    public function search(string $search): static
    {
        $this->params['search'] = $search;

        return $this;
    }

    public function number(string $configurationId): static
    {
        $this->params['configuration_id'] = $configurationId;

        return $this;
    }

    /** Só as que mudaram depois deste instante (o `server_time` da resposta anterior). */
    public function updatedSince(string $serverTime): static
    {
        $this->params['updated_since'] = $serverTime;

        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;

        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;

        return $this;
    }

    /** { data, meta, counts: { queue, mine, bot }, unread, me, server_time } */
    public function get(): array
    {
        return $this->result($this->http()->get('/api/v1/chat/conversations', $this->params));
    }
}
