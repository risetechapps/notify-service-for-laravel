<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gerencia as configurações de driver no servidor via API.
 *
 * Cada canal (sms, email, push, etc.) pode ter múltiplas configurações
 * de credenciais salvas no servidor. Uma pode ser marcada como padrão (is_default).
 * Quando um envio não especifica config_id, o servidor usa a config padrão do canal.
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * LISTAR
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   // Todas as configs
 *   NotifyConfig::all();
 *
 *   // Por canal
 *   NotifyConfig::channel('sms');
 *   NotifyConfig::channel('email');
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * CRIAR
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyConfig::create()
 *       ->channel('sms')
 *       ->driver('twilio')
 *       ->label('Twilio Principal')
 *       ->credentials([
 *           'account_sid' => 'ACxxxxxxxx',
 *           'auth_token'  => 'xxxxxxxx',
 *           'from'        => '+15551234567',
 *       ])
 *       ->asDefault()
 *       ->save();
 *
 *   NotifyConfig::create()
 *       ->channel('email')
 *       ->driver('resend')
 *       ->label('Resend Transacional')
 *       ->credentials(['api_key' => 're_xxxxxxxx'])
 *       ->save();
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * ATUALIZAR
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyConfig::update($id)
 *       ->label('Novo nome')
 *       ->credentials(['auth_token' => 'novo-token'])  // merge parcial
 *       ->save();
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * OUTRAS OPERAÇÕES
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyConfig::find($id);           // detalhes (retorna chaves, nunca valores)
 *   NotifyConfig::setDefault($id);     // define como padrão do canal
 *   NotifyConfig::delete($id);         // remove
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * DRIVERS DISPONÍVEIS POR CANAL
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   sms:       twilio | zenvia | mobizon
 *   email:     smtp | mailgun | resend | sendgrid | ses | postmark
 *   push:      fcm
 *   apns:      apns
 *   telegram:  telegram
 *   slack:     slack
 *   discord:   discord
 *   teams:     teams
 *   websocket: pusher
 *   webhook:   webhook
 */
class NotifyConfig
{
    // ── Operações estáticas ───────────────────────────────────────────────────

    /**
     * Lista todas as configurações do servidor.
     * Opcionalmente filtra por canal.
     *
     * @return array  Lista de configs (id, channel, driver, label, is_default, active)
     */
    public static function all(?string $channel = null): array
    {
        $params = $channel ? ['channel' => $channel] : [];

        $response = static::http()->get('/api/v1/configurations', $params);

        return $response->json('data', []);
    }

    /**
     * Lista configs de um canal específico.
     * Atalho para NotifyConfig::all('sms').
     */
    public static function channel(string $channel): array
    {
        return static::all($channel);
    }

    /**
     * Busca detalhes de uma configuração pelo ID.
     * Retorna as chaves das credenciais (nunca os valores).
     */
    public static function find(int|string $id): array
    {
        $response = static::http()->get("/api/v1/configurations/{$id}");

        return $response->json('data', []);
    }

    /**
     * Define uma config como padrão do canal.
     * Remove is_default de todas as outras configs do mesmo canal.
     */
    public static function setDefault(int|string $id): bool
    {
        $response = static::http()->patch("/api/v1/configurations/{$id}/set-default");

        return $response->json('status', false);
    }

    /**
     * Remove uma configuração do servidor.
     */
    public static function delete(int|string $id): bool
    {
        $response = static::http()->delete("/api/v1/configurations/{$id}");

        return $response->json('status', false);
    }

    /**
     * Inicia um fluent builder para criar uma nova configuração.
     */
    public static function create(): NotifyConfigBuilder
    {
        return new NotifyConfigBuilder(mode: 'create');
    }

    /**
     * Inicia um fluent builder para atualizar uma configuração existente.
     */
    public static function update(int|string $id): NotifyConfigBuilder
    {
        return new NotifyConfigBuilder(mode: 'update', id: $id);
    }

    // ── HTTP helper ───────────────────────────────────────────────────────────

