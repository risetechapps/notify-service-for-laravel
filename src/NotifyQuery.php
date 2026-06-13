<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Consultas de notificações e campanhas no servidor (tempo real via API).
 *
 * O pacote não persiste nada localmente — todas as consultas vão ao servidor.
 *
 *   // Notificações
 *   NotifyQuery::server()->notifications()->channel('sms')->status('send')->get();
 *   NotifyQuery::server()->notifications()->from('2026-01-01')->to('2026-03-31')->get();
 *   NotifyQuery::server()->notification('server-uuid');           // detalhes + eventos
 *   NotifyQuery::server()->notificationEvents('server-uuid');     // só a timeline
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
 *   // Campanhas
 *   NotifyQuery::server()->campaigns()->channel('sms')->status('processing')->get();
 *   NotifyQuery::server()->campaign('server-campaign-uuid');      // detalhes + progresso
 *   NotifyQuery::server()->campaignContacts('server-uuid')->status('failed')->get();
 *   NotifyQuery::server()->campaignContact('campaign-uuid', 'contact-uuid');
 */
class NotifyQuery
{
    /** Ponto de entrada para consultas no servidor. */
    public static function server(): ServerQuery
    {
        return new ServerQuery();
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para consultas na API do servidor.
 * Instanciado via NotifyQuery::server().
 */
class ServerQuery
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = Notify::BASE_URL;
        $this->apiKey = config('notify.key', '');
    }

    // ── Notificações ──────────────────────────────────────────────────────────

    /** Inicia query de notificações individuais no servidor. */
    public function notifications(): ServerNotificationQuery
    {
        return new ServerNotificationQuery($this->apiUrl, $this->apiKey);
    }

    /**
     * Busca detalhes + eventos de uma notificação no servidor.
     *
     * @param  string $serverNotificationId  UUID retornado pelo servidor no dispatch
     * @return array{data: array, events: array}
     */
    public function notification(string $serverNotificationId): array
    {
        $response = $this->http()->get("/api/v1/notifications/{$serverNotificationId}");
        return $response->json('data', []);
    }

    /**
     * Busca apenas a timeline de eventos de uma notificação.
     *
     * @param  string $serverNotificationId
     * @return array{current_status: string, events: array}
     */
    public function notificationEvents(string $serverNotificationId): array
    {
        $response = $this->http()->get("/api/v1/notifications/{$serverNotificationId}/events");
        return $response->json() ?? [];
    }

    // ── SMS ─────────────────────────────────────────────────────────────────

