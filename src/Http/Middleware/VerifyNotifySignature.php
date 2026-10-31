<?php

namespace RiseTechApps\Notify\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida a assinatura HMAC-SHA256 dos callbacks enviados pelo servidor.
 *
 * Todo webhook do servidor (webhook_url da requisição, do número, da conversa
 * e os webhooks da conta) sai no formato do api-key:
 *
 *   X-Webhook-Id:        evt_…  (igual num reenvio)
 *   X-Webhook-Event:     notification.sent
 *   X-Webhook-Timestamp: 1750000000
 *   X-Webhook-Signature: sha256=<hex>   HMAC de "{timestamp}.{corpo bruto}"
 *
 * Segredo: o da conta (painel → Configurações → Assinatura dos webhooks) no
 * webhook_url, no número e na conversa; o de cada endpoint nos webhooks da
 * conta (painel → Webhooks).
 *
 * O formato do servidor 1.0.0 (X-Notify-Signature: t=…,v1=…, HMAC de
 * "{t}.{corpo bruto}") segue aceito durante a transição.
 *
 * A validação usa o corpo BRUTO da requisição ($request->getContent()). Nunca
 * reserialize o JSON (json_encode($request->all())) — a ordem das chaves e o escape
 * de "/" e unicode mudam os bytes e a assinatura nunca confere.
 *
 * Configuração:
 *   notify.webhook_secret     segredo(s), separados por vírgula: o da conta e
 *                             o de cada endpoint da conta; qualquer um que
 *                             confira vale (também serve para rotação)
 *   notify.webhook_verify     true (default) valida; false desliga a validação
 *   notify.webhook_tolerance  janela anti-replay em segundos (default 300)
 */
class VerifyNotifySignature
{
    public const HEADER = 'X-Webhook-Signature';

    public const TIMESTAMP_HEADER = 'X-Webhook-Timestamp';

    /** Header do servidor 1.0.0. */
    public const LEGACY_HEADER = 'X-Notify-Signature';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('notify.webhook_verify', true)) {
            return $next($request);
        }

        $secrets = self::secrets();

        if ($secrets === []) {
            Log::error('Notify: webhook recebido mas notify.webhook_secret não está configurado. Defina NOTIFY_SERVICE_WEBHOOK_SECRET ou desligue a validação com notify.webhook_verify=false.');

            return $this->deny('Webhook secret not configured');
        }

        $body = $request->getContent();
        $header = $request->header(self::HEADER);

        if (is_string($header) && $header !== '') {
            $timestamp = $request->header(self::TIMESTAMP_HEADER);

            if (! preg_match('/^sha256=([0-9a-fA-F]{64})$/', trim($header), $m) || ! is_string($timestamp) || ! preg_match('/^\d+$/', $timestamp)) {
                return $this->deny('Malformed signature header');
            }

            if (! $this->withinTolerance((int) $timestamp)) {
                return $this->deny('Signature timestamp outside tolerance');
            }

            return $this->matches($timestamp.'.'.$body, [strtolower($m[1])], $secrets)
                ? $next($request)
                : $this->deny('Invalid signature');
        }

        $legacy = $request->header(self::LEGACY_HEADER);

        if (! is_string($legacy) || $legacy === '') {
            return $this->deny('Missing signature header');
        }

        [$timestamp, $signatures] = $this->parse($legacy);

        if ($timestamp === null || $signatures === []) {
            return $this->deny('Malformed signature header');
        }

        if (! $this->withinTolerance($timestamp)) {
            return $this->deny('Signature timestamp outside tolerance');
        }

        return $this->matches($timestamp.'.'.$body, $signatures, $secrets)
            ? $next($request)
            : $this->deny('Invalid signature');
    }

    /** Segredos configurados: string separada por vírgula ou array. */
    public static function secrets(): array
    {
        $value = config('notify.webhook_secret', '');
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn ($s) => trim((string) $s), $list), fn ($s) => $s !== ''));
    }

    protected function matches(string $material, array $signatures, array $secrets): bool
    {
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $material, $secret);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function withinTolerance(int $timestamp): bool
    {
        $tolerance = (int) config('notify.webhook_tolerance', 300);

        return $tolerance <= 0 || abs(time() - $timestamp) <= $tolerance;
    }

    /**
     * Extrai o timestamp e as assinaturas v1 do header.
     *
     * Aceita mais de um v1 no mesmo header (útil durante rotação de segredo):
     * "t=1750000000,v1=abc...,v1=def...".
     *
     * @return array{0: int|null, 1: array<int, string>}
     */
    protected function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = [trim($pair[0]), trim($pair[1])];

            if ($key === 't' && preg_match('/^\d+$/', $value)) {
                $timestamp = (int) $value;

                continue;
            }

            if ($key === 'v1' && preg_match('/^[0-9a-fA-F]{64}$/', $value)) {
                $signatures[] = strtolower($value);
            }
        }

        return [$timestamp, $signatures];
    }

    protected function deny(string $reason): JsonResponse
    {
        return new JsonResponse(['message' => $reason], 403);
    }
}
