<?php

use RiseTechApps\Notify\NotifyQuery;
use RiseTechApps\Notify\ServerChannelQuery;
use RiseTechApps\Notify\ServerQuery;

it('notifyQuery helper returns ServerQuery', function () {
    $query = notifyQuery();

    expect($query)->toBeInstanceOf(ServerQuery::class);
});

it('notifyQuery helper delegates to NotifyQuery::server()', function () {
    $fromHelper = notifyQuery();
    $fromDirect = NotifyQuery::server();

    expect(get_class($fromHelper))->toBe(get_class($fromDirect));
});

it('notifyQuery helper allows chaining server methods', function () {
    $query = notifyQuery()->sms();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});
