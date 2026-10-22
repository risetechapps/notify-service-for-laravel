<?php

namespace RiseTechApps\Notify\Server;

/**
 * Fluxos de autoatendimento. O formato do `definition` (menu, palavras-chave,
 * horário, ações reply/menu/flow/handoff/close, anexos) está no guia
 * /docs/whatsapp do servidor. O número usa um fluxo principal
 * (bot_flow_id nas configurações do chat); os outros entram como subfluxos.
 */
class BotFlows extends Resource
{
    public function all(): array
    {
        return $this->result($this->http()->get('/api/v1/chat/bot-flows'));
    }

    public function get(string $id): array
    {
        return $this->result($this->http()->get("/api/v1/chat/bot-flows/{$id}"));
    }

    public function create(string $name, array $definition): array
    {
        return $this->result($this->http()->post('/api/v1/chat/bot-flows', ['name' => $name, 'definition' => $definition]));
    }

    public function update(string $id, string $name, array $definition): array
    {
        return $this->result($this->http()->put("/api/v1/chat/bot-flows/{$id}", ['name' => $name, 'definition' => $definition]));
    }

    /** Remove o fluxo (recusado se outro fluxo abre este). */
    public function delete(string $id): array
    {
        return $this->result($this->http()->delete("/api/v1/chat/bot-flows/{$id}"));
    }

    /**
     * Anexo de resposta do fluxo. Devolve o `media` ({ path, mime, file_name, … })
     * para pôr na ação: ['type' => 'reply', 'text' => 'Segue', 'media' => $media].
     */
    public function uploadMedia(string $path, ?string $fileName = null): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("Arquivo não encontrado ou sem leitura: {$path}");
        }

        return $this->result($this->http()->attach('file', file_get_contents($path), $fileName ?? basename($path))->post('/api/v1/chat/bot-flows/media'));
    }

    /**
     * Simula o fluxo sem enviar nada: mande o `state` devolvido na chamada
     * anterior (null para começar) e o que o contato escreveu.
     *
     * @return array{data: array{replies: array, state: array, action: ?string}}
     */
    public function simulate(array $definition, ?array $state, ?string $input): array
    {
        return $this->result($this->http()->post('/api/v1/chat/bot-flows/simulate', ['definition' => $definition, 'state' => $state, 'input' => $input]));
    }
}
