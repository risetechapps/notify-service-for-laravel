<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RiseTechApps\Notify\Console\WhatsAppEventsCommand;
use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWhatsAppEvent;
use RiseTechApps\Notify\Http\Controllers\NotifyWebhookController;
use RiseTechApps\Notify\Notify;
use RiseTechApps\Notify\NotifyQuery;

beforeEach(fn () => Cache::flush());

it('número: cria pelo provedor da plataforma e lê estado e proteção pelo id', function () {
    Http::fake([
        Notify::BASE_URL.'/api/v1/configurations' => Http::response(['status' => true, 'data' => ['id' => 'cfg-1']], 201),
        Notify::BASE_URL.'/api/v1/configurations/cfg-1/whatsapp' => Http::response(['state' => 'connecting', 'qrcode' => 'data:image/png;base64,AA']),
        Notify::BASE_URL.'/api/v1/configurations/cfg-1/whatsapp/protection' => Http::response(['message' => 'Sem permissão'], 403),
    ]);

    expect(NotifyQuery::server()->whatsappNumber()->create('Atendimento', true)['data']['id'])->toBe('cfg-1')
        ->and(NotifyQuery::server()->whatsappNumber('cfg-1')->state()['state'])->toBe('connecting')
        ->and(NotifyQuery::server()->whatsappNumber('cfg-1')->protection())->toBe(['message' => 'Sem permissão', 'http' => 403]);

    Http::assertSent(fn ($r) => $r->url() === Notify::BASE_URL.'/api/v1/configurations' && $r->method() === 'POST'
        && $r->data() === ['channel' => 'whatsapp', 'driver' => 'evolution', 'label' => 'Atendimento', 'credentials' => [], 'is_default' => true]);

    expect(fn () => NotifyQuery::server()->whatsappNumber()->state())->toThrow(InvalidArgumentException::class);
});

it('chat: as() manda X-Agent-Id/Name; asMember() manda agent_id no corpo', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true])]);

    $chat = NotifyQuery::server()->chat();
    $chat->as(42, 'Ana')->conversation('c-1')->reply('Bom dia!');
    $chat->asMember('m-9')->conversation('c-1')->assign(null);
    $chat->conversation('c-1')->close();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0])->values();

    expect($sent[0]->url())->toBe(Notify::BASE_URL.'/api/v1/chat/conversations/c-1/messages')
        ->and($sent[0]->header('X-Agent-Id'))->toBe(['42'])
        ->and($sent[0]->header('X-Agent-Name'))->toBe(['Ana'])
        ->and($sent[0]->data())->toBe(['content' => 'Bom dia!'])
        ->and($sent[1]->url())->toBe(Notify::BASE_URL.'/api/v1/chat/conversations/c-1/assign')
        ->and($sent[1]->data())->toBe(['member_id' => null, 'agent_id' => 'm-9'])
        ->and($sent[1]->hasHeader('X-Agent-Id'))->toBeFalse()
        ->and($sent[2]->url())->toBe(Notify::BASE_URL.'/api/v1/chat/conversations/c-1/close');
});

it('chat: lê eventos pelo cursor e devolve o Retry-After do servidor', function () {
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/events*' => Http::response(['status' => true, 'data' => [], 'next' => 77, 'has_more' => false], 200, ['Retry-After' => '10']),
        Notify::BASE_URL.'/*' => Http::response(['status' => true, 'data' => []]),
    ]);

    // wait() ficou obsoleto: o servidor não faz mais long polling.
    $page = NotifyQuery::server()->chat()->events()->after(10)->wait(99)->types(['message.received'])->get();
    expect($page['retry_after'])->toBe(10)
        ->and(NotifyQuery::server()->chat()->events()->latest())->toBe(77);

    Http::assertSent(fn ($r) => str_starts_with($r->url(), Notify::BASE_URL.'/api/v1/chat/events?')
        && $r->data() === ['after' => '10', 'types' => 'message.received']);
    Http::assertSent(fn ($r) => $r->url() === Notify::BASE_URL.'/api/v1/chat/events?after=latest');
});

it('equipe: cadastra, atualiza e remove atendentes pelo id do seu sistema', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true])]);

    $team = NotifyQuery::server()->team();
    $team->syncAgent(7, 'Bruno');
    $team->updateAgent('a/b', role: 'admin');
    $team->removeAgent(7);

    $sent = Http::recorded()->map(fn ($pair) => [$pair[0]->method(), $pair[0]->url(), $pair[0]->data()])->values()->all();

    expect($sent)->toBe([
        ['POST', Notify::BASE_URL.'/api/v1/team/agents', ['external_id' => '7', 'name' => 'Bruno']],
        ['PUT', Notify::BASE_URL.'/api/v1/team/agents/a%2Fb', ['role' => 'admin']],
        ['DELETE', Notify::BASE_URL.'/api/v1/team/agents/7', []],
    ]);
});

