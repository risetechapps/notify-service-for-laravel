<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Message\NotifyApns;
use RiseTechApps\Notify\Message\NotifyDiscord;
use RiseTechApps\Notify\Message\NotifyMail;
use RiseTechApps\Notify\Message\NotifySlack;
use RiseTechApps\Notify\Message\NotifyTeams;
use RiseTechApps\Notify\Message\NotifyTelegram;
use RiseTechApps\Notify\Message\NotifyWebhook;
use RiseTechApps\Notify\Message\NotifyWebSocket;
use RiseTechApps\Notify\Message\NotifyWhatsApp;
use RiseTechApps\Notify\NotifyQuery;

/**
 * Os nomes dos campos têm de bater com o que o notify-server valida: um nome
 * errado vira 422 (ou campo ignorado) sem aviso. Antes, edit() mandava `text`
 * e o servidor espera `message`, e os testes só olhavam o status da resposta.
 */

// ── Ações sobre a mensagem enviada ───────────────────────────────────────────

it('edit manda message (e content no WhatsApp), com os campos extras', function () {
    Http::fake(['*' => Http::response(['status' => true], 200)]);

    NotifyQuery::server()->telegram('n1')->edit('Novo', 'HTML');
    NotifyQuery::server()->slack('n2')->edit('Novo', extra: ['color' => '#2ecc71']);
    NotifyQuery::server()->whatsapp('n3')->edit('Novo');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/telegram/messages/edit')
        && $r['notification_id'] === 'n1' && $r['message'] === 'Novo' && $r['parse_mode'] === 'HTML' && ! isset($r['text']));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/slack/messages/edit')
        && $r['message'] === 'Novo' && $r['color'] === '#2ecc71' && ! isset($r['parse_mode']));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/whatsapp/messages/edit')
        && $r['content'] === 'Novo' && ! isset($r['message']));
});

it('editCaption manda message', function () {
    Http::fake(['*' => Http::response(['status' => true], 200)]);

    NotifyQuery::server()->telegram('n1')->editCaption('Legenda');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/telegram/messages/edit-caption') && $r['message'] === 'Legenda');
});

it('ações novas vão para as rotas do servidor', function (callable $acao, string $rota, array $campos) {
    Http::fake(['*' => Http::response(['status' => true], 200)]);

    $resultado = $acao();

    expect($resultado['status'])->toBeTrue();
    Http::assertSent(function (Request $r) use ($rota, $campos) {
        if (! str_ends_with($r->url(), $rota)) {
            return false;
        }

        foreach ($campos as $campo => $valor) {
            if (($r->data()[$campo] ?? null) !== $valor) {
                return false;
            }
        }

        return true;
    });
})->with([
    'telegram unpin' => [fn () => NotifyQuery::server()->telegram('n')->unpin(), '/telegram/messages/unpin', ['notification_id' => 'n']],
    'telegram edit-media' => [fn () => NotifyQuery::server()->telegram('n')->editMedia('https://cdn/x.jpg', 'photo', 'Leg'), '/telegram/messages/edit-media', ['media_url' => 'https://cdn/x.jpg', 'media_type' => 'photo', 'message' => 'Leg']],
    'telegram edit-buttons' => [fn () => NotifyQuery::server()->telegram('n')->editButtons([]), '/telegram/messages/edit-buttons', ['buttons' => []]],
    'telegram pin' => [fn () => NotifyQuery::server()->telegram('n')->pin(true), '/telegram/messages/pin', ['disable_notification' => true]],
    'slack pin' => [fn () => NotifyQuery::server()->slack('n')->pin(), '/slack/messages/pin', ['notification_id' => 'n']],
    'slack react' => [fn () => NotifyQuery::server()->slack('n')->react('tada'), '/slack/messages/react', ['emoji' => 'tada']],
    'whatsapp delete' => [fn () => NotifyQuery::server()->whatsapp('n')->delete(), '/whatsapp/messages/delete', ['notification_id' => 'n']],
]);

it('consulta as listas novas da configuração', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => []], 200)]);

    NotifyQuery::server()->telegram()->config('c1')->telegramChats();
    NotifyQuery::server()->slack()->config('c1')->slackChannels();
    NotifyQuery::server()->whatsapp()->config('c1')->whatsappGroups(fresh: true);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/configurations/c1/telegram/chats'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/configurations/c1/slack/channels'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/configurations/c1/whatsapp/groups?fresh=1'));
});

// ── Campos novos das mensagens ───────────────────────────────────────────────

it('e-mail: cópias, resposta, prévia, headers e anexos no corpo', function () {
    $dados = (new NotifyMail)->to('a@x.com')->cc('b@x.com')->bcc(['c@x.com'])->replyTo('s@x.com', 'Suporte')
        ->preheader('Prévia')->header('X-Pedido', '1')->attachUrl('https://cdn/nf.pdf', 'nf.pdf')
        ->attachData('conteudo', 'a.txt', 'text/plain')->inline('logo', url: 'https://cdn/logo.png')->toArray();

    expect($dados['cc'])->toBe(['b@x.com'])
        ->and($dados['bcc'])->toBe(['c@x.com'])
        ->and($dados['reply_to'])->toBe(['email' => 's@x.com', 'name' => 'Suporte'])
        ->and($dados['preheader'])->toBe('Prévia')
        ->and($dados['headers'])->toBe(['X-Pedido' => '1'])
        ->and($dados['attach'])->toBe([['url' => 'https://cdn/nf.pdf', 'name' => 'nf.pdf']])
        ->and($dados['attachments'][0])->toBe(['name' => 'a.txt', 'content' => base64_encode('conteudo'), 'mime' => 'text/plain'])
        ->and($dados['inline'][0])->toBe(['cid' => 'logo', 'url' => 'https://cdn/logo.png']);
});

