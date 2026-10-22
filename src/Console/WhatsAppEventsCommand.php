<?php

namespace RiseTechApps\Notify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use RiseTechApps\Notify\Events\NotifyWhatsAppEvent;
use RiseTechApps\Notify\NotifyQuery;

/**
 * Acompanha os eventos do chat pelo GET /chat/events e dispara
 * NotifyWhatsAppEvent para cada um, uma vez só por event_id (o webhook pode
 * ter entregado antes). O cursor fica no cache: ao reiniciar, continua de
 * onde parou.
 *
 * O servidor responde na hora. Sem novidade, o comando espera o Retry-After
 * (ou --interval) antes da próxima chamada; no 429, o tempo que o servidor
 * pedir. Sem essa pausa, o laço gastava o limite de requisições da conta.
 *
 *   php artisan notify:whatsapp-events            # roda até ser parado (supervisor)
 *   php artisan notify:whatsapp-events --once     # uma passada (agendador)
 */
class WhatsAppEventsCommand extends Command
{
    public const CURSOR_KEY = 'notify:whatsapp-events:cursor';

    protected $signature = 'notify:whatsapp-events
        {--once : Uma passada só}
        {--interval=10 : Segundos entre as consultas sem novidade, quando o servidor não informa}
        {--wait= : Obsoleta: o servidor não faz mais long polling (ignorada)}
        {--types= : Só estes tipos, separados por vírgula (ex.: message.received,conversation.updated)}
        {--from-now : Ignora o cursor salvo e começa do agora}';

    protected $description = 'Acompanha os eventos do chat do WhatsApp no servidor e dispara NotifyWhatsAppEvent';

    public function handle(): int
    {
        $events = NotifyQuery::server()->chat()->events();
        $cursor = $this->option('from-now') ? null : Cache::get(self::CURSOR_KEY);

        if ($cursor === null) {
            $cursor = $events->latest();
            Cache::forever(self::CURSOR_KEY, $cursor);
        }

        if ($this->option('wait') !== null) {
            $this->warn('--wait é obsoleta e foi ignorada: o servidor não faz mais long polling. Use --interval.');
        }

        $types = array_filter(array_map('trim', explode(',', (string) $this->option('types'))));
        $interval = max(1, (int) $this->option('interval'));

        do {
            $query = NotifyQuery::server()->chat()->events()->after($cursor)->limit(500);

            if ($types) {
                $query->types($types);
            }

            $page = $query->get();

            if (isset($page['http'])) {
                $this->error('Falha ao ler os eventos: HTTP '.$page['http'].' '.($page['message'] ?? ''));

                if (! $this->option('once')) {
                    Sleep::for(max(5, $page['retry_after'] ?? 5))->seconds();
                }

                continue;
            }

            foreach ($page['data'] ?? [] as $item) {
                NotifyWhatsAppEvent::dispatchOnce('whatsapp.'.$item['type'], (int) $item['id'], [
                    ...(array) ($item['payload'] ?? []),
                    'event' => 'whatsapp.'.$item['type'],
                    'event_id' => (int) $item['id'],
                    'occurred_at' => $item['occurred_at'] ?? null,
                ]);
            }

            $cursor = (int) ($page['next'] ?? $cursor);
            Cache::forever(self::CURSOR_KEY, $cursor);

            // Com has_more ainda há fila: segue sem pausa.
            if (empty($page['has_more']) && ! $this->option('once')) {
                Sleep::for(max(1, $page['retry_after'] ?? $interval))->seconds();
            }
        } while (! $this->option('once') || ! empty($page['has_more']));

        return self::SUCCESS;
    }
}
