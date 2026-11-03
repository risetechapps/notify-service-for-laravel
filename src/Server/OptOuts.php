<?php

namespace RiseTechApps\Notify\Server;

/**
 * Números fora das campanhas (quem respondeu SAIR, ou bloqueado por você),
 * por canal: sms (padrão) ou whatsapp.
 */
class OptOuts extends Resource
{
    /** Números fora das campanhas do canal; $search: telefone inteiro ou parte. Paginado (25 por página, até 100). */
    public function all(string $channel = 'sms', ?string $search = null, ?int $page = null, ?int $perPage = null): array
    {
        return $this->result($this->http()->get('/api/v1/sms-opt-outs', self::filled(['channel' => $channel, 'search' => $search, 'page' => $page, 'per_page' => $perPage])));
    }

    /** Bloqueia números (não recebem mais campanhas do canal). */
    public function add(array $phones, string $channel = 'sms'): array
    {
        return $this->result($this->http()->post('/api/v1/sms-opt-outs', ['phones' => array_values($phones), 'channel' => $channel]));
    }

    /** Libera um número (id do registro). */
    public function remove(string $id): array
    {
        return $this->result($this->http()->delete("/api/v1/sms-opt-outs/{$id}"));
    }
}
