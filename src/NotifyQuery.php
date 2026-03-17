<?php

namespace RiseTechApps\Notify;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Models\NotifyCampaign;
use RiseTechApps\Notify\Models\NotifyCampaignContact;
use RiseTechApps\Notify\Models\NotifyLog;

/**
 * Consultas de notificações e campanhas.
 *
 * Dois modos de consulta:
 *   - LOCAL  → banco de dados da sua aplicação (notify_logs, notify_campaigns, etc.)
 *   - SERVER → API do servidor notifykit.app.br (status em tempo real, eventos, contatos)
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * CONSULTAS LOCAIS (banco da sua aplicação)
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   // Logs de notificações individuais
 *   NotifyQuery::logs()->where('channel', 'sms')->latest()->get();
 *   NotifyQuery::logs()->where('status', 'error')->paginate(25);
 *   NotifyQuery::logsFor($user)->get();
 *   NotifyQuery::findLog('server-notification-uuid');
 *
 *   // Campanhas
 *   NotifyQuery::campaigns()->where('channel', 'sms')->latest()->get();
 *   NotifyQuery::campaigns()->where('status', 'processing')->get();
 *   NotifyQuery::findCampaign('server-campaign-uuid');
 *
 *   // Contatos de uma campanha
 *   NotifyQuery::campaignContacts('campaign-local-uuid')->where('status', 'failed')->get();
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * CONSULTAS NO SERVIDOR (tempo real via API)
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   // Notificações no servidor
 *   NotifyQuery::server()->notifications()->channel('sms')->status('send')->get();
 *   NotifyQuery::server()->notifications()->from('2026-01-01')->to('2026-03-31')->get();
 *   NotifyQuery::server()->notification('server-uuid');           // detalhes + eventos
 *   NotifyQuery::server()->notificationEvents('server-uuid');     // só a timeline
 *
 *   // Campanhas no servidor
 *   NotifyQuery::server()->campaigns()->channel('sms')->status('processing')->get();
 *   NotifyQuery::server()->campaign('server-campaign-uuid');      // detalhes + progresso
 *   NotifyQuery::server()->campaignContacts('server-uuid')->status('failed')->get();
 *   NotifyQuery::server()->campaignContact('campaign-uuid', 'contact-uuid');
 */
class NotifyQuery
{
    // ══════════════════════════════════════════════════════════════════════════
    // CONSULTAS LOCAIS
    // ══════════════════════════════════════════════════════════════════════════

    /** Inicia query na tabela notify_logs. */
    public static function logs(): Builder
    {
        return NotifyLog::query();
    }

    /** Busca um log pelo server_notification_id. */
    public static function findLog(string $serverNotificationId): ?NotifyLog
    {
        return NotifyLog::where('server_notification_id', $serverNotificationId)->first();
    }

    /** Todos os logs de um model específico (User, Authentication, etc.). */
    public static function logsFor(object $notifiable): Builder
    {
        return NotifyLog::query()
            ->where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->getKey());
    }

    /** Inicia query na tabela notify_campaigns. */
    public static function campaigns(): Builder
    {
        return NotifyCampaign::query();
    }

    /** Busca uma campanha pelo server_campaign_id. */
    public static function findCampaign(string $serverCampaignId): ?NotifyCampaign
    {
        return NotifyCampaign::where('server_campaign_id', $serverCampaignId)->first();
    }

    /** Contatos de uma campanha (pelo ID local). */
    public static function campaignContacts(string $localCampaignId): Builder
    {
        return NotifyCampaignContact::where('notify_campaign_id', $localCampaignId);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONSULTAS NO SERVIDOR
    // ══════════════════════════════════════════════════════════════════════════

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
        $this->apiUrl = 'https://notifykit.app.br';
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
