<?php

use Illuminate\Support\Facades\Route;
use RiseTechApps\Notify\Http\Controllers\NotifyWebhookController;

/*
 * Rotas de webhook do Notify Service.
 *
 * Registradas automaticamente quando 'routes' => true no config/notify.php.
 * O prefixo e o middleware podem ser customizados no config.
 *
 * Para registrar manualmente (desabilite no config e cole no seu routes/api.php):
 *
 *   Route::post('/notify/webhook',          [NotifyWebhookController::class, 'notification']);
 *   Route::post('/notify/webhook/campaign', [NotifyWebhookController::class, 'campaign']);
 */

Route::post('webhook',          [NotifyWebhookController::class, 'notification'])->name('notify.webhook.notification');
Route::post('webhook/campaign', [NotifyWebhookController::class, 'campaign'])->name('notify.webhook.campaign');
