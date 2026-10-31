<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Channel\NotifyChannelApns;
use RiseTechApps\Notify\Channel\NotifyChannelDiscord;
use RiseTechApps\Notify\Channel\NotifyChannelMail;
use RiseTechApps\Notify\Channel\NotifyChannelPush;
use RiseTechApps\Notify\Channel\NotifyChannelSlack;
use RiseTechApps\Notify\Channel\NotifyChannelSms;
use RiseTechApps\Notify\Channel\NotifyChannelTeams;
use RiseTechApps\Notify\Channel\NotifyChannelTelegram;
use RiseTechApps\Notify\Channel\NotifyChannelWebhook;
use RiseTechApps\Notify\Channel\NotifyChannelWebSocket;
use RiseTechApps\Notify\Channel\NotifyChannelWhatsApp;
use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWebhookEvent;
use RiseTechApps\Notify\Http\Controllers\NotifyWebhookController;
use RiseTechApps\Notify\Http\Middleware\VerifyNotifySignature;
use RiseTechApps\Notify\Message\NotifyApns;
use RiseTechApps\Notify\Message\NotifyDiscord;
use RiseTechApps\Notify\Message\NotifyMail;
use RiseTechApps\Notify\Message\NotifyPush;
use RiseTechApps\Notify\Message\NotifySlack;
use RiseTechApps\Notify\Message\NotifySms;
use RiseTechApps\Notify\Message\NotifyTeams;
use RiseTechApps\Notify\Message\NotifyTelegram;
use RiseTechApps\Notify\Message\NotifyWebhook;
use RiseTechApps\Notify\Message\NotifyWebSocket;
use RiseTechApps\Notify\Message\NotifyWhatsApp;
use RiseTechApps\Notify\Notify;
use RiseTechApps\Notify\NotifyCampaignBuilder;
use RiseTechApps\Notify\NotifyQuery;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Contrato do pacote contra um notify-server de verdade, no modo de teste
 * (X-Notify-Test: nada chega a um provedor). Só roda com:
 *
 *   NOTIFY_E2E_URL=http://127.0.0.1:8020 NOTIFY_E2E_KEY=<chave> vendor/bin/pest tests/E2E
 *
 * A conta precisa de um plano com todas as features e de uma configuração
 * ativa de Slack, Discord, Teams, WebSocket e webhook (canais só do cliente).
 * As chamadas do pacote vão para Notify::BASE_URL; aqui elas são desviadas
 * para NOTIFY_E2E_URL e feitas de verdade.
 */
beforeEach(function () {
    $this->base = rtrim((string) getenv('NOTIFY_E2E_URL'), '/');

    if ($this->base === '') {
        $this->markTestSkipped('Defina NOTIFY_E2E_URL e NOTIFY_E2E_KEY para rodar contra um servidor.');
    }

    config(['notify.key' => getenv('NOTIFY_E2E_KEY'), 'notify.webhook' => null, 'notify.retry_failures' => false]);
    $GLOBALS['e2e_test_header'] = true;

    $client = new Client(['http_errors' => false, 'timeout' => 30]);
    $base = new Uri($this->base);

    Http::fake(function (Request $request) use ($client, $base) {
        $uri = $request->toPsrRequest()->getUri()->withScheme($base->getScheme())->withHost($base->getHost())->withPort($base->getPort());
        $psr = $request->toPsrRequest()->withUri($uri);
        $psr = ($GLOBALS['e2e_test_header'] ?? true) ? $psr->withHeader('X-Notify-Test', 'true') : $psr->withoutHeader('X-Notify-Test');
        $response = $client->send($psr);

        if (str_contains($uri->getPath(), '/api/v1/send/') && ! str_contains($uri->getPath(), '/campaigns/')) {
            $GLOBALS['e2e_last_sent'] = json_decode((string) $request->body(), true) ?: [];
        }

        return Http::response((string) $response->getBody(), $response->getStatusCode(), $response->getHeaders());
    });
});

function e2eNotification(string $method, object $message): Notification
{
    return new class($method, $message) extends Notification
    {
        public function __construct(public string $method, public object $message) {}

        public function via($notifiable): array
        {
            return [];
        }

        public function __call($name, $args)
        {
            return $this->message;
        }
    };
}

