<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RiseTechApps\Notify\Channel\NotifyChannelSlack;
use RiseTechApps\Notify\Channel\NotifyChannelSms;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Exceptions\NotifyDeliveryException;
use RiseTechApps\Notify\Message\NotifySlack;
use RiseTechApps\Notify\Message\NotifySms;
use RiseTechApps\Notify\Message\NotifyWhatsApp;
use RiseTechApps\Notify\Notify;

function notifyQueuedWith(string $channel, callable $build): Notification
{
    return new class($channel, $build) extends Notification implements ShouldQueue
    {
        public function __construct(public string $channel, public $build) {}

        public function via($notifiable): array
        {
            return ["notify.{$this->channel}"];
        }

        public function __call($method, $args)
        {
            return ($this->build)($args[0] ?? null);
        }
    };
}

function notifyWith(string $channel, callable $build): Notification
{
    return new class($channel, $build) extends Notification
    {
        public function __construct(public string $channel, public $build) {}

        public function via($notifiable): array
        {
            return ["notify.{$this->channel}"];
        }

        public function __call($method, $args)
        {
            return ($this->build)($args[0] ?? null);
        }
    };
}

it('WhatsApp: texto pelo número do notifiable, com o config_id padrão e o webhook do config', function () {
    config(['notify.whatsapp.config_id' => 'Loja', 'notify.webhook' => 'https://app.test/notify/webhook']);
    Http::fake([Notify::BASE_URL.'/api/v1/send/whatsapp' => Http::response(['status' => true, 'notification_id' => 'n-1'], 202)]);
    Event::fake([NotifySentEvent::class]);

    NotificationFacade::route('whatsapp', '5521999998888')
        ->notify(notifyWith('whatsapp', fn () => (new NotifyWhatsApp)->content('Pedido *#1042* saiu.')->tag('pedido')));

    Http::assertSent(fn ($r) => $r->url() === Notify::BASE_URL.'/api/v1/send/whatsapp'
        && $r->header('X-API-KEY')[0] === 'test-api-key'
        && $r->data() === ['to' => '5521999998888', 'content' => 'Pedido *#1042* saiu.', 'tag' => ['pedido'], 'config_id' => 'Loja', 'webhook_url' => 'https://app.test/notify/webhook']);
    Event::assertDispatched(NotifySentEvent::class, fn ($e) => $e->channel === 'whatsapp');
});

it('WhatsApp: documento com legenda e enquete (sem texto)', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true], 202)]);

    NotificationFacade::route('whatsapp', '5521999998888')
        ->notify(notifyWith('whatsapp', fn () => (new NotifyWhatsApp)->document('https://cdn.test/b.pdf', 'boleto.pdf')->content('Segue')));
    NotificationFacade::route('whatsapp', '5521999998888')
        ->notify(notifyWith('whatsapp', fn () => (new NotifyWhatsApp)->content('ignorado')->poll('Confirma?', ['Sim', 'Não'], multiple: true)));

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();
    expect($sent[0])->toMatchArray(['media_url' => 'https://cdn.test/b.pdf', 'media_type' => 'document', 'file_name' => 'boleto.pdf', 'content' => 'Segue'])
        ->and($sent[1])->toMatchArray(['poll' => ['question' => 'Confirma?', 'options' => ['Sim', 'Não'], 'multiple' => true]])
        ->and($sent[1])->not->toHaveKey('content');

    expect(fn () => (new NotifyWhatsApp)->media('https://x', 'gif'))->toThrow(InvalidArgumentException::class);
});

it('opções de envio: agendamento, failover, modelo salvo, Idempotency-Key e modo de teste', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true], 202)]);

    NotificationFacade::route('sms', '5521999998888')->notify(notifyWith('sms', fn () => (new NotifySms)
        ->template('Pedido enviado', ['pedido' => '#1042'])
        ->scheduledAt('2026-12-01 09:00', 'America/Sao_Paulo')
        ->configId('Twilio')->failover()
        ->idempotencyKey('pedido-1042')
        ->test()));

    Http::assertSent(fn ($r) => $r['template_id'] === 'Pedido enviado'
        && $r['variables'] === ['pedido' => '#1042']
        && $r['scheduled_at'] === '2026-12-01 09:00' && $r['timezone'] === 'America/Sao_Paulo'
        && $r['failover'] === true && $r['config_id'] === 'Twilio'
        && $r->header('Idempotency-Key')[0] === 'pedido-1042'
        && $r->header('X-Notify-Test')[0] === 'true');
});

