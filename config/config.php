<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'key' => env('NOTIFY_SERVICE_KEY', ''),

    // URL de callback informada ao servidor nos envios (para onde ELE manda o status).
    'webhook' => env('NOTIFY_SERVICE_WEBHOOK', ''),

    /*
    |--------------------------------------------------------------------------
    | Recebimento dos callbacks do servidor
    |--------------------------------------------------------------------------
    |
    | O pacote registra POST {prefix}/webhook e POST {prefix}/webhook/campaign,
    | que apenas disparam NotifyWebhookEvent / NotifyCampaignWebhookEvent.
    |
    */

    // Registra as rotas de webhook automaticamente. Desligue se preferir declarar
    // as rotas manualmente no seu routes/api.php apontando para NotifyWebhookController.
    'routes' => env('NOTIFY_SERVICE_ROUTES', true),

    // Prefixo das rotas: resulta em /notify/webhook e /notify/webhook/campaign.
    'routes_prefix' => env('NOTIFY_SERVICE_ROUTES_PREFIX', 'notify'),

    // Middlewares do grupo. A validação de assinatura é sempre aplicada por cima
    // deste array (controlada por 'webhook_verify'), não precisa ser listada aqui.
    'routes_middleware' => ['api'],

    // Segredo(s) para validar a assinatura dos callbacks (X-Webhook-Signature).
    // Separe por vírgula: o da conta (painel → Configurações → Assinatura dos
    // webhooks, usado nos envios com webhook_url) e o de cada endpoint em
    // painel → Webhooks. Sem nenhum, os callbacks são recusados com 403.
    'webhook_secret' => env('NOTIFY_SERVICE_WEBHOOK_SECRET', ''),

    // Valida a assinatura HMAC dos callbacks recebidos. Só desligue em ambiente
    // local — com false qualquer um pode forjar um callback e disparar seus listeners.
    'webhook_verify' => env('NOTIFY_SERVICE_WEBHOOK_VERIFY', true),

    // Janela anti-replay em segundos: recusa callbacks cujo timestamp assinado
    // esteja fora deste intervalo (X-Webhook-Timestamp). 0 desliga a checagem de tempo.
    'webhook_tolerance' => env('NOTIFY_SERVICE_WEBHOOK_TOLERANCE', 300),

    // Notificação com fila (ShouldQueue): falha passageira no envio (servidor
    // fora, timeout, 5xx, 429, 409) lança NotifyDeliveryException para o
    // Laravel tentar de novo (use $tries/$backoff na notificação). Cada envio
    // leva uma Idempotency-Key automática, então a nova tentativa não duplica
    // a mensagem. Envio síncrono não lança: devolve o erro, como antes.
    // false: devolve o erro também na fila.
    'retry_failures' => env('NOTIFY_SERVICE_RETRY_FAILURES', true),

    // Chamadas ao notify-server. timeout: segundos até desistir da resposta
    // (com o servidor lento, a tela do atendente não fica presa). Arquivos
    // (respostas com anexo, mídia de fluxo e de campanha) usam upload_timeout.
    // retries: novas tentativas só em leituras (GET) e só em falha de conexão
    // ou 5xx, com retry_sleep ms entre elas. Envios nunca repetem sozinhos.
    'http' => [
        'timeout' => env('NOTIFY_HTTP_TIMEOUT', 20),
        'connect_timeout' => env('NOTIFY_HTTP_CONNECT_TIMEOUT', 5),
        'upload_timeout' => env('NOTIFY_HTTP_UPLOAD_TIMEOUT', 120),
        'retries' => env('NOTIFY_HTTP_RETRIES', 2),
        'retry_sleep' => env('NOTIFY_HTTP_RETRY_SLEEP', 200),
    ],

    // Cache curto do que quase não muda e é pedido em toda tela do chat:
    // team()->members(), chat()->numbers() e chat()->botFlows()->all(). Quem
    // altera pelo pacote (syncAgent, create/update do fluxo, updateChatSettings…)
    // já limpa o cache; o que mudar pelo painel aparece depois de ttl segundos.
    // ttl 0 desliga. store null = o cache padrão da aplicação.
    'cache' => [
        'ttl' => env('NOTIFY_CACHE_TTL', 30),
        'store' => env('NOTIFY_CACHE_STORE'),
    ],

    // Tempo real no front-end sem polling: cada NotifyWhatsAppEvent (webhook do
    // chat ou notify:whatsapp-events) é retransmitido pelo Broadcasting do
    // Laravel (Reverb, Pusher, Ably…) em canais privados:
    //   private-{channel}                      todos os eventos da conta
    //   private-{channel}.{configuration_id}   só os de um número
    // Autorize os canais em routes/channels.php. Veja o README (Tempo real).
    'broadcast' => [
        'enabled' => env('NOTIFY_BROADCAST', false),
        'channel' => env('NOTIFY_BROADCAST_CHANNEL', 'notify.whatsapp'),
        // Só estes tipos (ex.: ['message.received', 'conversation.updated']); vazio = todos.
        'events' => [],
        // Conexão do broadcasting (null = a padrão) e fila (null = a padrão;
        // 'sync' transmite na hora, dentro do webhook).
        'connection' => env('NOTIFY_BROADCAST_CONNECTION'),
        'queue' => env('NOTIFY_BROADCAST_QUEUE'),
    ],

    'sms' => [
        // config_id default (UUID ou label, ex.: "Twilio Principal") usado quando a
        // notificação não passa ->configId(). null = usa a config default do servidor.
        'config_id' => env('NOTIFY_SMS_CONFIG_ID'),

        // Tags aplicadas a todo SMS, mescladas com as tags definidas na notificação.
        'tags' => [],
    ],

    'mail' => [
        // Remetente default. A notificação pode sobrescrever com ->from($email, $name).
        'from' => [
            'address' => env('NOTIFY_MAIL_FROM_ADDRESS'),
            'name' => env('NOTIFY_MAIL_FROM_NAME'),
        ],

        // Nome do app/marca exibido no template. Cai para config('app.name') se null.
        'app_name' => env('NOTIFY_MAIL_APP_NAME'),

        // Tema default do template do servidor.
        'theme' => env('NOTIFY_MAIL_THEME', 'default'),

        // config_id default (UUID ou label da credencial SMTP, multi-tenant).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_MAIL_CONFIG_ID'),

        // Tags aplicadas a todo e-mail, mescladas com as tags definidas na notificação.
        'tags' => [],
    ],

    'push' => [
        // config_id default (UUID ou label da credencial FCM).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_PUSH_CONFIG_ID'),

        // Tag default. No push a tag é uma STRING única (FCM grouping tag + etiqueta de
        // histórico) — não aceita array. A ->tag() da notificação sobrescreve esta.
        'tag' => env('NOTIFY_PUSH_TAG'),
    ],

    'apns' => [
        // config_id default (UUID ou label da credencial APNS).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_APNS_CONFIG_ID'),

        // Tags aplicadas a todo push APNS, mescladas com as tags definidas na notificação.
        'tags' => [],
    ],

    'telegram' => [
        // config_id default (UUID ou label do bot Telegram).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_TELEGRAM_CONFIG_ID'),

        // Tags aplicadas a todo envio Telegram, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'slack' => [
        // config_id default (UUID ou label da credencial Slack — Bot Token ou Incoming Webhook).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_SLACK_CONFIG_ID'),

        // Tags aplicadas a todo envio Slack, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'discord' => [
        // config_id default (UUID ou label da credencial Discord).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_DISCORD_CONFIG_ID'),

        // Tags aplicadas a todo envio Discord, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'teams' => [
        // config_id default (UUID ou label da credencial Teams).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_TEAMS_CONFIG_ID'),

        // Tags aplicadas a todo envio Teams, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'whatsapp' => [
        // config_id default (UUID ou label do número conectado). Sobrescrito pelo
        // ->configId() da mensagem. null = o número padrão da conta no servidor.
        'config_id' => env('NOTIFY_WHATSAPP_CONFIG_ID'),

        // Tags aplicadas a todo envio de WhatsApp, mescladas com as da mensagem.
        'tags' => [],
    ],

    'websocket' => [
        // config_id default (UUID ou label da credencial WebSocket — ex.: Pusher).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_WEBSOCKET_CONFIG_ID'),

        // Tags aplicadas a todo evento WebSocket, mescladas com as tags da notificação.
        'tags' => [],
    ],
];