/** Manda pelo canal do pacote e devolve a resposta do servidor. */
function e2eSend(string $channelClass, string $method, object $message, array $routes = []): array
{
    $notifiable = new AnonymousNotifiable;

    foreach ($routes as $channel => $route) {
        $notifiable->route($channel, $route);
    }

    $response = (array) (new $channelClass)->send($notifiable, e2eNotification($method, $message));

    // Nome do histórico do canal (NotifyQuery::server()->{canal}()).
    $response['_channel'] = match ($channelClass) {
        NotifyChannelMail::class => 'mail',
        NotifyChannelWebSocket::class => 'websocket',
        default => strtolower(str_replace('NotifyChannel', '', class_basename($channelClass))),
    };

    return $response;
}

function aceito(array $response): void
{
    expect($response['status'] ?? null)->toBeTrue(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // O que o pacote mandou tem de estar no que o servidor guardou e despacha:
    // campo aceito com 202 e descartado na validação some sem aviso.
    $sent = $GLOBALS['e2e_last_sent'] ?? [];
    $channel = $response['_channel'] ?? null;

    if ($sent === [] || $channel === null || empty($response['notification_id'])) {
        return;
    }

    $stored = (array) (NotifyQuery::server()->{$channel}($response['notification_id'])->get()['content'] ?? []);
    $missing = e2eMissingKeys(array_diff_key($sent, array_flip(E2E_TRANSFORMED)), $stored);

    expect($missing)->toBe([], "Campos enviados e perdidos no servidor ({$channel}): ".implode(', ', $missing));
}

/** Campos que o servidor guarda transformados (arquivo no disco, segredo mascarado) ou fora do conteúdo. */
const E2E_TRANSFORMED = ['attachments', 'inline', 'media_base64', 'file_base64', 'files', 'webhook_url', 'config_id', 'template_id', 'variables', 'scheduled_at', 'timezone', 'failover', 'tag'];

function e2eMissingKeys(array $sent, mixed $stored, string $path = ''): array
{
    $missing = [];

    foreach ($sent as $key => $value) {
        if (! is_array($stored) || ! array_key_exists($key, $stored)) {
            $missing[] = ltrim("{$path}.{$key}", '.');
        } elseif (is_array($value)) {
            $missing = [...$missing, ...e2eMissingKeys($value, $stored[$key], "{$path}.{$key}")];
        }
    }

    return $missing;
}

// ── Envio por canal, com os recursos novos ───────────────────────────────────

it('SMS com modelo salvo ausente responde 422 com o motivo, e SMS simples passa', function () {
    aceito(e2eSend(NotifyChannelSms::class, 'toNotifySms', (new NotifySms)->content('Código 1234')->tag('e2e'), ['sms' => '5511900000001']));
});

it('E-mail com cópias, resposta, prévia, headers, anexos e imagem embutida', function () {
    $mail = (new NotifyMail)->subject('Pedido')->line('Seu pedido saiu.')->from('loja@exemplo.com', 'Loja')->appName('Loja')
        ->cc('copia@exemplo.com')->bcc(['oculta@exemplo.com'])->replyTo('suporte@exemplo.com', 'Suporte')
        ->preheader('Prévia')->header('X-Pedido', '1042')
        ->attachUrl('https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', 'nota.pdf')
        ->attachData('conteudo', 'a.txt', 'text/plain')
        ->inline('logo', content: base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='), mime: 'image/png');

    aceito(e2eSend(NotifyChannelMail::class, 'toNotifyMail', $mail, ['mail' => ['entregue@sandbox.notify' => 'Ana']]));
});

it('E-mail em HTML puro', function () {
    aceito(e2eSend(NotifyChannelMail::class, 'toNotifyMail', (new NotifyMail)->subject('HTML')->from('loja@exemplo.com', 'Loja')->html('<p>Olá</p>'), ['mail' => 'entregue@sandbox.notify']));
});

it('Push FCM com imagem, ações, prioridade e ttl', function () {
    $push = (new NotifyPush)->token('0000000000000001')->title('Pedido')->body('Saiu')->image('https://exemplo.com/i.png')
        ->data(['pedido' => 1042, 'pago' => true])->priority('high')->ttl(3600)->channelId('pedidos')->action('Ver', 'ver');

    aceito(e2eSend(NotifyChannelPush::class, 'toNotifyPush', $push));
});

it('APNs com imagem, nível de interrupção, relevância, ambiente e som crítico', function () {
    $apns = (new NotifyApns)->token('0000000000000001')->title('Alerta')->body('Corpo')->image('https://exemplo.com/i.png')
        ->interruptionLevel('time-sensitive')->relevanceScore(0.8)->ttl(600)->environment('sandbox')->collapseId('c1')
        ->criticalSound('alarme.caf', 0.7)->category('PEDIDO')->threadId('t1');

    aceito(e2eSend(NotifyChannelApns::class, 'toNotifyApns', $apns));
});

it('Telegram: texto com todos os tipos de botão', function () {
    $tg = (new NotifyTelegram)->chatId('123456789')->message('Olá')
        ->button('Site', 'https://exemplo.com')
        ->webAppButton('App', 'https://exemplo.com/app', sameRow: true)
        ->loginButton('Entrar', 'https://exemplo.com/login')
        ->copyButton('Copiar código', 'ABC123', sameRow: true)
        ->switchInlineButton('Compartilhar', 'pedido 1042')
        ->switchInlineButton('Aqui', 'x', currentChat: true, sameRow: true)
        ->silent()->protectContent()->disablePreview();

    aceito(e2eSend(NotifyChannelTelegram::class, 'toNotifyTelegram', $tg));
});

it('Telegram: mídia no corpo com spoiler, documento por URL, álbum, enquete e quiz', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    aceito(e2eSend(NotifyChannelTelegram::class, 'toNotifyTelegram', (new NotifyTelegram)->chatId('123')->message('Foto')->media($png, 'photo', 'p.png')->spoiler()->captionAbove()));
    aceito(e2eSend(NotifyChannelTelegram::class, 'toNotifyTelegram', (new NotifyTelegram)->chatId('123')->message('Nota')->documentUrl('https://exemplo.com/nota.pdf', 'nota.pdf')));
    aceito(e2eSend(NotifyChannelTelegram::class, 'toNotifyTelegram', (new NotifyTelegram)->chatId('123')->mediaGroup([
        ['type' => 'photo', 'url' => 'https://exemplo.com/1.png', 'caption' => 'Um'],
        ['type' => 'photo', 'url' => 'https://exemplo.com/2.png'],
    ])));
    aceito(e2eSend(NotifyChannelTelegram::class, 'toNotifyTelegram', (new NotifyTelegram)->chatId('123')->poll('Qual?', ['A', 'B', 'C'], false)->pollMultipleAnswers()->pollOpenPeriod(60)));
    aceito(e2eSend(NotifyChannelTelegram::class, 'toNotifyTelegram', (new NotifyTelegram)->chatId('123')->quiz('2+2?', ['3', '4'], 1, 'Básico')->pollOpenPeriod(30)));
});

it('WhatsApp: texto, grupo com menções, localização, contato, mídia no corpo e enquete', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->content('Olá')->linkPreview(false)->typing(false), ['whatsapp' => '5511900000001']));
    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->group('120363000000000000@g.us')->mentionAll()->content('Grupo')));
    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->to('5511900000001')->location(-22.9, -43.2, 'Loja', 'Rua A')));
    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->to('5511900000001')->contact('Ana', '5511988887777', 'Loja', 'ana@exemplo.com')));
    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->to('5511900000001')->mediaData($png, 'image', 'p.png')->content('Legenda')));
    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->to('5511900000001')->poll('Qual?', ['A', 'B'], true)));
});