    protected static function http()
    {
        return Http::withHeaders(['X-API-KEY' => config('notify.key', '')])
            ->acceptJson()
            ->baseUrl('https://notifykit.app.br');
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para criar ou atualizar uma configuração de driver.
 * Instanciado via NotifyConfig::create() ou NotifyConfig::update($id).
 */
class NotifyConfigBuilder
{
    protected ?string $channel     = null;
    protected ?string $driver      = null;
    protected ?string $label       = null;
    protected array   $credentials = [];
    protected ?bool   $isDefault   = null;
    protected ?bool   $active      = null;

    public function __construct(
        protected string          $mode,
        protected int|string|null $id = null,
    ) {}

    /**
     * Canal da configuração.
     * Valores: sms | email | push | apns | telegram | slack | discord | teams | websocket | webhook
     */
    public function channel(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    /**
     * Driver do canal.
     *
     * sms:       twilio | zenvia | mobizon
     * email:     smtp | mailgun | resend | sendgrid | ses | postmark
     * push:      fcm
     * apns:      apns
     * telegram:  telegram
     * slack:     slack
     * discord:   discord
     * teams:     teams
     * websocket: pusher
     * webhook:   webhook
     */
    public function driver(string $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Nome amigável para identificar a configuração.
     * Ex: "Twilio Principal", "Resend Transacional", "FCM App Android"
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Credenciais específicas do driver.
     *
     * No update(), as credenciais são mescladas (merge parcial) —
     * você só precisa enviar os campos que deseja atualizar.
     *
     * Exemplos por driver:
     *
     *   Twilio:   ['account_sid' => '', 'auth_token' => '', 'from' => '']
     *   Zenvia:   ['api_token' => '', 'from' => '']
     *   Mobizon:  ['api_key' => '', 'from' => '']
     *   SMTP:     ['host' => '', 'port' => '', 'username' => '', 'password' => '', 'encryption' => '']
     *   Mailgun:  ['api_key' => '', 'domain' => '', 'endpoint' => '']
     *   Resend:   ['api_key' => '']
     *   SendGrid: ['api_key' => '']
     *   SES:      ['key' => '', 'secret' => '', 'region' => '']
     *   Postmark: ['server_token' => '']
     *   FCM:      ['credentials_json' => '...']
     *   APNS:     ['key_id' => '', 'team_id' => '', 'bundle_id' => '', 'private_key' => '']
     *   Telegram: ['bot_token' => '']
     *   Slack:    ['bot_token' => '']
     *   Discord:  ['webhook_url' => '']
     *   Teams:    ['webhook_url' => '']
     *   Pusher:   ['app_id' => '', 'app_key' => '', 'app_secret' => '', 'cluster' => '']
     *   Webhook:  ['default_url' => '']
     */
    public function credentials(array $credentials): static
    {
        $this->credentials = $credentials;
        return $this;
    }

    /**
     * Marca esta configuração como padrão do canal ao salvar.
     * Remove is_default de todas as outras configs do mesmo canal.
     */
    public function asDefault(bool $isDefault = true): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    /**
     * Define se a configuração está ativa (padrão: true no servidor).
     */
    public function active(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * Envia a configuração ao servidor.
     *
     * create() → POST /api/v1/configurations  → retorna ['id', 'label', 'channel', 'driver']
     * update() → PUT  /api/v1/configurations/{id} → retorna bool
     *
     * @throws \RuntimeException se a requisição falhar
     */
    public function save(): array|bool
    {
        $http = Http::withHeaders(['X-API-KEY' => config('notify.key', '')])
            ->acceptJson()
            ->baseUrl('https://notifykit.app.br');

        if ($this->mode === 'create') {
            $payload = array_filter([
                'channel'     => $this->channel,
                'driver'      => $this->driver,
                'label'       => $this->label,
                'credentials' => $this->credentials ?: null,
                'is_default'  => $this->isDefault,
            ], fn($v) => !is_null($v));

            $response = $http->post('/api/v1/configurations', $payload);

            if ($response->failed()) {
                Log::error('[NotifyConfig] Falha ao criar configuração.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('Falha ao criar configuração: ' . $response->body());
            }

            return $response->json('data', []);
        }

        // update — merge parcial de credenciais no servidor
        $payload = array_filter([
            'label'       => $this->label,
            'credentials' => $this->credentials ?: null,
            'is_default'  => $this->isDefault,
            'active'      => $this->active,
        ], fn($v) => !is_null($v));

        $response = $http->put("/api/v1/configurations/{$this->id}", $payload);

        if ($response->failed()) {
            Log::error('[NotifyConfig] Falha ao atualizar configuração.', [
                'id'     => $this->id,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Falha ao atualizar configuração: ' . $response->body());
        }

        return $response->json('status', false);
    }
}
