<?php

namespace RiseTechApps\Notify;

/**
 * Consultas de notificações e campanhas no servidor (tempo real via API).
 *
 * O pacote não persiste nada localmente — todas as consultas vão ao servidor.
 *
 *   // SMS
 *   NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get();  // listar
 *   NotifyQuery::server()->sms('server-uuid')->get();                        // detalhe + timeline
 *   NotifyQuery::server()->sms('server-uuid')->cancel();                     // cancelar (se created)
 *
 *   // E-mail
 *   NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get(); // listar
 *   NotifyQuery::server()->mail('server-uuid')->get();                        // detalhe + timeline
 *   NotifyQuery::server()->mail('server-uuid')->cancel();                     // cancelar (se created)
 *
 *   // Campanhas (endpoints por canal: sms | mail)
 *   NotifyQuery::server()->campaigns('sms')->status('processing')->get();   // listar
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->get();        // detalhe + progresso
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->cancel();      // cancelar
 *   NotifyQuery::server()->campaigns('mail', 'uuid')->contacts()->status('failed')->get(); // contatos
 *
 *   // Multi-canal
 *   NotifyQuery::multi([...])->send();
 */
class NotifyQuery
{
    /** Ponto de entrada para consultas no servidor. */
    public static function server(): ServerQuery
    {
        return new ServerQuery;
    }

    /**
     * Envia para múltiplos canais em uma única requisição.
     *
     * @param  array  $channels  Lista de canais, cada um com 'channel' e 'data'
     * @param  string|null  $webhookUrl  URL de callback global (opcional)
     *
     * @example
     * NotifyQuery::multi([
     *     ['channel' => 'sms',   'data' => ['to' => '+5511999999999', 'content' => 'Olá!']],
     *     ['channel' => 'email', 'data' => ['to' => 'user@ex.com',   'subject' => 'Teste']],
     * ])->send();
     */
    public static function multi(array $channels, ?string $webhookUrl = null): NotifyMultiBuilder
    {
        return new NotifyMultiBuilder($channels, $webhookUrl);
    }
}
