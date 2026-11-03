<?php

namespace RiseTechApps\Notify\Server;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RiseTechApps\Notify\Notify;
use RiseTechApps\Notify\Support\NotifyHttp;

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
        return NotifyHttp::client(['X-API-KEY' => $this->apiKey, ...$this->headers])
            ->baseUrl($this->apiUrl);
    }

    protected function result(Response $response): array
    {
        $json = $response->json();
        $json = is_array($json) ? $json : ['body' => $response->body()];

        return $response->successful() ? $json : $json + ['http' => $response->status()];
    }

    /**
     * Resultado guardado por notify.cache.ttl segundos (0 desliga). Só guarda
     * sucesso. A chave separa por conta (chave de API) e por quem pede (os
     * cabeçalhos do atendente: um admin vê mais números que um agente).
     */
    protected function remember(string $key, callable $fetch, bool $fresh = false): array
    {
        $ttl = (int) config('notify.cache.ttl', 30);

        if ($ttl <= 0) {
            return $fetch();
        }

        $cacheKey = $this->cacheKey($key, perActor: true);

        if (! $fresh && is_array($hit = self::cache()->get($cacheKey))) {
            return $hit;
        }

        $result = $fetch();

        if (! isset($result['http'])) {
            self::cache()->put($cacheKey, $result, $ttl);
            $this->trackKey($key, $cacheKey, $ttl);
        }

        return $result;
    }

    /** Esquece o que foi guardado com remember($key), para todos que pediram. */
    protected function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            $index = $this->cacheKey($key, perActor: false);

            foreach ((array) self::cache()->pull($index, []) as $cacheKey) {
                self::cache()->forget($cacheKey);
            }
        }
    }

    /** Guarda as chaves por atendente de cada recurso, para o forget achar todas. */
    private function trackKey(string $key, string $cacheKey, int $ttl): void
    {
        $index = $this->cacheKey($key, perActor: false);
        $keys = (array) self::cache()->get($index, []);

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            self::cache()->put($index, $keys, $ttl);
        }
    }

    private function cacheKey(string $key, bool $perActor): string
    {
        $account = substr(hash('sha256', $this->apiKey), 0, 16);

        if (! $perActor) {
            return "notify:{$account}:{$key}:index";
        }

        $actor = $this->headers;
        ksort($actor);

        return "notify:{$account}:{$key}:".substr(hash('sha256', serialize($actor)), 0, 16);
    }

    private static function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('notify.cache.store'));
    }

    /** Parâmetros sem os vazios (null). */
    protected static function filled(array $params): array
    {
        return array_filter($params, fn ($value) => $value !== null);
    }
}
