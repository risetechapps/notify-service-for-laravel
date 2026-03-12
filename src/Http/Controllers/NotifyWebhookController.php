<?php

namespace RiseTechApps\Notify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RiseTechApps\Notify\Models\NotifyCampaign;
use RiseTechApps\Notify\Models\NotifyCampaignContact;
use RiseTechApps\Notify\Models\NotifyLog;

/**
 * Recebe callbacks de status do servidor de notificações.
 *
 * Registre as rotas no seu routes/api.php:
 *
 *   Route::post('/notify/webhook',          [\RiseTechApps\Notify\Http\Controllers\NotifyWebhookController::class, 'notification']);
 *   Route::post('/notify/webhook/campaign', [\RiseTechApps\Notify\Http\Controllers\NotifyWebhookController::class, 'campaign']);
 *
 * Ou use o helper do ServiceProvider (publish config + habilite notify.routes no config/notify.php).
 *
 * Formato esperado para notificação individual:
 * {
 *   "notification_id": "uuid",
 *   "status": "delivered|error",
 *   "delivered_at": "2026-01-01 12:00:00",   // opcional
 *   "error": "mensagem de erro"               // opcional
 * }
 *
 * Formato esperado para campanha:
 * {
 *   "campaign_id": "uuid",
 *   "status": "processing|paused|completed|failed",
 *   "total": 1000,
 *   "sent": 800,
 *   "failed": 50,
 *   "started_at": "...",
 *   "finished_at": "..."                      // opcional
 *   "contact_updates": [                      // opcional — status individual por contato
 *     {"contact": "email@ex.com", "status": "sent|failed", "error": "...", "sent_at": "..."}
 *   ]
 * }
 */
class NotifyWebhookController extends Controller
{
    /**
     * Atualiza o status de uma notificação individual.
     */
    public function notification(Request $request): JsonResponse
    {
        $notificationId = $request->input('notification_id');

        if (!$notificationId) {
            return response()->json(['error' => 'notification_id required'], 422);
        }

        $log = NotifyLog::where('server_notification_id', $notificationId)->first();

        if (!$log) {
            // Pode chegar antes do envio ser salvo — retorna 200 mesmo assim
            return response()->json(['ok' => true, 'found' => false]);
        }

        $status = $request->input('status');

        match ($status) {
            'delivered' => $log->markAsDelivered(),
            'error'     => $log->markAsFailed(
                $request->input('error', 'Unknown error'),
                $request->all()
            ),
            default     => $log->update(['status' => $status]),
        };

        return response()->json(['ok' => true]);
    }

    /**
     * Atualiza contadores e status de uma campanha.
     */
    public function campaign(Request $request): JsonResponse
    {
        $campaignId = $request->input('campaign_id');

        if (!$campaignId) {
            return response()->json(['error' => 'campaign_id required'], 422);
        }

        $campaign = NotifyCampaign::where('server_campaign_id', $campaignId)->first();

        if (!$campaign) {
            return response()->json(['ok' => true, 'found' => false]);
        }

        // Atualiza contadores e status
        $campaign->syncFromWebhook($request->all());

        // Atualiza contatos individuais, se o servidor enviar
        if ($contactUpdates = $request->input('contact_updates')) {
            foreach ($contactUpdates as $update) {
                $contact = NotifyCampaignContact::where('notify_campaign_id', $campaign->id)
                    ->where('contact', $update['contact'])
                    ->first();

                if ($contact) {
                    $contact->update([
                        'status'  => $update['status'] ?? $contact->status,
                        'error'   => $update['error']   ?? null,
                        'sent_at' => isset($update['sent_at']) ? $update['sent_at'] : $contact->sent_at,
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
