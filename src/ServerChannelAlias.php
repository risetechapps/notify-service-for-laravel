<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Aliases de canal de uma configuração (Telegram/Slack) — nomes lógicos → destino real.
 * Obtido via NotifyQuery::server()->{telegram|slack}()->config('uuid')->channels().
 * Recurso: /api/v1/configurations/{id}/channels.
 *
 * No envio, basta mandar o nome lógico (->chatId('equipe') / ->channel('equipe')); se não
 * for um alias cadastrado, o valor é usado como está (fallthrough).
 */
class ServerChannelAlias
{
    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $configId = null,
    ) {}

    /**
     * Lista os aliases da config (mapa nome => destino).
     * GET /api/v1/configurations/{id}/channels.
     */
    public function all(): array
    {
        if ($this->configId === null) {
            return [];
        }

        return $this->http()->get("/api/v1/configurations/{$this->configId}/channels")->json('data', []);
    }

    /**
     * Cria ou atualiza um alias (idempotente). POST /api/v1/configurations/{id}/channels.
     *
     * @param  string  $name  Nome lógico (slug: sem espaço/barra), ex.: "equipe".
     * @param  string  $target  Destino real (chat_id no Telegram; ID C0.. ou #canal no Slack).
     */
    public function set(string $name, string $target): array
    {
        if ($this->configId === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        return $this->http()->post("/api/v1/configurations/{$this->configId}/channels", [
            'name' => $name,
            'target' => $target,
        ])->json() ?? [];
    }

    /**
     * Remove um alias. DELETE /api/v1/configurations/{id}/channels/{name}.
     * Retorna 404 (no http) se o name não existir.
     */
    public function remove(string $name): array
    {
        if ($this->configId === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        $response = $this->http()->delete("/api/v1/configurations/{$this->configId}/channels/{$name}");

        return ($response->json() ?? []) + ['http' => $response->status()];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
