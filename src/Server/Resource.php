<?php

namespace RiseTechApps\Notify\Server;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Notify;

/**
 * Base dos recursos da API do servidor: chave de API, cabeçalhos extras
 * (ex.: X-Agent-Id do chat) e o formato de retorno.
 *
 * Retorno: o JSON do servidor como array. Em erro, o mesmo JSON com `http`
 * (o código HTTP), para quem chama decidir (ex.: 429 da proteção do número,
 * 422 de validação com `errors`).
 */
abstract class Resource
{
    protected string $apiUrl;

    protected string $apiKey;

    public function __construct(protected array $headers = [])
    {
        $this->apiUrl = Notify::BASE_URL;
        $this->apiKey = (string) config('notify.key', '');
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey, ...$this->headers])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }

    protected function result(Response $response): array
    {
        $json = $response->json();
        $json = is_array($json) ? $json : ['body' => $response->body()];

        return $response->successful() ? $json : $json + ['http' => $response->status()];
    }

    /** Parâmetros sem os vazios (null). */
    protected static function filled(array $params): array
    {
        return array_filter($params, fn ($value) => $value !== null);
    }
}