it('Slack: campos, efêmera, arquivo no corpo, unfurl, escape e agendamento nativo', function () {
    aceito(e2eSend(NotifyChannelSlack::class, 'toNotifySlack', (new NotifySlack)->channel('#teste-entregue')->message('Deploy <ok> & feito')->title('Deploy')->color('#2ecc71')->field('Versão', '1.2', true)->unfurl(false)->escape(true)));
    aceito(e2eSend(NotifyChannelSlack::class, 'toNotifySlack', (new NotifySlack)->channel('C0123ABCD')->message('Só você vê')->ephemeral('U0123ABCD')));
    aceito(e2eSend(NotifyChannelSlack::class, 'toNotifySlack', (new NotifySlack)->channel('C0123ABCD')->message('Relatório')->fileData('a,b', 'r.csv', 'Relatório')));
    aceito(e2eSend(NotifyChannelSlack::class, 'toNotifySlack', (new NotifySlack)->channel('C0123ABCD')->message('Depois')->scheduledAt(now()->addHour()->toIso8601String())->slackSchedule()));
});

it('Discord: embed, enquete, arquivos, silencioso, sem prévia e cor hex', function () {
    aceito(e2eSend(NotifyChannelDiscord::class, 'toNotifyDiscord', (new NotifyDiscord)->message('Deploy')->title('v1.2')->color('#2ecc71')->field('Status', 'ok')->footer('CI')->timestamp(false)->silent()->suppressEmbeds(false)));
    aceito(e2eSend(NotifyChannelDiscord::class, 'toNotifyDiscord', (new NotifyDiscord)->poll('Qual?', ['A', 'B'], 48, true)));
    aceito(e2eSend(NotifyChannelDiscord::class, 'toNotifyDiscord', (new NotifyDiscord)->message('Arquivos')->addFile(content: 'a,b', filename: 'r.csv')->addFile(url: 'https://exemplo.com/a.pdf')));
    aceito(e2eSend(NotifyChannelDiscord::class, 'toNotifyDiscord', (new NotifyDiscord)->embeds([['title' => 'Cru', 'description' => 'Embed', 'author' => ['name' => 'Bot']]])));
});

