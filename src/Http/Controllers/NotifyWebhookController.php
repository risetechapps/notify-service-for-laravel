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
 * (NotifyWebhookEvent / NotifyCampaignWebhookEvent) para reagir.
 *
 * As rotas são registradas automaticamente com notify.routes = true, já protegidas
 * pelo middleware de assinatura HMAC (VerifyNotifySignature). Para registrar
 * manualmente, aplique o alias 'notify.signature':
 *
 *   Route::post('/notify/webhook',          [NotifyWebhookController::class, 'notification'])
 *       ->middleware('notify.signature');
 *   Route::post('/notify/webhook/campaign', [NotifyWebhookController::class, 'campaign'])
 *       ->middleware('notify.signature');
 *
 * Formato enviado pelo servidor para notificação individual:
 * {
 *   "event": "created|sending|sent|delivered|error|failed|cancelled",
 *   "channel": "telegram",        // vira $type no NotifyWebhookEvent
 *   "notification_id": "uuid",
 *   "provider_id": "11",          // id da mensagem no provedor (ex.: message_id do Telegram)
 *   "status": "sent",
 *   "tag": ["pedido"],
 *   "error": "mensagem de erro"   // opcional, em event=error
 * }
 *
 * Formato esperado para campanha:
 * {
 *   "campaign_id": "uuid",
 *   "status": "processing|paused|completed|failed",
 *   "total": 1000, "sent": 800, "failed": 50,
 *   "contact_updates": [ ... ]    // opcional
 * }
 */
class NotifyWebhookController extends Controller
{
    /**
     * Repassa o callback de uma notificação individual como NotifyWebhookEvent.
     *
     * Campanha sem ->webhookUrl() usa o notify.webhook, que costuma apontar
     * para esta rota: o callback dela (com campaign_id e sem notification_id)
     * segue para campaign().
     */
    public function notification(Request $request): JsonResponse
    {
        if ($request->filled('campaign_id') && ! $request->filled('notification_id')) {
            return $this->campaign($request);
        }

        Event::dispatch(new NotifyWebhookEvent(
            event: $request->input('event'),
            notificationId: $request->input('notification_id'),
            providerId: $request->input('provider_id'),
            status: $request->input('status'),
            // O servidor manda o canal em `channel`; `type` fica para quem
            // ainda recebe o formato antigo.
            type: $request->input('channel', $request->input('type')),
            payload: $request->all(),
        ));

        return response()->json(['ok' => true]);
    }

    /**
     * Repassa o callback de uma campanha como NotifyCampaignWebhookEvent.
     */
    public function campaign(Request $request): JsonResponse
    {
        Event::dispatch(new NotifyCampaignWebhookEvent(
            campaignId: $request->input('campaign_id'),
            status: $request->input('status'),
            payload: $request->all(),
        ));

        return response()->json(['ok' => true]);
    }

    /**
     * Webhook do chat do WhatsApp (e dos avisos de conexão): aponte o
     * webhook_url das configurações do chat (e dos avisos) para esta rota.
     * Dispara NotifyWhatsAppEvent uma vez por event_id.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');

        if ($event !== '') {
            $eventId = $request->input('event_id');
            NotifyWhatsAppEvent::dispatchOnce($event, $eventId !== null ? (int) $eventId : null, $request->all());
        }

        return response()->json(['ok' => true]);
    }
}
