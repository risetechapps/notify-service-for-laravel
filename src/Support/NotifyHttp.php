<?php

namespace RiseTechApps\Notify\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP de todas as chamadas ao notify-server: tempo limite (para a
 * tela de quem chama não travar com o servidor lento) e nova tentativa curta.
 *
 * Só leituras (GET/HEAD) tentam de novo, e só em falha de conexão ou erro 5xx.
 * Envio, resposta no chat, transferência etc. nunca repetem sozinhos: uma
 * segunda tentativa poderia mandar a mensagem duas vezes. 4xx (validação, 429
 * da proteção do número) volta na hora para quem chamou decidir.
 *
 * Configuração: notify.http (timeout, connect_timeout, upload_timeout,
 * retries, retry_sleep).
 */
final class NotifyHttp
{
    public static function client(array $headers = []): PendingRequest
    {
        $request = Http::withHeaders($headers)
            ->acceptJson()
            ->timeout(max(1, (int) config('notify.http.timeout', 20)))
            ->connectTimeout(max(1, (int) config('notify.http.connect_timeout', 5)));

        $retries = (int) config('notify.http.retries', 2);

        if ($retries > 0) {
            $request->retry(
                $retries + 1,
                max(0, (int) config('notify.http.retry_sleep', 200)),
                static fn (Throwable $e, PendingRequest $r, ?string $method = null) => self::shouldRetry($e, $method),
                throw: false,
            );
        }

        return $request;
    }

    /** Para envio de arquivo: o mesmo cliente, com o tempo limite de upload. */
    public static function upload(PendingRequest $request): PendingRequest
    {
        return $request->timeout(max(1, (int) config('notify.http.upload_timeout', 120)));
    }

    public static function shouldRetry(Throwable $exception, ?string $method): bool
    {
        if (! in_array(strtoupper((string) $method), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException && $exception->response->serverError();
    }
}