it('Teams: card com menção, fatos, ações, cor e largura total', function () {
    $teams = (new NotifyTeams)->title('Deploy')->message('Feito por <at>Ana</at>')->mention('ana@exemplo.com', 'Ana')
        ->color('#2ecc71')->fullWidth()->fact('Versão', '1.2')->action('Abrir', 'https://exemplo.com');

    aceito(e2eSend(NotifyChannelTeams::class, 'toNotifyTeams', $teams));
});

it('WebSocket: vários canais, privado, socket excluído e sem _notify', function () {
    aceito(e2eSend(NotifyChannelWebSocket::class, 'toNotifyWebSocket', (new NotifyWebSocket)->channels(['pedidos', 'painel'])->event('pedido.criado')->data(['id' => 1])->except('1234.5678')->notifyMeta(false)));
    aceito(e2eSend(NotifyChannelWebSocket::class, 'toNotifyWebSocket', (new NotifyWebSocket)->channel('user.1')->private()->event('ping')->data(['x' => 1])));
});

it('Webhook genérico: hmac, api key, formulário, DELETE e headers', function () {
    aceito(e2eSend(NotifyChannelWebhook::class, 'toNotifyWebhook', (new NotifyWebhook)->url('https://exemplo.com/hooks/teste-entregue')->payload(['a' => 1])->hmacAuth('segredo')->header('X-Origem', 'e2e')));
    aceito(e2eSend(NotifyChannelWebhook::class, 'toNotifyWebhook', (new NotifyWebhook)->url('https://exemplo.com/hooks/x')->apiKeyAuth('k', 'X-Chave')->asForm()->notifyMeta(false)->payload(['a' => '1'])));
    aceito(e2eSend(NotifyChannelWebhook::class, 'toNotifyWebhook', (new NotifyWebhook)->url('https://exemplo.com/hooks/x')->method('DELETE')->bearerAuth('t')->timeout(5)));
    aceito(e2eSend(NotifyChannelWebhook::class, 'toNotifyWebhook', (new NotifyWebhook)->payload(['usa' => 'default_url'])));
});