it('Slack completa o canal pelo notifiable; erro do servidor vira NotifyFailedEvent e devolve o corpo', function () {
    Http::fake([Notify::BASE_URL.'/api/v1/send/slack' => Http::response(['status' => false, 'message' => 'O plano não inclui Slack.'], 402)]);
    Event::fake([NotifyFailedEvent::class]);

    $notifiable = NotificationFacade::route('slack', '#vendas');
    $result = (new NotifyChannelSlack)->send($notifiable, notifyWith('slack', fn () => (new NotifySlack)->message('Nova venda')));

    Http::assertSent(fn ($r) => $r['channel'] === '#vendas');
    expect($result)->toBe(['status' => false, 'message' => 'O plano não inclui Slack.']);
    Event::assertDispatched(NotifyFailedEvent::class, fn ($e) => $e->channel === 'slack');
});

it('WhatsApp: o ->to() da mensagem vence o número do notifiable', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true], 202)]);

    NotificationFacade::route('whatsapp', '5521999998888')
        ->notify(notifyWith('whatsapp', fn () => (new NotifyWhatsApp)->to('5521977776666')->content('Para o financeiro')));

    Http::assertSent(fn ($r) => $r['to'] === '5521977776666');
});

it('falha de envio vai ao log sem os objetos do notifiable e da notificação', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => false, 'message' => 'Erro'], 422)]);
    Log::spy();

    NotificationFacade::route('sms', '5521999998888')
        ->notify(notifyWith('sms', fn () => (new NotifySms)->content('Oi')));

    Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $context['channel'] === 'sms'
        && $context['http'] === 422
        && is_string($context['notifiable'])
        && is_string($context['notification']))->once();
});

it('na fila, falha passageira (5xx, 429, 409) lança NotifyDeliveryException para tentar de novo', function (int $status) {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['message' => 'Indisponível'], $status)]);
    Event::fake([NotifyFailedEvent::class]);

    $send = fn () => (new NotifyChannelSms)->send(NotificationFacade::route('sms', '5521999998888'), notifyQueuedWith('sms', fn () => (new NotifySms)->content('Oi')));

    expect($send)->toThrow(fn (NotifyDeliveryException $e) => expect($e->status)->toBe($status)
        ->and($e->channel)->toBe('sms')
        ->and($e->response)->toBe(['message' => 'Indisponível']));
    Event::assertDispatched(NotifyFailedEvent::class);
})->with([500, 503, 429, 409]);

it('na fila, servidor fora do ar também lança, com o erro de conexão como causa', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

    expect(fn () => (new NotifyChannelSms)->send(NotificationFacade::route('sms', '5521999998888'), notifyQueuedWith('sms', fn () => (new NotifySms)->content('Oi'))))
        ->toThrow(fn (NotifyDeliveryException $e) => expect($e->status)->toBe(0)
            ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class));
});

it('validação e plano não lançam nem na fila: repetir não resolve', function (int $status) {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['message' => 'Recusado'], $status)]);

    $result = (new NotifyChannelSms)->send(NotificationFacade::route('sms', '5521999998888'), notifyQueuedWith('sms', fn () => (new NotifySms)->content('Oi')));

    expect($result)->toBe(['message' => 'Recusado']);
})->with([400, 402, 403, 422]);

it('envio síncrono não lança: devolve o erro, como antes', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['message' => 'Indisponível'], 503)]);
    $channel = new NotifyChannelSms;
    $notifiable = NotificationFacade::route('sms', '5521999998888');

    expect($channel->send($notifiable, notifyWith('sms', fn () => (new NotifySms)->content('Oi'))))->toBe(['message' => 'Indisponível']);

    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));
    expect($channel->send($notifiable, notifyWith('sms', fn () => (new NotifySms)->content('Oi'))))->toBe(['error' => 'cURL error 28: timeout']);
});

it('com retry_failures desligado, nem a fila lança', function () {
    config(['notify.retry_failures' => false]);
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['message' => 'Indisponível'], 503)]);

    $result = (new NotifyChannelSms)->send(NotificationFacade::route('sms', '5521999998888'), notifyQueuedWith('sms', fn () => (new NotifySms)->content('Oi')));

    expect($result)->toBe(['message' => 'Indisponível']);
});

it('Idempotency-Key automática: igual na nova tentativa, diferente para outro destino; a da mensagem vence', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true], 202)]);
    $channel = new NotifyChannelSms;
    $notification = notifyWith('sms', fn () => (new NotifySms)->content('Oi'));
    $notification->id = 'n-1';

    $channel->send(NotificationFacade::route('sms', '5521999998888'), $notification);
    $channel->send(NotificationFacade::route('sms', '5521999998888'), $notification); // nova tentativa
    $channel->send(NotificationFacade::route('sms', '5521977776666'), $notification); // outro destino

    $withKey = notifyWith('sms', fn () => (new NotifySms)->content('Oi')->idempotencyKey('pedido-1042'));
    $withKey->id = 'n-2';
    $channel->send(NotificationFacade::route('sms', '5521999998888'), $withKey);

    $keys = Http::recorded()->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0] ?? null)->values();
    expect($keys[0])->toStartWith('notify:')
        ->and($keys[1])->toBe($keys[0])
        ->and($keys[2])->not->toBe($keys[0])
        ->and($keys[3])->toBe('pedido-1042');
});
