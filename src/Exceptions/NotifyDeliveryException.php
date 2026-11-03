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

    /**
     * Motivos da proteção do número de WhatsApp (429 com `reason`). Repetir em
     * segundos não resolve: o limite do dia só volta no dia seguinte, a pausa
     * só com resumeProtection(), a mensagem repetida só personalizando o texto.
     */
    public const PROTECTION_REASONS = ['paused', 'daily_limit', 'new_contacts', 'duplicate', 'undelivered', 'invalid_numbers', 'opt_outs'];

    /**
     * Status em que vale tentar de novo: o servidor não chegou a enviar. O 429
     * da proteção do número (com `reason`) fica de fora; ver PROTECTION_REASONS.
     */
    public static function isRetryable(int $status, array $response = []): bool
    {
        if ($status === 429 && self::protectionReason($response) !== null) {
            return false;
        }

        return $status >= 500 || in_array($status, [408, 409, 429], true);
    }

    /** O motivo da recusa pela proteção do número, ou null se não for isso. */
    public static function protectionReason(array $response): ?string
    {
        $reason = $response['reason'] ?? null;

        return is_string($reason) && in_array($reason, self::PROTECTION_REASONS, true) ? $reason : null;
    }
}