it('Opções comuns: agendamento, failover e chave de idempotência', function () {
    $sms = (new NotifySms)->content('Agendado')->scheduledAt(now()->addDay()->format('Y-m-d H:i'), 'America/Sao_Paulo')->failover()->idempotencyKey('e2e-'.uniqid());

    aceito(e2eSend(NotifyChannelSms::class, 'toNotifySms', $sms, ['sms' => '5511900000001']));
});

// ── Lote e multicanal ────────────────────────────────────────────────────────

it('Multicanal com mensagens de vários canais', function () {
    $r = NotifyQuery::multi()
        ->add((new NotifySms)->to('5511900000001')->content('Multi'))
        ->add((new NotifyMail)->to('entregue@sandbox.notify', 'Ana')->subject('Multi')->line('Olá')->from('loja@exemplo.com', 'Loja'))
        ->add((new NotifyTelegram)->chatId('123')->message('Multi')->copyButton('Copiar', 'X'))
        ->add((new NotifyWebSocket)->channel('pedidos')->event('e')->data(['a' => 1]))
        ->tag('e2e-multi')
        ->send();

    expect($r['status'] ?? null)->toBeTrue(json_encode($r, JSON_UNESCAPED_UNICODE))
        ->and(collect($r['results'] ?? [])->every(fn ($x) => $x['success']))->toBeTrue(json_encode($r, JSON_UNESCAPED_UNICODE));
});

it('Lote de e-mails com mensagens e defaults', function () {
    $r = NotifyQuery::server()->batch('mail', [
        (new NotifyMail)->to('entregue@sandbox.notify', 'Ana')->line('Olá Ana'),
        (new NotifyMail)->to('invalido@sandbox.notify', 'Bia')->line('Olá Bia'),
    ], ['defaults' => ['subject' => 'Lote', 'name_from' => 'Loja', 'email_from' => 'loja@exemplo.com', 'app_name' => 'Loja'], 'tag' => 'e2e-lote']);

    expect($r['status'] ?? null)->toBeTrue(json_encode($r, JSON_UNESCAPED_UNICODE))->and($r['count'] ?? 0)->toBe(2);
});

// ── Campanhas ────────────────────────────────────────────────────────────────

it('Campanhas de SMS, e-mail (com anexo, janela e teste A/B) e WhatsApp', function () {
    $sms = NotifyCampaignBuilder::sms()->name('E2E SMS')->content('Olá {name}')->contacts([['name' => 'Ana', 'phone' => '5511900000001']])->send();
    expect($sms['status'] ?? null)->toBeTrue(json_encode($sms, JSON_UNESCAPED_UNICODE));

    $mail = NotifyCampaignBuilder::email()->name('E2E Mail')->appName('Loja')->subject('Oi')->line('Olá')->from('loja@exemplo.com', 'Loja')
        ->attachUrl('https://exemplo.com/catalogo.pdf')
        ->sendWindow('09:00', '18:00')->abTest('Oi, tudo bem?', 20, 60)
        ->contacts(array_map(fn ($i) => ['name' => "Contato {$i}", 'email' => "contato{$i}@exemplo.com"], range(1, 20)))->send();
    expect($mail['status'] ?? null)->toBeTrue(json_encode($mail, JSON_UNESCAPED_UNICODE));

    $wa = NotifyCampaignBuilder::whatsapp()->name('E2E WA')->content('Olá {name}')->contacts([['name' => 'Ana', 'phone' => '5511900000001']])->send();
    expect($wa['status'] ?? null)->toBeTrue(json_encode($wa, JSON_UNESCAPED_UNICODE));
});

// ── Consultas, ações e configurações ─────────────────────────────────────────

it('Histórico, detalhe e cancelamento de um envio agendado', function () {
    $r = e2eSend(NotifyChannelSms::class, 'toNotifySms', (new NotifySms)->content('Cancelar')->scheduledAt(now()->addDay()->format('Y-m-d H:i')), ['sms' => '5511900000001']);
    $id = $r['notification_id'];

    expect(NotifyQuery::server()->sms()->tag('e2e')->status('sent')->perPage(5)->get())->toBeArray()
        ->and(NotifyQuery::server()->sms($id)->get()['id'] ?? null)->toBe($id)
        ->and(NotifyQuery::server()->sms()->cancel($id)['status'] ?? null)->toBeTrue();
});

