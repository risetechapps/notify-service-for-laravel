<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

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

    // ── SMS ─────────────────────────────────────────────────────────────────

    public function sms(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'sms', $id);
    }

    // ── E-mail ────────────────────────────────────────────────────────────────

    public function mail(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'email', $id);
    }

    // ── Push ──────────────────────────────────────────────────────────────────

    public function push(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'push', $id);
    }

    // ── APNs ────────────────────────────────────────────────────────────────────

    public function apns(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'apns', $id);
    }

    // ── Telegram ────────────────────────────────────────────────────────────────

    public function telegram(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'telegram', $id);
    }

    // ── Slack ─────────────────────────────────────────────────────────────────

    public function slack(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'slack', $id);
    }

    // ── Discord ───────────────────────────────────────────────────────────────

    public function discord(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'discord', $id);
    }

    // ── Teams ─────────────────────────────────────────────────────────────────

    public function teams(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'teams', $id);
    }

    // ── WebSocket ─────────────────────────────────────────────────────────────

    public function websocket(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'websocket', $id);
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    public function webhook(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'webhook', $id);
    }

    // ── WhatsApp ────────────────────────────────────────────────────────────

    /** Histórico e cancelamento dos envios de WhatsApp (e ->config() para a configuração). */
    public function whatsapp(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'whatsapp', $id);
    }

    /**
     * Um número de WhatsApp: criar, conectar pelo QR, avisos de conexão,
     * configurações do chat, importação do histórico e proteção do número.
     * Sem id: create() e all().
     */
    public function whatsappNumber(?string $configId = null): Server\WhatsAppNumber
    {
        return new Server\WhatsAppNumber($configId);
    }

    /** Chat do WhatsApp: conversas, eventos (cursor) e autoatendimento. */
    public function chat(): Server\Chat
    {
        return new Server\Chat;
    }

    /** Equipe: membros e atendentes do seu sistema (external_id, sem login). */
    public function team(): Server\Team
    {
        return new Server\Team;
    }

    // ── Recursos da conta ───────────────────────────────────────────────────

    /** Modelos de mensagem salvos (e-mail, SMS, WhatsApp). */
    public function templates(): Server\Templates
    {
        return new Server\Templates;
    }

    /** Listas de contatos salvas, para as campanhas. */
    public function contactLists(): Server\ContactLists
    {
        return new Server\ContactLists;
    }

    /** Números fora das campanhas (SAIR ou bloqueio manual), por canal. */
    public function optOuts(): Server\OptOuts
    {
        return new Server\OptOuts;
    }

    /** Histórico das entregas de webhook, com reenvio. */
    public function webhookDeliveries(): Server\WebhookDeliveries
    {
        return new Server\WebhookDeliveries;
    }

    /** Envios por canal e por dia nos últimos $days dias (1 a 90). */
    public function usage(int $days = 30): array
    {
        return $this->http()->get('/api/v1/usage/channels', ['days' => $days])->json() ?? [];
    }

    /**
     * Até 1.000 mensagens de um canal numa chamada (POST /send/batch). Cada
     * item é o corpo do envio individual do canal; $options leva o que vale
     * para o lote (webhook_url, tag, config_id, failover, scheduled_at).
     * O lote entra inteiro ou não entra. Devolve os notification_ids na ordem.
     */
    public function batch(string $channel, array $messages, array $options = [], ?string $idempotencyKey = null): array
    {
        $request = $this->http();

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post('/api/v1/send/batch', ['channel' => $channel, 'messages' => array_values($messages)] + $options);

        return ($response->json() ?? []) + ($response->failed() ? ['http' => $response->status()] : []);
    }

    // ── Configuração de drivers ─────────────────────────────────────────────────

    /**
     * Descobre os drivers cadastráveis por canal (default_driver + credential_fields).
     * GET /api/v1/configurations/drivers
     */
    public function drivers(): array
    {
        return $this->http()->get('/api/v1/configurations/drivers')->json('data', []);
    }

    /**
     * Lista as configurações de driver cadastradas (filtro opcional por canal).
     * GET /api/v1/configurations
     *
     * @param  string|null  $channel  sms | email | push | apns | telegram | ...
     */
    public function configurations(?string $channel = null): array
    {
        $params = $channel ? ['channel' => $channel] : [];

        return $this->http()->get('/api/v1/configurations', $params)->json('data', []);
    }

    // Cada recurso de canal (sms, mail, push, apns, telegram, slack, discord,
    // teams, websocket) expõe ->config() para CRUD da credencial; o driver é
    // informado por ->driver('twilio') no builder.

    // ── Campanhas ─────────────────────────────────────────────────────────────

    /**
     * Recurso de campanhas no servidor (endpoints por canal).
     * Sem id → listagem; com id → detalhe (->get()) e cancelamento (->cancel()).
     *
     * @param  string  $channel  sms | mail | whatsapp (email é normalizado para mail)
     * @param  string|null  $id  UUID da campanha retornado ao criar
     */
    public function campaigns(string $channel, ?string $id = null): ServerCampaignQuery
    {
        return new ServerCampaignQuery($this->apiUrl, $this->apiKey, $channel, $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
