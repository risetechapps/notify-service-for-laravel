<?php

namespace RiseTechApps\Notify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RiseTechApps\Notify\Events\NotifyWhatsAppEvent;
use RiseTechApps\Notify\NotifyQuery;

/**
 * Acompanha os eventos do chat pelo GET /chat/events (long polling) e
 * dispara NotifyWhatsAppEvent para cada um, uma vez só por event_id (o
 * webhook pode ter entregado antes). O cursor fica no cache: ao reiniciar,
 * continua de onde parou.
 *
 *   php artisan notify:whatsapp-events            # roda até ser parado (supervisor)
 *   php artisan notify:whatsapp-events --once     # uma passada (agendador)
 */
class WhatsAppEventsCommand extends Command
{
    public const CURSOR_KEY = 'notify:whatsapp-events:cursor';

    protected $signature = 'notify:whatsapp-events
        {--once : Uma passada só}
        {--wait=25 : Segundos de long polling por chamada (0 a 25)}
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

        $types = array_filter(array_map('trim', explode(',', (string) $this->option('types'))));

        do {
            $query = NotifyQuery::server()->chat()->events()->after($cursor)->wait((int) $this->option('wait'))->limit(500);

            if ($types) {
                $query->types($types);
            }

            $page = $query->get();

            if (isset($page['http'])) {
                $this->error('Falha ao ler os eventos: HTTP '.$page['http'].' '.($page['message'] ?? ''));
                sleep(5);

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
        } while (! $this->option('once') || ! empty($page['has_more']));

        return self::SUCCESS;
    }
}
