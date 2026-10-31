<?php

namespace RiseTechApps\Notify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWhatsAppEvent;

/**
 * Recebe callbacks de status do servidor de notificações e os repassa como
 * eventos Laravel. O pacote não persiste nada localmente — escute os eventos
 * (NotifyWebhookEvent / NotifyCampaignWebhookEvent / NotifyWhatsAppEvent).
 *
 * As rotas são registradas automaticamente com notify.routes = true, já protegidas
 * pelo middleware de assinatura HMAC (VerifyNotifySignature). Para registrar
 * manualmente, aplique o alias 'notify.signature':
 *
 *   Route::post('/notify/webhook',          [NotifyWebhookController::class, 'notification'])
 *       ->middleware('notify.signature');
 *   Route::post('/notify/webhook/campaign', [NotifyWebhookController::class, 'campaign'])
 *       ->middleware('notify.signature');
 *   Route::post('/notify/webhook/whatsapp', [NotifyWebhookController::class, 'whatsapp'])
 *       ->middleware('notify.signature');
 *
 * Qualquer uma das três rotas aceita qualquer evento e o encaminha pelo nome:
 * útil para os webhooks da conta (painel → Webhooks), que mandam todos os
 * eventos assinados para uma URL só.
 *
 * Notificação (webhook_url da requisição):
 * {
 *   "event": "notification.sent",   // notification.created|sending|sent|delivered|error|failed|cancelled…
 *   "channel": "telegram",          // vira $type no NotifyWebhookEvent
 *   "notification_id": "uuid",
 *   "provider_id": "11",            // id da mensagem no provedor
 *   "status": "sent",
 *   "tag": ["pedido"],
 *   "timestamp": "2026-10-31T12:00:00-03:00",
 *   "test": true,                   // só no modo de teste
 *   "error": "mensagem de erro"     // em notification.error / failed
 * }
 *
 * Campanha: campaign.created | campaign.started | campaign.completed (com
 * sent, failed, skipped, started_at e finished_at) e paused, cancelled etc.
 * {
 *   "event": "campaign.completed", "campaign_id": "uuid", "channel": "sms",
 *   "status": "completed", "total": 1000, "sent": 950, "failed": 50, "progress": 100
 * }
 *
 * Webhooks da conta: o mesmo conteúdo dentro de {id, event, created_at, data};
 * o envelope é aberto antes de disparar o evento (o `id` vira `delivery_id`).
 */
class NotifyWebhookController extends Controller
{
    /**
     * Repassa o callback de uma notificação como NotifyWebhookEvent. Eventos de
     * campanha e do WhatsApp que chegarem aqui seguem para o método certo.
     */
    public function notification(Request $request): JsonResponse
    {
        $payload = self::unwrap($request->all());

        return match (self::kind($payload)) {
            'campaign' => $this->dispatchCampaign($payload),
            'whatsapp' => $this->dispatchWhatsApp($payload),
            default => $this->dispatchNotification($payload),
        };
    }

    /**
     * Repassa o callback de uma campanha como NotifyCampaignWebhookEvent.
     */
    public function campaign(Request $request): JsonResponse
    {
        $payload = self::unwrap($request->all());

        return self::kind($payload) === 'notification'
            ? $this->dispatchNotification($payload)
            : $this->dispatchCampaign($payload);
    }

    /**
     * Webhook do chat do WhatsApp (e dos avisos de conexão): aponte o
     * webhook_url das configurações do chat (e dos avisos) para esta rota.
     * Dispara NotifyWhatsAppEvent uma vez por event_id.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        $payload = self::unwrap($request->all());

        return match (self::kind($payload)) {
            'notification' => $this->dispatchNotification($payload),
            'campaign' => $this->dispatchCampaign($payload),
            default => $this->dispatchWhatsApp($payload),
        };
    }

    /**
     * Abre o envelope dos webhooks da conta ({id, event, created_at, data}):
     * o conteúdo de `data` sobe para o nível de cima, com o `event`, o
     * `timestamp` (created_at) e o `delivery_id` (id). Outros formatos passam
     * como chegaram.
     */
    public static function unwrap(array $payload): array
    {
        if (! isset($payload['event'], $payload['data']) || ! is_array($payload['data']) || ! array_key_exists('created_at', $payload)) {
            return $payload;
        }

        return [
            ...$payload['data'],
            'event' => $payload['event'],
            'timestamp' => $payload['created_at'],
            'delivery_id' => $payload['id'] ?? null,
        ];
    }

    /** notification | campaign | whatsapp, pelo conteúdo do evento. */
    public static function kind(array $payload): string
    {
        $event = (string) ($payload['event'] ?? '');

        if (filled($payload['notification_id'] ?? null)) {
            return 'notification';
        }

        if (str_starts_with($event, 'campaign.') || filled($payload['campaign_id'] ?? null)) {
            return 'campaign';
        }

        if (str_starts_with($event, 'whatsapp.') || array_key_exists('event_id', $payload)) {
            return 'whatsapp';
        }

        return 'notification';
    }

    protected function dispatchNotification(array $payload): JsonResponse
    {
        Event::dispatch(new NotifyWebhookEvent(
            event: $payload['event'] ?? null,
            notificationId: $payload['notification_id'] ?? null,
            providerId: isset($payload['provider_id']) ? (string) $payload['provider_id'] : null,
            status: $payload['status'] ?? null,
            // O servidor manda o canal em `channel`; `type` fica para quem
            // ainda recebe o formato antigo.
            type: $payload['channel'] ?? $payload['type'] ?? null,
            payload: $payload,
            test: ! empty($payload['test']),
        ));

        return response()->json(['ok' => true]);
    }

    protected function dispatchCampaign(array $payload): JsonResponse
    {
        Event::dispatch(new NotifyCampaignWebhookEvent(
            campaignId: $payload['campaign_id'] ?? null,
            status: $payload['status'] ?? null,
            payload: $payload,
            event: $payload['event'] ?? null,
        ));

        return response()->json(['ok' => true]);
    }

    protected function dispatchWhatsApp(array $payload): JsonResponse
    {
        $event = (string) ($payload['event'] ?? '');

        if ($event !== '') {
            $eventId = $payload['event_id'] ?? null;
            NotifyWhatsAppEvent::dispatchOnce($event, $eventId !== null ? (int) $eventId : null, $payload);
        }

        return response()->json(['ok' => true]);
    }
}