it('lote: envia os itens do canal com a chave de idempotência', function () {
    Http::fake([Notify::BASE_URL.'/api/v1/send/batch' => Http::response(['status' => true, 'accepted' => 2], 202)]);

    $result = NotifyQuery::server()->batch('whatsapp', [['to' => '5521911111111', 'content' => 'a'], ['to' => '5521922222222', 'content' => 'b']], ['tag' => 'lote'], 'lote-1');

    expect($result['accepted'])->toBe(2);
    Http::assertSent(fn ($r) => $r->header('Idempotency-Key') === ['lote-1']
        && $r['channel'] === 'whatsapp' && count($r['messages']) === 2 && $r['tag'] === 'lote');
});

it('evento: dispatchOnce dispara uma vez por event_id; sem id, sempre', function () {
    Event::fake([NotifyWhatsAppEvent::class]);

    expect(NotifyWhatsAppEvent::dispatchOnce('whatsapp.message.received', 5, []))->toBeTrue()
        ->and(NotifyWhatsAppEvent::dispatchOnce('whatsapp.message.received', 5, []))->toBeFalse()
        ->and(NotifyWhatsAppEvent::dispatchOnce('whatsapp.disconnected', null, []))->toBeTrue()
        ->and(NotifyWhatsAppEvent::dispatchOnce('whatsapp.disconnected', null, []))->toBeTrue();

    Event::assertDispatchedTimes(NotifyWhatsAppEvent::class, 3);

    $event = new NotifyWhatsAppEvent('whatsapp.message.received', 1, ['message' => ['body' => 'oi'], 'conversation' => ['id' => 'c-1']]);
    expect($event->type())->toBe('message.received')
        ->and($event->is('conversation.closed', 'whatsapp.message.received'))->toBeTrue()
        ->and($event->message()['body'])->toBe('oi')
        ->and($event->conversation()['id'])->toBe('c-1')
        ->and($event->changes())->toBeNull();
});

it('webhook do chat dispara o evento e ignora corpo sem event', function () {
    Event::fake([NotifyWhatsAppEvent::class]);
    $controller = new NotifyWebhookController;

    $controller->whatsapp(Request::create('/', 'POST', ['event' => 'whatsapp.message.received', 'event_id' => 9, 'message' => ['body' => 'oi']]));
    $controller->whatsapp(Request::create('/', 'POST', ['event' => 'whatsapp.message.received', 'event_id' => 9]));
    $controller->whatsapp(Request::create('/', 'POST', ['foo' => 'bar']));

    Event::assertDispatchedTimes(NotifyWhatsAppEvent::class, 1);
    Event::assertDispatched(NotifyWhatsAppEvent::class, fn ($e) => $e->eventId === 9 && $e->message()['body'] === 'oi');
});

it('comando: começa do cursor atual, dispara os eventos e guarda o próximo cursor', function () {
    Event::fake([NotifyWhatsAppEvent::class]);
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/events?after=latest' => Http::response(['next' => 100]),
        Notify::BASE_URL.'/api/v1/chat/events*' => Http::response([
            'status' => true,
            'data' => [['id' => 101, 'type' => 'message.received', 'occurred_at' => '2026-10-12T10:00:00Z', 'payload' => ['message' => ['body' => 'oi']]]],
            'next' => 101,
            'has_more' => false,
        ]),
    ]);

    expect(Artisan::call('notify:whatsapp-events', ['--once' => true]))->toBe(0)
        ->and(Cache::get(WhatsAppEventsCommand::CURSOR_KEY))->toBe(101);

    Event::assertDispatched(NotifyWhatsAppEvent::class, fn ($e) => $e->event === 'whatsapp.message.received'
        && $e->eventId === 101 && $e->payload['message']['body'] === 'oi');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'after=100'));
});

// Interrompe o laço contínuo do comando depois de algumas pausas.
function stopAfterSleeps(int $times): void
{
    $count = 0;

    Sleep::whenFakingSleep(function () use (&$count, $times) {
        if (++$count >= $times) {
            throw new RuntimeException('fim do laço');
        }
    });
}

it('comando: sem novidade, espera o Retry-After antes da próxima consulta', function () {
    Sleep::fake();
    stopAfterSleeps(2);
    Cache::forever(WhatsAppEventsCommand::CURSOR_KEY, 5);
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/events*' => Http::response(['status' => true, 'data' => [], 'next' => 5, 'has_more' => false], 200, ['Retry-After' => '10']),
    ]);

    expect(fn () => Artisan::call('notify:whatsapp-events'))->toThrow(RuntimeException::class, 'fim do laço');

    // Antes, o laço chamava de novo na hora e esgotava o limite da conta.
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 10.0, 2);
});

