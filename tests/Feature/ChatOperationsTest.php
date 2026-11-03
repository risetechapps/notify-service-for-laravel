<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyWhatsAppBroadcast;
use RiseTechApps\Notify\Events\NotifyWhatsAppEvent;
use RiseTechApps\Notify\NotifyQuery;

/**
 * Operação do chat: timeout/retry das chamadas, cache do que quase não muda,
 * resumo da fila, dias sem expediente e retransmissão em tempo real.
 */
beforeEach(fn () => config(['notify.http.retry_sleep' => 0]));

// ── HTTP ─────────────────────────────────────────────────────────────────────

it('leitura tenta de novo no 5xx e devolve o sucesso', function () {
    Http::fake(['*' => Http::sequence()->push(['message' => 'falhou'], 502)->push(['status' => true, 'data' => ['ok']])]);

    expect(NotifyQuery::server()->chat()->conversation('c-1')->get())->toBe(['status' => true, 'data' => ['ok']]);
    Http::assertSentCount(2);
});

it('ação (POST) nunca repete sozinha, para não mandar a mensagem duas vezes', function () {
    Http::fake(['*' => Http::response(['message' => 'falhou'], 503)]);

    $result = NotifyQuery::server()->chat()->conversation('c-1')->reply('Oi');

    expect($result['http'])->toBe(503);
    Http::assertSentCount(1);
});

it('4xx não repete e volta com o código', function () {
    Http::fake(['*' => Http::response(['message' => 'Calma'], 429)]);

    expect(NotifyQuery::server()->chat()->conversation('c-1')->get()['http'])->toBe(429);
    Http::assertSentCount(1);
});

it('retries = 0 desliga as novas tentativas', function () {
    config(['notify.http.retries' => 0]);
    Http::fake(['*' => Http::response([], 500)]);

    NotifyQuery::server()->chat()->conversation('c-1')->get();

    Http::assertSentCount(1);
});

// ── Cache ────────────────────────────────────────────────────────────────────

it('guarda equipe, números e fluxos, e quem altera pelo pacote limpa', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => [['id' => 'm-1']]])]);
    $server = NotifyQuery::server();

    $server->team()->members();
    $server->team()->members();
    $server->chat()->numbers();
    $server->chat()->numbers();
    $server->chat()->botFlows()->all();
    $server->chat()->botFlows()->all();
    Http::assertSentCount(3);

    $server->team()->syncAgent(7, 'Ana');
    $server->team()->members();
    $server->whatsappNumber('cfg-1')->updateChatSettings(['enabled' => true, 'mark_read' => true]);
    $server->chat()->numbers();
    $server->chat()->botFlows()->create('Novo', ['menu' => []]);
    $server->chat()->botFlows()->all();
    Http::assertSentCount(9);

    $server->team()->members(fresh: true);
    Http::assertSentCount(10);
});

it('números ficam separados por atendente, e a alteração limpa de todos', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => []])]);
    $chat = NotifyQuery::server()->chat();

    $chat->numbers();
    $chat->as(1, 'Ana')->numbers();
    $chat->as(1, 'Ana')->numbers();
    Http::assertSentCount(2);

    NotifyQuery::server()->whatsappNumber('cfg-1')->restart();
    $chat->numbers();
    $chat->as(1, 'Ana')->numbers();
    Http::assertSentCount(5);
});

it('não guarda erro e ttl 0 desliga o cache', function () {
    Http::fake(['*' => Http::sequence()->push([], 500)->push([], 500)->push([], 500)->push(['status' => true])->push(['status' => true])->push(['status' => true])]);
    config(['notify.http.retries' => 0]);
    $team = NotifyQuery::server()->team();

    expect($team->members()['http'])->toBe(500);
    $team->members();
    Http::assertSentCount(2);

    Cache::flush();
    config(['notify.cache.ttl' => 0]);
    $team->members();
    $team->members();
    Http::assertSentCount(4);
});

// ── Fila ─────────────────────────────────────────────────────────────────────

it('resumo da fila em nome do atendente', function () {
    Http::fake(['*' => Http::response([
        'status' => true, 'data' => [], 'unread' => 4, 'me' => 'm-9', 'server_time' => '2026-11-03T10:00:00.000000-03:00',
        'counts' => ['queue' => 3, 'mine' => 2, 'bot' => 5],
    ])]);

    $summary = NotifyQuery::server()->chat()->as(9, 'Ana')->summary();

    expect($summary['data'])->toBe(['queue' => 3, 'mine' => 2, 'bot' => 5, 'unread' => 4, 'me' => 'm-9', 'server_time' => '2026-11-03T10:00:00.000000-03:00']);
    Http::assertSent(fn (Request $r) => $r->hasHeader('X-Agent-Id', '9') && str_contains($r->url(), 'per_page=1'));
});

// ── Dias sem expediente ──────────────────────────────────────────────────────

function fakeFlowWith(array $definition): void
{
    Http::fake(['*/api/v1/chat/bot-flows/flow-1' => fn (Request $r) => $r->method() === 'GET'
        ? Http::response(['status' => true, 'data' => ['id' => 'flow-1', 'name' => 'Principal', 'definition' => $definition]])
        : Http::response(['status' => true])]);
}

