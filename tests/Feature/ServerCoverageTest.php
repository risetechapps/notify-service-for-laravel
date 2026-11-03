<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\NotifyQuery;

/**
 * Rotas do notify-server que o pacote não cobria (inventário de 03/11/2026,
 * contra as 161 rotas da API com chave): cada método bate na rota e com os
 * campos que o servidor valida.
 */
beforeEach(fn () => Http::fake(['*' => Http::response(['status' => true, 'data' => []], 200)]));

function sent(string $method, string $suffix, ?callable $check = null): void
{
    Http::assertSent(fn (Request $r) => $r->method() === $method
        && str_contains($r->url(), '/api/v1/'.$suffix)
        && ($check === null || $check($r)));
}

it('número de WhatsApp: reinicia a sessão, resposta ao SAIR/VOLTAR e estado sem QR novo', function () {
    $number = NotifyQuery::server()->whatsappNumber('cfg-1');

    $number->restart();
    $number->optOutReply();
    $number->updateOptOutReply(true, 'Pronto, você saiu.', null);
    $number->state('5521999998888', qr: false);
    $number->state();

    sent('POST', 'configurations/cfg-1/whatsapp/restart');
    sent('GET', 'configurations/cfg-1/whatsapp/opt-out');
    sent('PUT', 'configurations/cfg-1/whatsapp/opt-out', fn ($r) => $r['enabled'] === true
        && $r['opt_out_message'] === 'Pronto, você saiu.' && array_key_exists('opt_in_message', $r->data()) && $r['opt_in_message'] === null);
    sent('GET', 'configurations/cfg-1/whatsapp?', fn ($r) => str_contains($r->url(), 'phone=5521999998888') && str_contains($r->url(), 'qr=0'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/configurations/cfg-1/whatsapp'));
});

it('conversa: digitando, reagir, editar e apagar mensagem, e mensagens desde um instante', function () {
    $conversation = NotifyQuery::server()->chat()->conversation('conv-1');

    $conversation->typing('recording');
    $conversation->react('msg-1', '👍');
    $conversation->react('msg-1', null);
    $conversation->editMessage('msg-2', 'Texto novo');
    $conversation->deleteMessage('msg-3');
    $conversation->messages(since: '2026-11-03T10:00:00.000000-03:00', limit: 100);

    sent('POST', 'chat/conversations/conv-1/typing', fn ($r) => $r['state'] === 'recording');
    sent('POST', 'chat/conversations/conv-1/messages/msg-1/react', fn ($r) => $r['emoji'] === '👍');
    sent('POST', 'chat/conversations/conv-1/messages/msg-1/react', fn ($r) => array_key_exists('emoji', $r->data()) && $r['emoji'] === null);
    sent('PATCH', 'chat/conversations/conv-1/messages/msg-2', fn ($r) => $r['content'] === 'Texto novo');
    sent('DELETE', 'chat/conversations/conv-1/messages/msg-3');
    sent('GET', 'chat/conversations/conv-1/messages', fn ($r) => str_contains($r->url(), 'since=') && str_contains($r->url(), 'limit=100'));
});

it('chat: começa conversa com enquete ou citando uma mensagem, em nome do atendente', function () {
    $chat = NotifyQuery::server()->chat()->as('user-9', 'Ana');

    $chat->start('cfg-1', '+5511999999999', 'Oi!', quotedId: 'WA-123');
    $chat->startPoll('cfg-1', '+5511999999999', 'Prefere?', ['Manhã', 'Tarde'], multiple: true);

    sent('POST', 'chat/conversations', fn ($r) => ($r->data()['content'] ?? null) === 'Oi!' && ($r->data()['quoted_id'] ?? null) === 'WA-123' && $r->header('X-Agent-Id') === ['user-9']);
    sent('POST', 'chat/conversations', fn ($r) => ($r->data()['poll'] ?? null) === ['question' => 'Prefere?', 'options' => ['Manhã', 'Tarde'], 'multiple' => true]
        && ! array_key_exists('content', $r->data()));
});

it('simulação do fluxo com o fluxo em edição', function () {
    NotifyQuery::server()->chat()->botFlows()->simulate(['menu' => ['text' => 'Oi', 'options' => []]], null, '1', 'flow-9');

    sent('POST', 'chat/bot-flows/simulate', fn ($r) => $r['editing_id'] === 'flow-9' && $r['input'] === '1' && ! array_key_exists('state', $r->data()));
});

it('cota, requisições por dia e consumo por canal', function () {
    $server = NotifyQuery::server();

    $server->quota();
    $server->requestsDaily(7);
    $server->usage(14);

    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), '/api/v1/usage'));
    sent('GET', 'usage/daily', fn ($r) => str_contains($r->url(), 'days=7'));
    sent('GET', 'usage/channels', fn ($r) => str_contains($r->url(), 'days=14'));
});

it('listas com busca e paginação: modelos, listas, membros e descadastros', function () {
    $server = NotifyQuery::server();

    $server->templates()->all('sms', 'boas', 2, 10);
    $server->contactLists()->all('email', 3, 20);
    $server->contactLists()->members('list-1', 'ana', 1, 200);
    $server->optOuts()->all('whatsapp', '2199', 4, 50);

    sent('GET', 'templates?', fn ($r) => str_contains($r->url(), 'channel=sms') && str_contains($r->url(), 'search=boas') && str_contains($r->url(), 'page=2') && str_contains($r->url(), 'per_page=10'));
    sent('GET', 'contact-lists?', fn ($r) => str_contains($r->url(), 'page=3') && str_contains($r->url(), 'per_page=20'));
    sent('GET', 'contact-lists/list-1/members', fn ($r) => str_contains($r->url(), 'search=ana') && str_contains($r->url(), 'per_page=200'));
    sent('GET', 'sms-opt-outs', fn ($r) => str_contains($r->url(), 'channel=whatsapp') && str_contains($r->url(), 'page=4') && str_contains($r->url(), 'per_page=50'));
});

it('filtros da listagem de campanhas vão para o servidor', function () {
    NotifyQuery::server()->campaigns('whatsapp')->status('paused')->tag(['vip', 'natal'])->from('2026-11-01')->to('2026-11-30')->perPage(50)->get();

    sent('GET', 'send/campaigns/whatsapp', fn ($r) => str_contains($r->url(), 'status=paused') && str_contains($r->url(), 'from=2026-11-01')
        && str_contains($r->url(), 'to=2026-11-30') && str_contains(urldecode($r->url()), 'tag[0]=vip') && str_contains($r->url(), 'per_page=50'));
});
