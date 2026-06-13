<?php

namespace RiseTechApps\Notify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;
use RiseTechApps\Notify\Events\NotifyWebhookEvent;

/**
 * Recebe callbacks de status do servidor de notificações e os repassa como
 * eventos Laravel. O pacote não persiste nada localmente — escute os eventos
 * (NotifyWebhookEvent / NotifyCampaignWebhookEvent) para reagir.
 *
 * Registre as rotas no seu routes/api.php (ou habilite notify.routes no config):
 *
 *   Route::post('/notify/webhook',          [NotifyWebhookController::class, 'notification']);
 *   Route::post('/notify/webhook/campaign', [NotifyWebhookController::class, 'campaign']);
 *
 * Formato esperado para notificação individual:
 * {
 *   "event": "sending|sent|delivered|error|token_invalid",
 *   "notification_id": "uuid",
 *   "provider_id": "11",          // id da mensagem no provedor (ex.: message_id do Telegram)
 *   "status": "send",
 *   "type": "telegram",
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
     */
    public function notification(Request $request): JsonResponse
    {
        Event::dispatch(new NotifyWebhookEvent(
            event:          $request->input('event'),
            notificationId: $request->input('notification_id'),
            providerId:     $request->input('provider_id'),
            status:         $request->input('status'),
            type:           $request->input('type'),
            payload:        $request->all(),
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
            status:     $request->input('status'),
            payload:    $request->all(),
        ));

        return response()->json(['ok' => true]);
    }
}