function savedClosedDates(): array
{
    $put = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => $r->method() === 'PUT');

    return $put['definition']['business_hours']['closed_dates'];
}

it('marca dias sem expediente, troca a mensagem da mesma data e tira as que passaram', function () {
    $this->travelTo(new DateTimeImmutable('2026-11-03 12:00:00', new DateTimeZone('America/Sao_Paulo')));
    fakeFlowWith(['menu' => ['text' => 'M'], 'business_hours' => ['enabled' => true, 'days' => ['mon' => []], 'closed_dates' => [
        ['date' => '2026-01-01'],
        ['date' => '2026-12-25', 'message' => 'Antiga'],
    ]]]);

    NotifyQuery::server()->chat()->botFlows()->addClosedDates('flow-1', [
        '2026-12-25' => 'Feliz Natal!',
        '2026-11-03' => null,
    ]);

    expect(savedClosedDates())->toBe([
        ['date' => '2026-11-03'],
        ['date' => '2026-12-25', 'message' => 'Feliz Natal!'],
    ]);
});

it('marca um dia por DateTime, desmarca e lista', function () {
    $this->travelTo(new DateTimeImmutable('2026-11-03 12:00:00'));
    fakeFlowWith(['menu' => ['text' => 'M'], 'business_hours' => ['closed_dates' => [['date' => '2026-12-25', 'message' => 'Natal'], ['date' => '2026-12-31']]]]);
    $flows = NotifyQuery::server()->chat()->botFlows();

    expect($flows->closedDates('flow-1'))->toBe(['2026-12-25' => 'Natal', '2026-12-31' => null]);

    $flows->removeClosedDate('flow-1', new DateTimeImmutable('2026-12-31'));
    expect(savedClosedDates())->toBe([['date' => '2026-12-25', 'message' => 'Natal']]);
});

it('recusa data que não existe antes de chamar o servidor', function () {
    Http::fake();

    expect(fn () => NotifyQuery::server()->chat()->botFlows()->addClosedDate('flow-1', '2026-02-30'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

// ── Tempo real ───────────────────────────────────────────────────────────────

it('retransmite o evento do chat nos canais da conta e do número', function () {
    config(['notify.broadcast.enabled' => true]);
    Event::fake([NotifyWhatsAppBroadcast::class]);

    NotifyWhatsAppEvent::dispatchOnce('whatsapp.message.received', 101, [
        'configuration_id' => 'cfg-1',
        'conversation' => ['id' => 'c-1'],
        'message' => ['body' => 'Oi'],
    ]);

    Event::assertDispatched(NotifyWhatsAppBroadcast::class, function (NotifyWhatsAppBroadcast $b) {
        return $b->broadcastAs() === 'message.received'
            && array_map(fn ($c) => $c->name, $b->broadcastOn()) === ['private-notify.whatsapp', 'private-notify.whatsapp.cfg-1']
            && $b->broadcastWith()['message'] === ['body' => 'Oi']
            && $b->broadcastWith()['event_id'] === 101;
    });
});

it('não retransmite desligado nem tipo fora da lista', function () {
    Event::fake([NotifyWhatsAppBroadcast::class]);

    NotifyWhatsAppEvent::dispatchOnce('whatsapp.message.received', 201, []);

    config(['notify.broadcast.enabled' => true, 'notify.broadcast.events' => ['whatsapp.conversation.updated']]);
    NotifyWhatsAppEvent::dispatchOnce('whatsapp.message.received', 202, []);
    NotifyWhatsAppEvent::dispatchOnce('whatsapp.conversation.updated', 203, ['conversation' => ['id' => 'c', 'configuration_id' => 'cfg-2']]);

    Event::assertDispatchedTimes(NotifyWhatsAppBroadcast::class, 1);
    Event::assertDispatched(NotifyWhatsAppBroadcast::class, fn ($b) => $b->broadcastOn()[1]->name === 'private-notify.whatsapp.cfg-2');
});

// ── Proteção do número ───────────────────────────────────────────────────────

it('capacidade do número: o que ainda dá para mandar hoje, com o aquecimento', function () {
    Http::fake(['*/configurations/cfg-1/whatsapp/protection' => Http::response(['status' => true, 'data' => [
        'enabled' => true,
        'paused' => null,
        'today' => ['sent' => 150, 'daily_limit' => 200, 'new_contacts' => 12, 'new_contacts_limit' => 10],
        'warmup' => ['age_days' => 1, 'factor' => 0.2],
    ]])]);

    expect(NotifyQuery::server()->whatsappNumber('cfg-1')->capacity()['data'])->toBe([
        'sends' => 50, 'new_contacts' => 0, 'paused' => null, 'warmup_factor' => 0.2, 'age_days' => 1,
    ]);
});

it('capacidade do número pausado traz o motivo; erro volta como veio', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['status' => true, 'data' => ['paused' => ['reason' => 'undelivered'], 'today' => [], 'warmup' => []]])
        ->push(['message' => 'Não encontrado'], 404)]);
    $number = NotifyQuery::server()->whatsappNumber('cfg-1');

    expect($number->capacity()['data']['paused'])->toBe('undelivered')
        ->and($number->capacity()['http'])->toBe(404);
});
