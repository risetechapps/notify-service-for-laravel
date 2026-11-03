<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\NotifyQuery;

/**
 * Atalhos do fluxo de autoatendimento: leem o fluxo salvo, trocam só a parte
 * pedida e salvam o resto como estava.
 */
function fakeFlow(array $definition): void
{
    Http::fake([
        '*/api/v1/chat/bot-flows/flow-1' => fn (Request $r) => $r->method() === 'GET'
            ? Http::response(['status' => true, 'data' => ['id' => 'flow-1', 'name' => 'Principal', 'definition' => $definition]])
            : Http::response(['status' => true, 'data' => ['id' => 'flow-1']]),
    ]);
}

function savedDefinition(): array
{
    $put = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => $r->method() === 'PUT');
    expect($put)->not->toBeNull()
        ->and($put['name'])->toBe('Principal');

    return $put['definition'];
}

$menu = ['text' => 'Escolha', 'options' => [['label' => 'Boleto', 'action' => ['type' => 'reply', 'text' => 'Segue']]]];

it('define o horário em formatos curtos e mantém o menu', function () use ($menu) {
    fakeFlow(['menu' => $menu, 'business_hours' => ['away_message' => 'Mensagem antiga']]);

    NotifyQuery::server()->chat()->botFlows()->updateBusinessHours('flow-1', [
        'MON' => '08:00-18:00',
        'tue' => ['08:00-12:00', '13:00 - 18:00'],
        'sat' => [['start' => '08:00', 'end' => '12:00']],
        'sun' => ['start' => '09:00', 'end' => '11:00'],
    ], outsideHours: 'menu');

    $definition = savedDefinition();

    expect($definition['menu'])->toBe($menu)
        ->and($definition['outside_hours'])->toBe('menu')
        ->and($definition['business_hours'])->toBe([
            'enabled' => true,
            'timezone' => 'America/Sao_Paulo',
            'days' => [
                'mon' => [['start' => '08:00', 'end' => '18:00']],
                'tue' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '18:00']],
                'sat' => [['start' => '08:00', 'end' => '12:00']],
                'sun' => [['start' => '09:00', 'end' => '11:00']],
            ],
            'away_message' => 'Mensagem antiga',
        ]);
});

it('troca só a mensagem de fora do expediente', function () use ($menu) {
    $hours = ['enabled' => true, 'timezone' => 'America/Manaus', 'days' => ['mon' => [['start' => '08:00', 'end' => '18:00']]], 'away_message' => 'Antiga'];
    fakeFlow(['menu' => $menu, 'business_hours' => $hours, 'outside_hours' => 'handoff']);

    NotifyQuery::server()->chat()->botFlows()->updateAwayMessage('flow-1', 'Voltamos às 8h.');

    $definition = savedDefinition();

    expect($definition['business_hours'])->toBe(['away_message' => 'Voltamos às 8h.'] + $hours)
        ->and($definition['outside_hours'])->toBe('handoff');
});

it('desliga o horário guardando os dias', function () use ($menu) {
    fakeFlow(['menu' => $menu, 'business_hours' => ['enabled' => true, 'days' => ['mon' => [['start' => '08:00', 'end' => '18:00']]]]]);

    NotifyQuery::server()->chat()->botFlows()->disableBusinessHours('flow-1');

    expect(savedDefinition()['business_hours'])->toBe(['enabled' => false, 'days' => ['mon' => [['start' => '08:00', 'end' => '18:00']]]]);
});

it('patch troca e remove chaves do fluxo e mantém o nome', function () use ($menu) {
    fakeFlow(['menu' => $menu, 'greeting' => 'Oi', 'max_invalid' => 3]);

    NotifyQuery::server()->chat()->botFlows()->patch('flow-1', ['greeting' => null, 'max_invalid' => 2, 'close_message' => 'Até logo!']);

    expect(savedDefinition())->toBe(['menu' => $menu, 'max_invalid' => 2, 'close_message' => 'Até logo!']);
});

it('não salva nada se a leitura do fluxo falhar', function () {
    Http::fake(['*' => Http::response(['status' => false, 'message' => 'Não encontrado.'], 404)]);

    $result = NotifyQuery::server()->chat()->botFlows()->updateAwayMessage('flow-x', 'Oi');

    expect($result['http'])->toBe(404);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT');
});

it('recusa dia ou modo inválidos antes de chamar o servidor', function () {
    Http::fake();
    $flows = NotifyQuery::server()->chat()->botFlows();

    expect(fn () => $flows->updateBusinessHours('flow-1', ['segunda' => '08:00-18:00']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $flows->updateBusinessHours('flow-1', ['mon' => '08:00-18:00'], outsideHours: 'fechar'))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});