    /**
     * Recurso de SMS no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get();
     *
     *   // Detalhe + timeline de um SMS
     *   NotifyQuery::server()->sms('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->sms('uuid')->cancel();
     *   NotifyQuery::server()->sms()->cancel('uuid');
     *
     * @param string|null $id  UUID do SMS, para detalhe/ações sobre um registro específico.
     */
    public function sms(?string $id = null): ServerSmsQuery
    {
        return new ServerSmsQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── E-mail ────────────────────────────────────────────────────────────────

    /**
     * Recurso de e-mail no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get();
     *
     *   // Detalhe + timeline de um e-mail
     *   NotifyQuery::server()->mail('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->mail('uuid')->cancel();
     *   NotifyQuery::server()->mail()->cancel('uuid');
     *
     * @param string|null $id  UUID do e-mail, para detalhe/ações sobre um registro específico.
     */
    public function mail(?string $id = null): ServerMailQuery
    {
        return new ServerMailQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Push ──────────────────────────────────────────────────────────────────

    /**
     * Recurso de push (FCM) no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->push()->tag('promo')->status('sent')->get();
     *
     *   // Detalhe + timeline de um push
     *   NotifyQuery::server()->push('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->push('uuid')->cancel();
     *   NotifyQuery::server()->push()->cancel('uuid');
     *
     * @param string|null $id  UUID do push, para detalhe/ações sobre um registro específico.
     */
    public function push(?string $id = null): ServerPushQuery
    {
        return new ServerPushQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── APNs ────────────────────────────────────────────────────────────────────

    /**
     * Recurso de push APNs (Apple) no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->apns()->tag('entregas')->status('sent')->get();
     *
     *   // Detalhe + timeline de um push APNs
     *   NotifyQuery::server()->apns('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->apns('uuid')->cancel();
     *   NotifyQuery::server()->apns()->cancel('uuid');
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function apns(?string $id = null): ServerApnsQuery
    {
        return new ServerApnsQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Telegram ────────────────────────────────────────────────────────────────

    /**
     * Recurso de Telegram no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->telegram()->tag('pedidos')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->telegram('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->telegram('uuid')->cancel();
     *   NotifyQuery::server()->telegram()->cancel('uuid');
     *
     *   // Gerenciar a mensagem já enviada (só precisa do notification_id do recurso)
     *   NotifyQuery::server()->telegram('uuid')->edit('novo texto');
     *   NotifyQuery::server()->telegram('uuid')->editCaption('nova legenda');
     *   NotifyQuery::server()->telegram('uuid')->delete();
     *   NotifyQuery::server()->telegram('uuid')->pin(disableNotification: true);
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function telegram(?string $id = null): ServerTelegramQuery
    {
        return new ServerTelegramQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Slack ─────────────────────────────────────────────────────────────────

    /**
     * Recurso de Slack no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->slack()->tag('deploys')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->slack('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->slack('uuid')->cancel();
     *   NotifyQuery::server()->slack()->cancel('uuid');
     *
     *   // Gerenciar a mensagem já enviada (só Bot Token)
     *   NotifyQuery::server()->slack('uuid')->edit('novo texto');
     *   NotifyQuery::server()->slack('uuid')->delete();
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function slack(?string $id = null): ServerSlackQuery
    {
        return new ServerSlackQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Discord ───────────────────────────────────────────────────────────────

    /**
     * Recurso de Discord no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->discord()->tag('cadastros')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->discord('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->discord('uuid')->cancel();
     *
     *   // Gerenciar a mensagem já enviada
     *   NotifyQuery::server()->discord('uuid')->edit('novo texto');
     *   NotifyQuery::server()->discord('uuid')->delete();
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function discord(?string $id = null): ServerDiscordQuery
    {
        return new ServerDiscordQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Teams ─────────────────────────────────────────────────────────────────

    /**
     * Recurso de Microsoft Teams no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->teams()->tag('relatorios')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->teams('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->teams('uuid')->cancel();
     *   NotifyQuery::server()->teams()->cancel('uuid');
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function teams(?string $id = null): ServerTeamsQuery
    {
        return new ServerTeamsQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── WebSocket ─────────────────────────────────────────────────────────────

    /**
     * Recurso de WebSocket no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->websocket()->tag('pedidos')->status('sent')->get();
     *
     *   // Detalhe + timeline de um evento
     *   NotifyQuery::server()->websocket('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->websocket('uuid')->cancel();
     *   NotifyQuery::server()->websocket()->cancel('uuid');
     *
     * @param string|null $id  UUID do evento, para detalhe/ações sobre um registro específico.
     */
    public function websocket(?string $id = null): ServerWebSocketQuery
    {
        return new ServerWebSocketQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Campanhas ─────────────────────────────────────────────────────────────

    /** Inicia query de campanhas no servidor. */
    public function campaigns(): ServerCampaignQuery
    {
        return new ServerCampaignQuery($this->apiUrl, $this->apiKey);
    }

    /**
     * Busca detalhes + progresso de uma campanha no servidor.
     *
     * @param  string $serverCampaignId  UUID retornado pelo servidor ao criar a campanha
     */
    public function campaign(string $serverCampaignId): array
    {
        $response = $this->http()->get("/api/v1/campaigns/{$serverCampaignId}");
        return $response->json('data', []);
    }

    /**
     * Inicia query de contatos de uma campanha no servidor.
     */
    public function campaignContacts(string $serverCampaignId): ServerCampaignContactQuery
    {
        return new ServerCampaignContactQuery($this->apiUrl, $this->apiKey, $serverCampaignId);
    }

    /**
     * Busca detalhes de um contato específico de uma campanha.
     */
    public function campaignContact(string $serverCampaignId, string $contactId): array
    {
        $response = $this->http()->get("/api/v1/campaigns/{$serverCampaignId}/contacts/{$contactId}");
        return $response->json('data', []);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para listar notificações no servidor.
 *
 * NotifyQuery::server()->notifications()
 *     ->channel('sms')
 *     ->status('send')
 *     ->from('2026-01-01')
 *     ->campaignId('uuid')
 *     ->perPage(50)
 *     ->get();
 */
class ServerNotificationQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
    ) {}

    public function channel(string $channel): static
    {
        $this->params['channel'] = $channel;
        return $this;
    }

    /**
     * Status: created | sending | send | delivered | ready | error
     */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function from(string $date): static
    {
        $this->params['from'] = $date;
        return $this;
    }

    public function to(string $date): static
    {
        $this->params['to'] = $date;
        return $this;
    }

    public function campaignId(string $campaignId): static
    {
        $this->params['campaign_id'] = $campaignId;
        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Executa a consulta. Retorna array com 'data' e 'meta'.
     *
     * @return array{data: array, meta: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    public function get(): array
    {
        $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->get("{$this->apiUrl}/api/v1/notifications", $this->params);

        return [
            'data' => $response->json('data', []),
            'meta' => $response->json('meta', []),
        ];
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para listar campanhas no servidor.
 *
 * NotifyQuery::server()->campaigns()
 *     ->channel('email')
 *     ->status('completed')
 *     ->from('2026-01-01')
 *     ->get();
 */
class ServerCampaignQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
    ) {}

    /** channel: sms | email */
    public function channel(string $channel): static
    {
        $this->params['channel'] = $channel;
        return $this;
    }

    /** status: pending | processing | paused | completed | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function from(string $date): static
    {
        $this->params['from'] = $date;
        return $this;
    }

    public function to(string $date): static
    {
        $this->params['to'] = $date;
        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * @return array{data: array, meta: array}
     */
    public function get(): array
    {
        $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->get("{$this->apiUrl}/api/v1/campaigns", $this->params);

        return [
            'data' => $response->json('data', []),
            'meta' => $response->json('meta', []),
        ];
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para listar contatos de uma campanha no servidor.
 *
 * NotifyQuery::server()->campaignContacts('server-campaign-uuid')
 *     ->status('failed')
 *     ->search('joao@email.com')
 *     ->perPage(100)
 *     ->get();
 */
class ServerCampaignContactQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $serverCampaignId,
    ) {}

    /** status: pending | sending | sent | failed | skipped */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Busca parcial por email ou telefone. */
    public function search(string $term): static
    {
        $this->params['search'] = $term;
        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * @return array{campaign: array, data: array, meta: array}
     */
    public function get(): array
    {
        $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->get("{$this->apiUrl}/api/v1/campaigns/{$this->serverCampaignId}/contacts", $this->params);

        return [
            'campaign' => $response->json('campaign', []),
            'data'     => $response->json('data', []),
            'meta'     => $response->json('meta', []),
        ];
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de SMS no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->sms() (lista) ou ->sms($id) (registro).
 *
 *   // Listar
 *   NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->sms('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->sms('uuid')->cancel();
 *   NotifyQuery::server()->sms()->cancel('uuid');
 */
class ServerSmsQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /**
     * Filtra por tag(s) — contém qualquer uma das informadas.
     * Sem chamar tag() o servidor retorna só os SMS SEM tag.
     */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;
        return $this;
    }

    /** status: created | sending | send | delivered | error | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um SMS.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/sms/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/sms', $this->params)->json() ?? [];
    }

    /**
     * Cancela um SMS — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->sms($id)->cancel()) ou um id explícito (->sms()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'SMS id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/sms/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de e-mail no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->mail() (lista) ou ->mail($id) (registro).
 *
 *   // Listar
 *   NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->mail('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->mail('uuid')->cancel();
 *   NotifyQuery::server()->mail()->cancel('uuid');
 */
class ServerMailQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /**
     * Filtra por tag(s) — contém qualquer uma das informadas.
     * Sem chamar tag() o servidor retorna só os e-mails SEM tag.
     */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | delivered | ready | error | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um e-mail.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/email/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/email', $this->params)->json() ?? [];
    }

    /**
     * Cancela um e-mail — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->mail($id)->cancel()) ou um id explícito (->mail()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, notification_id: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Email id is required to cancel.', 'notification_id' => null, 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/email/{$id}/cancel");

        return [
            'status'          => $response->json('status', false),
            'message'         => $response->json('message'),
            'notification_id' => $response->json('notification_id'),
            'current_status'  => $response->json('current_status'),
            'http'            => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de push (FCM) no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->push() (lista) ou ->push($id) (registro).
 *
 *   // Listar
 *   NotifyQuery::server()->push()->tag('promo')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->push('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->push('uuid')->cancel();
 *   NotifyQuery::server()->push()->cancel('uuid');
 */
class ServerPushQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /**
     * Filtra por tag. Sem chamar tag() o servidor retorna só os push SEM tag.
     * (No push a tag é uma string única.)
     */
    public function tag(string $tag): static
    {
        $this->params['tag'] = $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um push.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/push/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/push', $this->params)->json() ?? [];
    }

    /**
     * Cancela um push — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->push($id)->cancel()) ou um id explícito (->push()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Push id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/push/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de push APNs (Apple) no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->apns() (lista) ou ->apns($id) (registro).
 *
 *   NotifyQuery::server()->apns()->tag('entregas')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->apns('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->apns('uuid')->cancel();
 *   NotifyQuery::server()->apns()->cancel('uuid');
 */
class ServerApnsQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/apns/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/apns', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio APNs — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->apns($id)->cancel()) ou um id explícito (->apns()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'APNs id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/apns/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Telegram no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->telegram() (lista) ou ->telegram($id) (registro).
 *
 *   NotifyQuery::server()->telegram()->tag('pedidos')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->telegram('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->telegram('uuid')->cancel();
 *   NotifyQuery::server()->telegram()->cancel('uuid');
 *
 *   // Gerenciar a mensagem já enviada (síncrono, sobre o registro)
 *   NotifyQuery::server()->telegram('uuid')->edit('novo texto');
 *   NotifyQuery::server()->telegram('uuid')->editCaption('nova legenda');
 *   NotifyQuery::server()->telegram('uuid')->delete();
 *   NotifyQuery::server()->telegram('uuid')->pin(disableNotification: true);
 */
class ServerTelegramQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/telegram/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/telegram', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Telegram — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->telegram($id)->cancel()) ou um id explícito (->telegram()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Telegram id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/telegram/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    // ── Gerência de mensagem (síncrono, sobre o registro) ───────────────────────

    /**
     * Edita o texto de uma mensagem já enviada. Requer o id do recurso:
     * NotifyQuery::server()->telegram($id)->edit('novo texto').
     *
     * @param string      $message    Novo texto
     * @param string|null $parseMode  Markdown | MarkdownV2 | HTML (opcional)
     */
    public function edit(string $message, ?string $parseMode = null): array
    {
        return $this->messageAction('edit', array_filter([
            'message'    => $message,
            'parse_mode' => $parseMode,
        ], fn ($v) => !is_null($v)));
    }

    /**
     * Edita a legenda de uma mensagem de mídia (foto/documento/vídeo).
     *
     * @param string      $caption    Nova legenda
     * @param string|null $parseMode  Markdown | MarkdownV2 | HTML (opcional)
     */
    public function editCaption(string $caption, ?string $parseMode = null): array
    {
        return $this->messageAction('edit-caption', array_filter([
            'message'    => $caption,
            'parse_mode' => $parseMode,
        ], fn ($v) => !is_null($v)));
    }

    /** Apaga uma mensagem já enviada. */
    public function delete(): array
    {
        return $this->messageAction('delete', []);
    }

    /**
     * Fixa uma mensagem já enviada no chat.
     *
     * @param bool $disableNotification  true = fixa sem notificar os membros
     */
    public function pin(bool $disableNotification = false): array
    {
        return $this->messageAction('pin', ['disable_notification' => $disableNotification]);
    }

    /**
     * Interno: chama /api/v1/telegram/messages/{action} com o notification_id do
     * recurso (o servidor resolve chat_id + message_id salvos).
     *
     * @return array{status: bool, message: string|null, http: int}
     */
    protected function messageAction(string $action, array $payload): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Telegram notification id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/telegram/messages/{$action}", array_merge(
            ['notification_id' => $this->id],
            $payload,
        ));

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Slack no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->slack() (lista) ou ->slack($id) (registro).
 *
 *   NotifyQuery::server()->slack()->tag('deploys')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->slack('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->slack('uuid')->cancel();
 *   NotifyQuery::server()->slack()->cancel('uuid');
 *
 *   // Gerenciar a mensagem já enviada (síncrono, só Bot Token)
 *   NotifyQuery::server()->slack('uuid')->edit('novo texto');
 *   NotifyQuery::server()->slack('uuid')->delete();
 */
class ServerSlackQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/slack/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/slack', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Slack — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->slack($id)->cancel()) ou um id explícito (->slack()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Slack id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/slack/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    // ── Gerência de mensagem (síncrono, só Bot Token) ───────────────────────────

    /**
     * Edita (chat.update) uma mensagem já enviada. Só funciona no modo Bot Token.
     * Requer o id do recurso: NotifyQuery::server()->slack($id)->edit('novo texto').
     */
    public function edit(string $message): array
    {
        return $this->messageAction('edit', ['message' => $message]);
    }

    /**
     * Apaga (chat.delete) uma mensagem já enviada. Só funciona no modo Bot Token.
     * Requer o id do recurso: NotifyQuery::server()->slack($id)->delete().
     */
    public function delete(): array
    {
        return $this->messageAction('delete', []);
    }

    /**
     * Interno: chama /api/v1/slack/messages/{action} com o notification_id do recurso.
     *
     * @return array{status: bool, message: string|null, http: int}
     */
    protected function messageAction(string $action, array $payload): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Slack notification id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/slack/messages/{$action}", array_merge(
            ['notification_id' => $this->id],
            $payload,
        ));

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Discord no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->discord() (lista) ou ->discord($id) (registro).
 *
 *   NotifyQuery::server()->discord()->tag('cadastros')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->discord('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->discord('uuid')->cancel();
 *   NotifyQuery::server()->discord()->cancel('uuid');
 *
 *   // Gerenciar a mensagem já enviada (síncrono, sobre o registro)
 *   NotifyQuery::server()->discord('uuid')->edit('novo texto');
 *   NotifyQuery::server()->discord('uuid')->delete();
 */
class ServerDiscordQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/discord/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/discord', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Discord — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->discord($id)->cancel()) ou um id explícito (->discord()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Discord id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/discord/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    // ── Gerência de mensagem (síncrono, sobre o registro) ───────────────────────

    /**
     * Edita (PATCH) uma mensagem já enviada.
     * Requer o id do recurso: NotifyQuery::server()->discord($id)->edit('novo texto').
     */
    public function edit(string $message): array
    {
        return $this->messageAction('edit', ['message' => $message]);
    }

    /**
     * Apaga (DELETE) uma mensagem já enviada.
     * Requer o id do recurso: NotifyQuery::server()->discord($id)->delete().
     */
    public function delete(): array
    {
        return $this->messageAction('delete', []);
    }

    /**
     * Interno: chama /api/v1/discord/messages/{action} com o notification_id do recurso.
     *
     * @return array{status: bool, message: string|null, http: int}
     */
    protected function messageAction(string $action, array $payload): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Discord notification id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/discord/messages/{$action}", array_merge(
            ['notification_id' => $this->id],
            $payload,
        ));

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Microsoft Teams no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->teams() (lista) ou ->teams($id) (registro).
 *
 *   NotifyQuery::server()->teams()->tag('relatorios')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->teams('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->teams('uuid')->cancel();
 *   NotifyQuery::server()->teams()->cancel('uuid');
 */
class ServerTeamsQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/teams/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/teams', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Teams — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->teams($id)->cancel()) ou um id explícito (->teams()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Teams id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/teams/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de WebSocket no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->websocket() (lista) ou ->websocket($id) (registro).
 *
 *   NotifyQuery::server()->websocket()->tag('pedidos')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->websocket('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->websocket('uuid')->cancel();
 *   NotifyQuery::server()->websocket()->cancel('uuid');
 */
class ServerWebSocketQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os eventos SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/websocket/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/websocket', $this->params)->json() ?? [];
    }

    /**
     * Cancela um evento WebSocket — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->websocket($id)->cancel()) ou um id explícito (->websocket()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id = $id ?? $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'WebSocket id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/websocket/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
