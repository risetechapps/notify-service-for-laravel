<?php

use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Notify;
use RiseTechApps\Notify\NotifyCampaignBuilder;

beforeEach(function () {
    config(['notify.webhook' => null]);
    Http::fake([
        Notify::BASE_URL.'/api/v1/send/campaigns/whatsapp/media' => Http::response(['status' => true, 'data' => ['path' => 'campaigns/x.mp4', 'media_type' => 'video', 'file_name' => 'promo.mp4']]),
        Notify::BASE_URL.'/api/v1/send/campaigns/*' => Http::response(['status' => true, 'campaign_id' => 'camp-1'], 202),
    ]);
});

function lastCampaign(): Illuminate\Http\Client\Request
{
    return Http::recorded()->map(fn ($pair) => $pair[0])->last();
}

it('e-mail usa a chave email nos contatos; SMS usa phone', function () {
    NotifyCampaignBuilder::email()->name('News')->subject('Oi')->contacts(['email' => 'a@x.com', 'name' => 'A'])->send();
    expect(lastCampaign()->url())->toBe(Notify::BASE_URL.'/api/v1/send/campaigns/mail')
        ->and(lastCampaign()['contacts'])->toBe([['email' => 'a@x.com', 'name' => 'A']]);

    NotifyCampaignBuilder::sms()->name('Promo')->content('Oi')->fromCollection(collect([['tel' => '5521911111111']]), 'tel')->send();
    expect(lastCampaign()['contacts'])->toBe([['phone' => '5521911111111']]);
});

it('WhatsApp: texto, mídia por URL, rodapé de SAIR e sem rate_per_minute', function () {
    NotifyCampaignBuilder::whatsapp()->name('Black Friday')
        ->content('{Olá|Oi} {name}!')
        ->media('https://cdn.test/p.jpg', 'image')
        ->optOutFooter(true, 'Responda SAIR para parar.')
        ->ratePerMinute(10)
        ->contacts([['phone' => '5521911111111', 'name' => 'Ana']])
        ->send();

    $request = lastCampaign();
    expect($request->url())->toBe(Notify::BASE_URL.'/api/v1/send/campaigns/whatsapp')
        ->and($request['template'])->toBe(['content' => '{Olá|Oi} {name}!', 'media_url' => 'https://cdn.test/p.jpg', 'media_type' => 'image', 'opt_out_footer' => true, 'opt_out_text' => 'Responda SAIR para parar.'])
        ->and($request->data())->not->toHaveKey('rate_per_minute')
        ->and($request['contacts'])->toBe([['phone' => '5521911111111', 'name' => 'Ana']]);
});

it('WhatsApp: mediaFile envia o arquivo antes e usa o media_path', function () {
    $file = tempnam(sys_get_temp_dir(), 'nt');
    file_put_contents($file, 'video');

    NotifyCampaignBuilder::whatsapp()->name('Vídeo')->mediaFile($file, 'promo.mov')->contacts([['phone' => '5521911111111']])->send();

    $requests = Http::recorded()->map(fn ($pair) => $pair[0])->values();
    expect($requests[0]->url())->toBe(Notify::BASE_URL.'/api/v1/send/campaigns/whatsapp/media')
        ->and($requests[1]['template'])->toMatchArray(['media_path' => 'campaigns/x.mp4', 'media_type' => 'video', 'file_name' => 'promo.mp4'])
        ->and($requests[1]['template'])->not->toHaveKey('media_url');

    unlink($file);
});

it('WhatsApp sem texto nem mídia é recusado; com modelo salvo, não', function () {
    expect(fn () => NotifyCampaignBuilder::whatsapp()->name('X')->contacts([['phone' => '1']])->send())
        ->toThrow(InvalidArgumentException::class);

    NotifyCampaignBuilder::whatsapp()->name('X')->template('boas-vindas')->contacts([['phone' => '1']])->send();
    expect(lastCampaign()['template_id'])->toBe('boas-vindas');
});

it('lista salva, janela de envio, teste A/B e cabeçalhos de idempotência e teste', function () {
    NotifyCampaignBuilder::email()->name('News')->subject('A')
        ->listId('list-1')
        ->sendWindow('09:00', '18:00', [1, 2, 3, 4, 5], 'America/Sao_Paulo')
        ->abTest('B', 30, 120)
        ->idempotencyKey('news-1')
        ->test()
        ->send();

    $request = lastCampaign();
    expect($request['list_id'])->toBe('list-1')
        ->and($request->data())->not->toHaveKey('contacts')
        ->and($request['send_window'])->toBe(['start' => '09:00', 'end' => '18:00', 'days' => [1, 2, 3, 4, 5], 'timezone' => 'America/Sao_Paulo'])
        ->and($request['ab_test'])->toBe(['subject_b' => 'B', 'sample_percent' => 30, 'wait_minutes' => 120])
        ->and($request->header('Idempotency-Key'))->toBe(['news-1'])
        ->and($request->header('X-Notify-Test'))->toBe(['true'])
        ->and($request->data())->not->toHaveKey('credentials');
});
