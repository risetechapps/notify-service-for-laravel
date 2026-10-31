<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWhatsAppEvent;
use RiseTechApps\Notify\Http\Controllers\NotifyWebhookController;
use RiseTechApps\Notify\Http\Middleware\VerifyNotifySignature;
use RiseTechApps\Notify\Message\NotifyMail;
use RiseTechApps\Notify\Message\NotifySms;
use RiseTechApps\Notify\Message\NotifyTelegram;
use RiseTechApps\Notify\NotifyQuery;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formatos do servidor depois da revisão de canais (007591e): assinatura
 * X-Webhook-Signature (com e sem timestamp), envelope dos webhooks da conta,
 * eventos `notification.*` e o envio por lote/multicanal com mensagens.
 */
beforeEach(function () {
    config()->set('notify.webhook_secret', 'segredo-conta, segredo-endpoint');
    config()->set('notify.webhook_verify', true);
    config()->set('notify.webhook_tolerance', 300);
});

function assinado(string $body, array $headers): Response
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return (new VerifyNotifySignature)->handle(
        Request::create('/notify/webhook', 'POST', [], [], [], $server, $body),
        fn () => new JsonResponse(['ok' => true])
    );
}

// ── Assinatura ───────────────────────────────────────────────────────────────

it('recusa X-Webhook-Signature sem o timestamp (o HMAC só do corpo não vale)', function () {
    $body = '{"event":"notification.sent","notification_id":"n1"}';

    expect(assinado($body, ['X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, 'segredo-conta')])->getStatusCode())->toBe(403);
});

it('aceita X-Webhook-Signature com timestamp no material, com qualquer segredo configurado', function () {
    $body = '{"id":"evt_1","event":"notification.sent","created_at":"2026-10-31T12:00:00Z","data":{"notification_id":"n1"}}';
    $t = (string) time();

    expect(assinado($body, ['X-Webhook-Timestamp' => $t, 'X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', "{$t}.{$body}", 'segredo-endpoint')])->getStatusCode())->toBe(200)
        // Sem o timestamp no material não confere.
        ->and(assinado($body, ['X-Webhook-Timestamp' => $t, 'X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, 'segredo-endpoint')])->getStatusCode())->toBe(403);
});

it('recusa o webhook da conta fora da janela', function () {
    $body = '{"event":"x"}';
    $t = (string) (time() - 3600);

    expect(assinado($body, ['X-Webhook-Timestamp' => $t, 'X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', "{$t}.{$body}", 'segredo-conta')])->getStatusCode())->toBe(403);
});

it('recusa X-Webhook-Signature malformado', function () {
    expect(assinado('{}', ['X-Webhook-Signature' => 't=1,v1=abc'])->getStatusCode())->toBe(403);
});

it('segue aceitando o formato do servidor 1.0.0', function () {
    $body = '{"event":"sent"}';
    $t = time();

    expect(assinado($body, ['X-Notify-Signature' => sprintf('t=%d,v1=%s', $t, hash_hmac('sha256', "{$t}.{$body}", 'segredo-endpoint'))])->getStatusCode())->toBe(200);
});

// ── Encaminhamento ───────────────────────────────────────────────────────────

it('abre o envelope da conta e dispara o evento de notificação', function () {
    Event::fake();

    $request = Request::create('/', 'POST', [
        'id' => 'evt_1', 'event' => 'notification.sent', 'created_at' => '2026-10-31T12:00:00Z',
        'data' => ['notification_id' => 'n1', 'channel' => 'sms', 'status' => 'sent', 'provider_id' => 99, 'test' => true],
    ]);

    (new NotifyWebhookController)->notification($request);

    Event::assertDispatched(NotifyWebhookEvent::class, fn ($e) => $e->notificationId === 'n1' && $e->type === 'sms'
        && $e->providerId === '99' && $e->test && $e->is('sent') && $e->name() === 'sent'
        && $e->payload['delivery_id'] === 'evt_1');
});

it('campanha e WhatsApp chegando na mesma URL vão para o evento certo', function () {
    Event::fake();
    $controller = new NotifyWebhookController;

    $controller->notification(Request::create('/', 'POST', ['event' => 'campaign.completed', 'campaign_id' => 'c1', 'status' => 'completed', 'skipped' => 2]));
    $controller->notification(Request::create('/', 'POST', ['event' => 'whatsapp.message.received', 'event_id' => 7]));
    $controller->campaign(Request::create('/', 'POST', ['event' => 'notification.failed', 'notification_id' => 'n2']));

    Event::assertDispatched(NotifyCampaignWebhookEvent::class, fn ($e) => $e->campaignId === 'c1' && $e->event === 'campaign.completed');
    Event::assertDispatched(NotifyWhatsAppEvent::class);
    Event::assertDispatched(NotifyWebhookEvent::class, fn ($e) => $e->is('notification.failed', 'error'));
});

it('is() aceita o nome antigo e o novo', function () {
    $antigo = new NotifyWebhookEvent('sent', 'n', null, 'sent', 'sms');
    $novo = new NotifyWebhookEvent('notification.delivered', 'n', null, 'delivered', 'sms');

    expect($antigo->is('notification.sent'))->toBeTrue()
        ->and($novo->is('delivered'))->toBeTrue()
        ->and($novo->is('sent'))->toBeFalse();
});

// ── Envio ────────────────────────────────────────────────────────────────────

it('multicanal com mensagens: canal, config, webhook, failover, modelo e opções', function () {
    Http::fake(['*' => Http::response(['status' => true], 202)]);
    config()->set('notify.key', 'k');

    NotifyQuery::multi()
        ->add((new NotifySms)->to('5521999998888')->template('Código', ['codigo' => '1'])->configId('cfg-sms')->failover())
        ->add((new NotifyMail)->to('ana@x.com')->subject('Oi')->line('Olá'), webhookUrl: 'https://cb.exemplo/mail')
        ->add((new NotifyTelegram)->chatId('123')->message('Oi'))
        ->tag('lote-1')->scheduledAt('2026-12-01 09:00', 'America/Sao_Paulo')->test()->idempotencyKey('m-1')
        ->send();

    Http::assertSent(function ($r) {
        $c = $r['channels'];

        return str_ends_with($r->url(), '/api/v1/send/multi')
            && $r->hasHeader('X-Notify-Test', 'true') && $r->hasHeader('Idempotency-Key', 'm-1')
            && $r['tag'] === ['lote-1'] && $r['timezone'] === 'America/Sao_Paulo'
            && $c[0]['channel'] === 'sms' && $c[0]['config_id'] === 'cfg-sms' && $c[0]['failover'] === true
            && $c[0]['data']['template_id'] === 'Código' && ! isset($c[0]['data']['config_id'])
            && $c[1]['channel'] === 'email' && $c[1]['webhook_url'] === 'https://cb.exemplo/mail'
            && $c[2]['channel'] === 'telegram';
    });
});

it('lote aceita mensagens, troca mail por email e liga o modo de teste', function () {
    Http::fake(['*' => Http::response(['status' => true], 202)]);

    NotifyQuery::server()->batch('mail', [(new NotifyMail)->to('a@x.com')->subject('S')->line('L'), ['email' => 'b@x.com']], ['test' => true, 'defaults' => ['subject' => 'Padrão']]);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v1/send/batch') && $r['channel'] === 'email'
        && $r['messages'][0]['email'] === 'a@x.com' && $r['messages'][1] === ['email' => 'b@x.com']
        && $r['defaults'] === ['subject' => 'Padrão'] && ! isset($r['test']) && $r->hasHeader('X-Notify-Test', 'true'));
});

it('APNs: chave .p8 por conteúdo', function () {
    Http::fake(['*' => Http::response(['status' => true, 'data' => []], 201)]);

    NotifyQuery::server()->apns()->config()->driver('apns')->label('iOS')->keyId('K')->teamId('T')->bundleId('b')->keyContent("-----BEGIN PRIVATE KEY-----\nx\n-----END PRIVATE KEY-----")->save();

    Http::assertSent(fn ($r) => str_contains((string) ($r['credentials']['key_content'] ?? ''), 'BEGIN PRIVATE KEY'));
});