it('APNs: campos novos com os nomes do servidor', function () {
    $dados = (new NotifyApns)->token('ab')->title('t')->body('b')->image('https://cdn/i.jpg')->interruptionLevel('time-sensitive')
        ->relevanceScore(0.5)->ttl(60)->environment('sandbox')->collapseId('c')->contentAvailable()->criticalSound('a.caf', 0.5)->toArray();

    expect($dados)->toMatchArray([
        'image' => 'https://cdn/i.jpg', 'interruption_level' => 'time-sensitive', 'relevance_score' => 0.5,
        'ttl' => 60, 'environment' => 'sandbox', 'collapse_id' => 'c', 'content_available' => true,
        'sound' => ['name' => 'a.caf', 'critical' => true, 'volume' => 0.5],
    ]);
});

it('Telegram: mídia por URL e no corpo, álbum, quiz e spoiler', function () {
    $doc = (new NotifyTelegram)->chatId('1')->documentUrl('https://cdn/a.pdf', 'a.pdf')->toArray();
    $corpo = (new NotifyTelegram)->chatId('1')->media('bytes', 'photo')->spoiler()->captionAbove()->toArray();
    $quiz = (new NotifyTelegram)->chatId('1')->quiz('2+2?', ['3', '4'], 1, 'Básico')->toArray();

    expect($doc)->toMatchArray(['document_url' => 'https://cdn/a.pdf', 'filename' => 'a.pdf'])
        ->and($corpo)->toMatchArray(['media_base64' => base64_encode('bytes'), 'media_type' => 'photo', 'has_spoiler' => true, 'show_caption_above_media' => true])
        ->and($quiz['poll'])->toMatchArray(['type' => 'quiz', 'correct_option_id' => 1, 'explanation' => 'Básico']);
});

it('WhatsApp: grupo sem to, menções, localização e contato', function () {
    $grupo = (new NotifyWhatsApp)->group('123456789012@g.us')->mentionAll()->mention('5521999998888')->content('Oi')->toArray();
    $local = (new NotifyWhatsApp)->to('5521999998888')->location(-22.9, -43.2, 'Loja')->typing(false)->toArray();

    expect($grupo)->not->toHaveKey('to')
        ->and($grupo)->toMatchArray(['group_id' => '123456789012@g.us', 'mention_all' => true, 'mentions' => ['5521999998888']])
        ->and($local)->toMatchArray(['location' => ['latitude' => -22.9, 'longitude' => -43.2, 'name' => 'Loja'], 'typing' => false])
        ->and((new NotifyWhatsApp)->group('1@g.us')->hasRecipient())->toBeTrue();
});

it('Slack e Discord: campos novos', function () {
    $slack = (new NotifySlack)->channel('C1')->message('m')->ephemeral('U0123456')->unfurl(false)->escape(false)->toArray();
    $discord = (new NotifyDiscord)->message('m')->poll('P?', ['a', 'b'], 48)->addFile(url: 'https://cdn/a.pdf')->silent()->timestamp(false)->color('#2ecc71')->toArray();

    expect($slack)->toMatchArray(['ephemeral_user' => 'U0123456', 'unfurl_links' => false, 'escape' => false])
        ->and($discord)->toMatchArray(['poll' => ['question' => 'P?', 'answers' => ['a', 'b'], 'duration' => 48, 'allow_multiselect' => false], 'files' => [['url' => 'https://cdn/a.pdf']], 'silent' => true, 'timestamp' => false, 'color' => '#2ecc71']);
});

it('Teams, WebSocket e webhook: campos novos', function () {
    $teams = (new NotifyTeams)->message('Oi <at>Ana</at>')->mention('ana@x.com', 'Ana')->fullWidth()->color('#FF0000')->toArray();
    $ws = (new NotifyWebSocket)->channels(['a', 'b'])->event('E')->data(['x' => 1])->except('1.2')->notifyMeta(false)->toArray();
    $hook = (new NotifyWebhook)->url('https://x.com')->apiKeyAuth('k', 'X-Chave')->toArray();
    $hmac = (new NotifyWebhook)->url('https://x.com')->hmacAuth('s')->toArray();
    $form = (new NotifyWebhook)->url('https://x.com')->method('delete')->asForm()->notifyMeta(false)->toArray();

    expect($teams)->toMatchArray(['mentions' => [['id' => 'ana@x.com', 'name' => 'Ana']], 'full_width' => true, 'color' => '#FF0000'])
        ->and($ws)->not->toHaveKey('channel')
        ->and($ws)->toMatchArray(['channels' => ['a', 'b'], 'socket_id' => '1.2', 'notify_meta' => false])
        ->and($hook)->toMatchArray(['auth_type' => 'api_key', 'api_key_header' => 'X-Chave'])
        ->and($hmac)->toMatchArray(['auth_type' => 'hmac', 'secret' => 's'])
        ->and($form)->toMatchArray(['method' => 'DELETE', 'body_format' => 'form', 'notify_meta' => false]);
});
