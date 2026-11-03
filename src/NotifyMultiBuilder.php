<?php

namespace RiseTechApps\Notify;

use RiseTechApps\Notify\Support\MessageChannel;
use RiseTechApps\Notify\Support\NotifyHttp;

/**
 * Envio para vários canais numa requisição (POST /api/v1/send/multi, até 10).
 *
 *   NotifyQuery::multi()
 *       ->add((new NotifySms)->to('5521999998888')->content('Pedido saiu'))
 *       ->add((new NotifyMail)->to('ana@x.com')->subject('Pedido')->line('Saiu'), webhookUrl: 'https://…')
 *       ->tag('pedido-1042')
 *       ->scheduledAt('2026-12-01 09:00', 'America/Sao_Paulo')
 *       ->send();
 *
 * Cada mensagem leva o config_id, o webhook_url e o failover dela para o
 * canal. Também aceita a lista crua: [['channel' => 'sms', 'data' => [...]], ...].
 */
class NotifyMultiBuilder
{
    protected array $tags = [];

    protected ?string $scheduledAt = null;

    protected ?string $timezone = null;

    protected bool $testMode = false;

    protected ?string $idempotencyKey = null;

    public function __construct(
        protected array $channels = [],
        protected ?string $webhookUrl = null,
    ) {}

    /** Acrescenta uma mensagem (NotifySms, NotifyMail…) ou um item cru {channel, data}. */
    public function add(array|object $message, ?string $webhookUrl = null): static
    {
        $this->channels[] = is_array($message) ? $message : self::entry($message, $webhookUrl);

        return $this;
    }

    /** Callback de status para todos os canais (o de cada mensagem vence). */
    public function webhookUrl(string $url): static
    {
        $this->webhookUrl = $url;

        return $this;
    }

    public function tag(string|array $tag): static
    {
        $this->tags = array_values(array_unique(array_merge($this->tags, (array) $tag)));

        return $this;
    }

    public function scheduledAt(\DateTimeInterface|string $at, ?string $timezone = null): static
    {
        $this->scheduledAt = $at instanceof \DateTimeInterface ? $at->format(DATE_ATOM) : $at;
        $this->timezone = $timezone;

        return $this;
    }

    /** Modo de teste (X-Notify-Test): valida e registra sem chamar o provedor. */
    public function test(bool $test = true): static
    {
        $this->testMode = $test;

        return $this;
    }

    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'channels' => array_map(fn ($c) => is_object($c) ? self::entry($c, null) : $c, $this->channels),
            'webhook_url' => $this->webhookUrl,
            'tag' => $this->tags ?: null,
            'scheduled_at' => $this->scheduledAt,
            'timezone' => $this->scheduledAt ? $this->timezone : null,
        ], fn ($v) => $v !== null);
    }

    public function send(): array
    {
        try {
            $response = NotifyHttp::client(array_filter([
                'X-API-KEY' => config('notify.key'),
                'Idempotency-Key' => $this->idempotencyKey,
                'X-Notify-Test' => $this->testMode ? 'true' : null,
            ]))
                ->post(Notify::BASE_URL.'/api/v1/send/multi', $this->toArray());

            if ($response->failed()) {
                return $response->json() ?: ['error' => $response->body()];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            report($e);

            return ['error' => $e->getMessage()];
        }
    }

    /** Item do multicanal a partir de uma mensagem: config_id, webhook_url e failover saem do data. */
    protected static function entry(object $message, ?string $webhookUrl): array
    {
        $data = MessageChannel::data($message);
        $options = method_exists($message, 'sendOptions') ? $message->sendOptions() : [];

        return array_filter([
            'channel' => MessageChannel::of($message),
            'config_id' => $data['config_id'] ?? null,
            'webhook_url' => $webhookUrl ?? $data['webhook_url'] ?? null,
            'failover' => ! empty($options['failover']) ? true : null,
            'data' => array_diff_key($data, array_flip(['config_id', 'webhook_url'])),
        ], fn ($v) => $v !== null);
    }
}