it('Ações sobre mensagem enviada: os campos do pacote passam na validação do servidor', function (callable $acao) {
    // Sem o header de teste: com ele, as ações respondem 422 de propósito. O
    // notification_id não existe, então o servidor só pode reclamar dele.
    $GLOBALS['e2e_test_header'] = false;

    $r = $acao();
    $campos = array_keys($r['errors'] ?? []);

    // Sem erro de campo: o servidor parou no notification_id (inexistente).
    expect($r['http'] ?? null)->toBeIn([404, 422], json_encode($r, JSON_UNESCAPED_UNICODE))
        ->and(array_diff($campos, ['notification_id']))->toBe([], json_encode($r, JSON_UNESCAPED_UNICODE))
        ->and(json_encode($r))->not->toContain('modo de teste');
})->with([
    'telegram edit' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->edit('Novo', 'HTML')],
    'telegram edit-caption' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->editCaption('Legenda')],
    'telegram edit-media' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->editMedia('https://exemplo.com/a.png', 'photo', 'Leg', true)],
    'telegram edit-buttons' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->editButtons([[['text' => 'Copiar', 'copy_text' => ['text' => 'X']]]])],
    'telegram pin' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->pin(true)],
    'telegram unpin' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->unpin()],
    'telegram delete' => [fn () => NotifyQuery::server()->telegram('00000000-0000-0000-0000-000000000000')->delete()],
    'slack edit' => [fn () => NotifyQuery::server()->slack('00000000-0000-0000-0000-000000000000')->edit('Novo', extra: ['color' => '#2ecc71'])],
    'slack pin' => [fn () => NotifyQuery::server()->slack('00000000-0000-0000-0000-000000000000')->pin()],
    'slack react' => [fn () => NotifyQuery::server()->slack('00000000-0000-0000-0000-000000000000')->react('tada')],
    'slack delete' => [fn () => NotifyQuery::server()->slack('00000000-0000-0000-0000-000000000000')->delete()],
    'discord edit' => [fn () => NotifyQuery::server()->discord('00000000-0000-0000-0000-000000000000')->edit('Novo')],
    'discord delete' => [fn () => NotifyQuery::server()->discord('00000000-0000-0000-0000-000000000000')->delete()],
    'whatsapp edit' => [fn () => NotifyQuery::server()->whatsapp('00000000-0000-0000-0000-000000000000')->edit('Novo')],
    'whatsapp delete' => [fn () => NotifyQuery::server()->whatsapp('00000000-0000-0000-0000-000000000000')->delete()],
]);

it('Configurações: cria, lê, define padrão e apaga em cada canal só do cliente', function () {
    // Sem o header de teste: as rotas de configuração recusam o modo de teste.
    $GLOBALS['e2e_test_header'] = false;

    $server = NotifyQuery::server();
    $configs = [
        $server->teams()->config()->driver('webhook')->label('E2E Teams')->webhookUrl('https://prod-01.westus.logic.azure.com:443/workflows/abc')->themeColor('#0076D7'),
        $server->discord()->config()->driver('webhook')->label('E2E Discord')->webhookUrl('https://discord.com/api/webhooks/1/abc')->defaultUsername('Bot'),
        $server->slack()->config()->driver('slack')->label('E2E Slack')->webhookUrl('https://hooks.slack.com/services/T0/B0/x'),
        $server->websocket()->config()->driver('pusher')->label('E2E WS')->appId('1')->key('k')->secret('s')->cluster('us2')->maxPayloadKb(100)->encryptionMasterKey(base64_encode(random_bytes(32))),
        $server->webhook()->config()->driver('generic')->label('E2E Hook')->defaultUrl('https://exemplo.com/hook')->authType('hmac')->secret('s')->injectMetadata(false),
        $server->apns()->config()->driver('apns')->label('E2E APNs')->keyId('ABCDE12345')->teamId('ABCDE12345')->bundleId('com.e2e.app')->keyContent("-----BEGIN PRIVATE KEY-----\nMIGT\n-----END PRIVATE KEY-----")->production(false),
    ];

    foreach ($configs as $config) {
        $saved = $config->label('E2E '.uniqid())->save();
        expect($saved['status'] ?? null)->toBeTrue(json_encode($saved, JSON_UNESCAPED_UNICODE));
        $id = $saved['data']['id'];

        $channel = $saved['data']['channel'];
        expect($server->{$channel}()->config($id)->get()['id'] ?? null)->toBe($id)
            ->and($server->{$channel}()->config($id)->setDefault()['status'] ?? null)->toBeTrue()
            ->and($server->{$channel}()->config($id)->delete()['status'] ?? null)->toBeTrue();
    }
});

