<?php

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifySlack;
use RiseTechApps\Notify\Message\NotifySms;
use RiseTechApps\Notify\Message\NotifyWhatsApp;
use RiseTechApps\Notify\Notify;

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
    $result = (new \RiseTechApps\Notify\Channel\NotifyChannelSlack)->send($notifiable, notifyWith('slack', fn () => (new NotifySlack)->message('Nova venda')));

    Http::assertSent(fn ($r) => $r['channel'] === '#vendas');
    expect($result)->toBe(['status' => false, 'message' => 'O plano não inclui Slack.']);
    Event::assertDispatched(NotifyFailedEvent::class, fn ($e) => $e->channel === 'slack');
});