it('comando: com has_more segue sem pausa e, no fim da fila, usa o --interval', function () {
    Sleep::fake();
    stopAfterSleeps(1);
    Cache::forever(WhatsAppEventsCommand::CURSOR_KEY, 5);
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/events*' => Http::sequence()
            ->push(['status' => true, 'data' => [['id' => 6, 'type' => 'message.received', 'payload' => []]], 'next' => 6, 'has_more' => true])
            ->push(['status' => true, 'data' => [['id' => 7, 'type' => 'message.received', 'payload' => []]], 'next' => 7, 'has_more' => false]),
    ]);

    expect(fn () => Artisan::call('notify:whatsapp-events', ['--interval' => 30]))->toThrow(RuntimeException::class);

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 30.0);
    expect(Cache::get(WhatsAppEventsCommand::CURSOR_KEY))->toBe(7);
});

it('comando: no 429 espera o tempo pedido pelo servidor', function () {
    Sleep::fake();
    stopAfterSleeps(1);
    Cache::forever(WhatsAppEventsCommand::CURSOR_KEY, 5);
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/events*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42']),
    ]);

    expect(fn () => Artisan::call('notify:whatsapp-events'))->toThrow(RuntimeException::class);

    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 42.0);
});

it('comando: --wait antigo é aceito, avisado e ignorado', function () {
    Sleep::fake();
    Cache::forever(WhatsAppEventsCommand::CURSOR_KEY, 5);
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/events*' => Http::response(['status' => true, 'data' => [], 'next' => 5, 'has_more' => false]),
    ]);

    expect(Artisan::call('notify:whatsapp-events', ['--once' => true, '--wait' => 25]))->toBe(0)
        ->and(Artisan::output())->toContain('--wait é obsoleta');

    Http::assertSent(fn ($r) => ! array_key_exists('wait', $r->data()));
    Sleep::assertNeverSlept();
});

it('chat: webhook por conversa ao começar, responder e transferir; null volta ao do número', function () {
    Http::fake([Notify::BASE_URL.'/*' => Http::response(['status' => true])]);
    $chat = NotifyQuery::server()->chat();

    $chat->start('cfg-1', '5521999998888', 'Olá', 'https://crm.test/whatsapp');
    $chat->conversation('c-1')->webhook('https://crm.test/whatsapp')->reply('Tudo bem?');
    $chat->conversation('c-1')->webhook(null)->assign('m-2');
    $chat->conversation('c-1')->reply('Sem webhook');

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();
    expect($sent[0]['webhook_url'])->toBe('https://crm.test/whatsapp')
        ->and($sent[1])->toBe(['content' => 'Tudo bem?', 'webhook_url' => 'https://crm.test/whatsapp'])
        ->and($sent[2])->toBe(['member_id' => 'm-2', 'webhook_url' => null])
        ->and($sent[3])->toBe(['content' => 'Sem webhook']);
});

it('chat: baixa a mídia pelo link assinado da mensagem', function () {
    Http::fake([
        Notify::BASE_URL.'/api/v1/chat/media/m-1*' => Http::response('JPEGDATA'),
        Notify::BASE_URL.'/api/v1/chat/media/m-2*' => Http::response('', 403),
    ]);
    $conversation = NotifyQuery::server()->chat()->conversation('c-1');

    expect($conversation->downloadMedia(['media' => ['url' => Notify::BASE_URL.'/api/v1/chat/media/m-1?signature=x']]))->toBe('JPEGDATA')
        ->and($conversation->downloadMedia(['media' => ['url' => Notify::BASE_URL.'/api/v1/chat/media/m-2?signature=velha']]))->toBeNull()
        ->and($conversation->downloadMedia(['type' => 'text']))->toBeNull();
});

it('webhook: o canal vem em channel; callback de campanha na rota de notificação vira evento de campanha', function () {
    Event::fake([NotifyWebhookEvent::class, NotifyCampaignWebhookEvent::class]);
    $controller = new NotifyWebhookController;

    $controller->notification(Request::create('/', 'POST', ['event' => 'delivered', 'channel' => 'sms', 'notification_id' => 'n-1', 'status' => 'delivered']));
    $controller->notification(Request::create('/', 'POST', ['event' => 'completed', 'campaign_id' => 'camp-1', 'status' => 'completed']));

    Event::assertDispatched(NotifyWebhookEvent::class, fn ($e) => $e->type === 'sms' && $e->notificationId === 'n-1');
    Event::assertDispatchedTimes(NotifyWebhookEvent::class, 1);
    Event::assertDispatched(NotifyCampaignWebhookEvent::class, fn ($e) => $e->campaignId === 'camp-1' && $e->status === 'completed');
});
