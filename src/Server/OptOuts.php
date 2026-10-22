<?php

namespace RiseTechApps\Notify\Server;

/**
 * Números fora das campanhas (quem respondeu SAIR, ou bloqueado por você),
 * por canal: sms (padrão) ou whatsapp.
 */
class OptOuts extends Resource
{
    public function all(string $channel = 'sms', ?string $search = null): array
    {
        return $this->result($this->http()->get('/api/v1/sms-opt-outs', self::filled(['channel' => $channel, 'search' => $search])));
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
