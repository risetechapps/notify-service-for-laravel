<?php

namespace RiseTechApps\Notify\Server;

/**
 * Equipe da conta. Os atendentes do SEU sistema entram pelo id deles lá
 * (external_id), sem login no painel; contam no limite de equipe do plano.
 *
 *   NotifyQuery::server()->team()->members();
 *   NotifyQuery::server()->team()->syncAgent($user->id, $user->name);
 *
 * Também dá para nem cadastrar: chat()->as($id, $nome) cadastra no primeiro uso.
 */
class Team extends Resource
{
    /** Dono e membros: id, external_id, name, role (owner | admin | agent). */
    public function members(): array
    {
        return $this->result($this->http()->get('/api/v1/team/members'));
    }

    /** Cadastra (ou atualiza) um atendente do seu sistema. role: agent (padrão) | admin. */
    public function syncAgent(string|int $externalId, string $name, ?string $role = null): array
    {
        return $this->result($this->http()->post('/api/v1/team/agents', self::filled([
            'external_id' => (string) $externalId,
            'name' => $name,
            'role' => $role,
        ])));
    }

    public function updateAgent(string|int $externalId, ?string $name = null, ?string $role = null): array
    {
        return $this->result($this->http()->put('/api/v1/team/agents/'.rawurlencode((string) $externalId), self::filled(['name' => $name, 'role' => $role])));
    }

    /** Remove o atendente; as conversas dele voltam para a fila. */
    public function removeAgent(string|int $externalId): array
    {
        return $this->result($this->http()->delete('/api/v1/team/agents/'.rawurlencode((string) $externalId)));
    }
}
