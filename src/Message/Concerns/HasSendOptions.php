<?php

namespace RiseTechApps\Notify\Message\Concerns;

use DateTimeInterface;

/**
 * Opções de envio que o servidor aceita em todo canal:
 *
 *   ->scheduledAt('2026-12-01 09:00', 'America/Sao_Paulo')  agenda (até 30 dias)
 *   ->failover()            com configId(), usa as outras configurações do canal se ela falhar
 *   ->idempotencyKey('pedido-1042')  repetir em até 24 h devolve a resposta original
 *   ->test()                valida e registra sem enviar nem gastar cota (X-Notify-Test)
 *
 * O canal mescla sendOptions() no corpo e requestHeaders() nos cabeçalhos.
 */
trait HasSendOptions
{
    protected ?string $scheduledAt = null;
    protected ?string $scheduleTimezone = null;
    protected bool $failover = false;
    protected ?string $idempotencyKey = null;
    protected bool $testMode = false;

    /** Envia mais tarde. Sem fuso, a data é lida no fuso informado (ou no do servidor). */
    public function scheduledAt(DateTimeInterface|string $at, ?string $timezone = null): static
    {
        $this->scheduledAt = $at instanceof DateTimeInterface ? $at->format(DateTimeInterface::ATOM) : $at;
        $this->scheduleTimezone = $timezone;

        return $this;
    }

    /** Com configId(), as demais configurações do canal entram como reserva. */
    public function failover(bool $failover = true): static
    {
        $this->failover = $failover;

        return $this;
    }

    /** Repetir a mesma chave em até 24 h devolve a resposta original, sem enviar de novo. */
    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /** Modo de teste: passa pela validação e pelo histórico, mas não chama o provedor. */
    public function test(bool $test = true): static
    {
        $this->testMode = $test;

        return $this;
    }

    public function sendOptions(): array
    {
        return array_filter([
            'scheduled_at' => $this->scheduledAt,
            'timezone' => $this->scheduledAt ? $this->scheduleTimezone : null,
            'failover' => $this->failover ?: null,
        ], fn ($value) => $value !== null);
    }

    public function requestHeaders(): array
    {
        return array_filter([
            'Idempotency-Key' => $this->idempotencyKey,
            'X-Notify-Test' => $this->testMode ? 'true' : null,
        ], fn ($value) => $value !== null);
    }
}