it('Modelos, listas, descadastros, entregas de webhook, uso e equipe', function () {
    $GLOBALS['e2e_test_header'] = false;

    $server = NotifyQuery::server();

    $tpl = $server->templates()->create('sms', 'E2E '.uniqid(), 'Código {{codigo}}');
    expect($tpl['status'] ?? null)->toBeTrue(json_encode($tpl, JSON_UNESCAPED_UNICODE));
    expect($server->templates()->delete($tpl['data']['id'])['status'] ?? null)->toBeTrue();

    $list = $server->contactLists()->create('sms', 'E2E '.uniqid());
    expect($list['status'] ?? null)->toBeTrue(json_encode($list, JSON_UNESCAPED_UNICODE));
    expect($server->contactLists()->addMembers($list['data']['id'], [['name' => 'Ana', 'phone' => '5511900000001']])['status'] ?? null)->toBeTrue();
    expect($server->contactLists()->delete($list['data']['id'])['status'] ?? null)->toBeTrue();

    expect($server->optOuts()->add(['5511911112222'])['status'] ?? null)->toBeTrue()
        ->and($server->webhookDeliveries()->all())->toBeArray()
        ->and($server->usage(7))->toBeArray()
        ->and($server->team()->members())->toBeArray();
});

it('Modelo salvo no envio: SMS e WhatsApp por template_id', function () {
    $GLOBALS['e2e_test_header'] = false;
    $nome = 'E2E código '.uniqid();
    NotifyQuery::server()->templates()->create('sms', $nome, 'Seu código é {{codigo}}');
    NotifyQuery::server()->templates()->create('whatsapp', $nome, 'Seu código é {{codigo}}');

    aceito(e2eSend(NotifyChannelSms::class, 'toNotifySms', (new NotifySms)->template($nome, ['codigo' => '4821'])->test(), ['sms' => '5511900000001']));
    aceito(e2eSend(NotifyChannelWhatsApp::class, 'toNotifyWhatsApp', (new NotifyWhatsApp)->template($nome, ['codigo' => '4821'])->test(), ['whatsapp' => '5511900000001']));
});

// ── Webhooks do servidor de volta para o pacote ──────────────────────────────

/**
 * Espera o servidor entregar os webhooks no receptor de teste
 * (NOTIFY_E2E_CAPTURE: um arquivo JSONL com path, headers e body de cada POST).
 */
function e2eCaptured(callable $match, int $min = 1, int $seconds = 20): array
{
    $file = (string) getenv('NOTIFY_E2E_CAPTURE');
    $deadline = time() + $seconds;

    do {
        $lines = is_file($file) ? array_filter(explode("\n", (string) file_get_contents($file))) : [];
        $found = array_values(array_filter(array_map(fn ($l) => json_decode($l, true), $lines), $match));

        if (count($found) >= $min) {
            return $found;
        }

        usleep(300_000);
    } while (time() < $deadline);

    return $found;
}

/** Passa a entrega capturada pelo middleware de assinatura e pelo controller do pacote. */
function e2eReceive(array $captured): array
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    foreach ($captured['headers'] as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    $request = Illuminate\Http\Request::create('/notify/webhook', 'POST', [], [], [], $server, $captured['body']);
    $request->setJson(new InputBag(json_decode($captured['body'], true) ?: []));

    $verified = (new VerifyNotifySignature)->handle(
        $request,
        fn ($r) => (new NotifyWebhookController)->notification($r)
    );

    return [$verified->getStatusCode(), json_decode($captured['body'], true)];
}

