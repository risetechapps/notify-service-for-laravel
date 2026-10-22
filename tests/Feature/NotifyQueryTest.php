<?php

use RiseTechApps\Notify\NotifyMultiBuilder;
use RiseTechApps\Notify\NotifyQuery;
use RiseTechApps\Notify\ServerCampaignQuery;
use RiseTechApps\Notify\ServerChannelQuery;
use RiseTechApps\Notify\ServerQuery;

it('can be instantiated', function () {
    $query = new NotifyQuery;

    expect($query)->toBeInstanceOf(NotifyQuery::class);
});

it('server returns ServerQuery instance', function () {
    $server = NotifyQuery::server();

    expect($server)->toBeInstanceOf(ServerQuery::class);
});

it('multi returns NotifyMultiBuilder instance', function () {
    $multi = NotifyQuery::multi([
        ['channel' => 'sms', 'data' => ['to' => '+5511999999999', 'content' => 'Olá!']],
    ]);

    expect($multi)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('multi returns NotifyMultiBuilder with webhook', function () {
    $multi = NotifyQuery::multi([], 'https://example.com/webhook');

    expect($multi)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('server sms returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->sms();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server sms with id returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->sms('some-uuid');

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server mail returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->mail();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server push returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->push();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server apns returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->apns();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server telegram returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->telegram();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server slack returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->slack();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server discord returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->discord();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server teams returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->teams();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server websocket returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->websocket();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server webhook returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->webhook();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server campaigns returns ServerCampaignQuery', function () {
    $query = NotifyQuery::server()->campaigns('sms');

    expect($query)->toBeInstanceOf(ServerCampaignQuery::class);
});

it('server drivers returns array', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/configurations/drivers' => Http::response(['data' => [['driver' => 'twilio']]], 200),
    ]);

    $result = NotifyQuery::server()->drivers();

    expect($result)->toBeArray();
});

it('server configurations returns array', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/configurations*' => Http::response(['data' => [['id' => '1']]], 200),
    ]);

    $result = NotifyQuery::server()->configurations('sms');

    expect($result)->toBeArray();
});
