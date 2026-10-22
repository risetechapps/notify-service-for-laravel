<?php

namespace RiseTechApps\Notify\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Envio que falhou por um motivo passageiro (servidor fora, timeout, 5xx,
 * 429, 409): lançada para a fila do Laravel tentar de novo. Falhas que
 * repetir não resolve (validação, plano) não lançam; o canal devolve o corpo
 * do erro. Ver notify.retry_failures.
 *
 * $status é 0 quando nem houve resposta (erro de conexão).
 */
class NotifyDeliveryException extends RuntimeException
{
    public function __construct(
        public readonly string $channel,
        public readonly int $status,
        public readonly array $response = [],
        ?Throwable $previous = null,
    ) {
        $reason = $status > 0 ? "HTTP {$status}" : ($previous?->getMessage() ?? 'sem resposta');

        parent::__construct("Notify: falha passageira no envio de {$channel} ({$reason}).", 0, $previous);
    }

    /** Status em que vale tentar de novo: o servidor não chegou a enviar. */
    public static function isRetryable(int $status): bool
    {
        return $status >= 500 || in_array($status, [408, 409, 429], true);
    }
}