it('webhook_url: o pacote valida a assinatura do servidor e dispara o evento', function () {
    if (! getenv('NOTIFY_E2E_CAPTURE') || ! getenv('NOTIFY_E2E_RECEIVER')) {
        $this->markTestSkipped('Defina NOTIFY_E2E_CAPTURE e NOTIFY_E2E_RECEIVER (receptor de webhooks).');
    }

    config(['notify.webhook_secret' => getenv('NOTIFY_E2E_SECRETS')]);
    Event::fake([NotifyWebhookEvent::class]);

    $r = e2eSend(NotifyChannelSms::class, 'toNotifySms', (new NotifySms)->content('Webhook')->webhookUrl(rtrim(getenv('NOTIFY_E2E_RECEIVER'), '/').'/direto'), ['sms' => '5511900000001']);
    aceito($r);

    $entregas = e2eCaptured(fn ($c) => $c['path'] === '/direto' && str_contains($c['body'], $r['notification_id']), 2);
    expect($entregas)->not->toBeEmpty('Nenhum webhook chegou ao receptor.');

    foreach ($entregas as $entrega) {
        expect($entrega['headers'])->toHaveKeys(['X-WEBHOOK-SIGNATURE', 'X-WEBHOOK-TIMESTAMP', 'X-WEBHOOK-ID', 'X-WEBHOOK-EVENT']);
        [$status, $body] = e2eReceive($entrega);
        expect($status)->toBe(200, 'Assinatura recusada: '.json_encode($entrega['headers']))
            ->and($body)->toHaveKeys(['id', 'event', 'created_at', 'data'])
            ->and($body['id'])->toBe($entrega['headers']['X-WEBHOOK-ID']);
    }

    Event::assertDispatched(NotifyWebhookEvent::class, fn ($e) => $e->notificationId === $r['notification_id'] && $e->is('created') && $e->test);
});

it('webhooks da conta: envelope aberto, assinatura com timestamp e campanha no evento certo', function () {
    if (! getenv('NOTIFY_E2E_CAPTURE')) {
        $this->markTestSkipped('Defina NOTIFY_E2E_CAPTURE (receptor de webhooks).');
    }

    config(['notify.webhook_secret' => getenv('NOTIFY_E2E_SECRETS')]);
    Event::fake([NotifyWebhookEvent::class, NotifyCampaignWebhookEvent::class]);

    $r = e2eSend(NotifyChannelSms::class, 'toNotifySms', (new NotifySms)->content('Conta'), ['sms' => '5511900000001']);
    aceito($r);

    $campanha = NotifyCampaignBuilder::sms()->name('E2E conta')->content('Oi {name}')->contacts([['name' => 'Ana', 'phone' => '5511900000001']])->send();

    $entregas = [
        ...e2eCaptured(fn ($c) => $c['path'] === '/conta' && str_contains($c['body'], $r['notification_id'])),
        ...e2eCaptured(fn ($c) => $c['path'] === '/conta' && str_contains($c['body'], (string) ($campanha['campaign_id'] ?? '-'))),
    ];
    expect($entregas)->not->toBeEmpty('Nenhum webhook da conta chegou ao receptor.');

    foreach ($entregas as $entrega) {
        expect($entrega['headers'])->toHaveKey('X-WEBHOOK-TIMESTAMP');
        [$status, $body] = e2eReceive($entrega);
        expect($status)->toBe(200, 'Assinatura recusada: '.json_encode($entrega['headers']))
            ->and($body)->toHaveKeys(['id', 'event', 'created_at', 'data']);
    }

    Event::assertDispatched(NotifyWebhookEvent::class, fn ($e) => $e->notificationId === $r['notification_id'] && str_starts_with((string) ($e->payload['delivery_id'] ?? ''), 'evt_'));
    Event::assertDispatched(NotifyCampaignWebhookEvent::class, fn ($e) => $e->campaignId === ($campanha['campaign_id'] ?? null) && str_starts_with((string) $e->event, 'campaign.'));
});
