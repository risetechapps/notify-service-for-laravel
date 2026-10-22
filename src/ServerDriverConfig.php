<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Builder de configuração de credencial de driver no servidor (CRUD).
 * Recurso: /api/v1/configurations. Obtido por NotifyQuery::server()->{canal}()->config().
 * O driver é informado por ->driver('twilio').
 *
 *   // Criar (sem id → POST)
 *   NotifyQuery::server()->push()->config()
 *       ->driver('fcm')
 *       ->label('FCM Android')
 *       ->projectId('meu-projeto')
 *       ->credentialsFile('/path/sa.json')
 *       ->asDefault()
 *       ->save();
 *
 *   // Atualizar (com id → PUT, merge parcial) — mesmo save()
 *   NotifyQuery::server()->push()->config('uuid')->apiKey('nova')->save();
 *
 *   // Detalhe / padrão / remover
 *   NotifyQuery::server()->push()->config('uuid')->get();
 *   NotifyQuery::server()->push()->config('uuid')->setDefault();
 *   NotifyQuery::server()->push()->config('uuid')->delete();
 */
class ServerDriverConfig
{
    protected ?string $driver = null;

    protected ?string $label = null;

    protected array $credentials = [];

    protected ?bool $isDefault = null;

    protected ?bool $active = null;

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $channel,
        protected ?string $id = null,
    ) {}

    // ── Opções gerais ───────────────────────────────────────────────────────────

    /** Driver desta credencial (ex.: twilio, smtp, fcm, pusher). Obrigatório ao criar. */
    public function driver(string $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Provedor automático: usa o provedor e as credenciais da plataforma, sem
     * credenciais suas, e acompanha se a plataforma trocar de provedor. Só nos
     * canais com supports_auto em drivers() (o WhatsApp não tem).
     */
    public function auto(): static
    {
        return $this->driver('auto');
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** Define esta configuração como padrão do canal ao salvar/atualizar. */
    public function asDefault(bool $isDefault = true): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function active(bool $active = true): static
    {
        $this->active = $active;

        return $this;
    }

    /** Define uma credencial avulsa (chave => valor). */
    public function credential(string $key, mixed $value): static
    {
        $this->credentials[$key] = $value;

        return $this;
    }

    /** Mescla um array de credenciais (chaves do servidor). */
    public function credentials(array $credentials): static
    {
        $this->credentials = array_merge($this->credentials, $credentials);

        return $this;
    }

    // ── Credenciais — SMS (Twilio: sid/token/from · ClickSend: username/api_key/from · Mobizon: key/api_server)
    public function sid(string $sid): static
    {
        return $this->credential('sid', $sid);
    }

    public function token(string $token): static
    {
        return $this->credential('token', $token);
    }

    public function from(string $from): static
    {
        return $this->credential('from', $from);
    }

    public function key(string $key): static
    {
        return $this->credential('key', $key);
    }

    public function apiServer(string $apiServer): static
    {
        return $this->credential('api_server', $apiServer);
    }

    // ── Credenciais — Email (SMTP / Mailgun / Resend / SendGrid / SES / Postmark)
    public function host(string $host): static
    {
        return $this->credential('host', $host);
    }

    public function port(int $port): static
    {
        return $this->credential('port', $port);
    }

    public function username(string $username): static
    {
        return $this->credential('username', $username);
    }

    public function password(string $password): static
    {
        return $this->credential('password', $password);
    }

    public function encryption(string $encryption): static
    {
        return $this->credential('encryption', $encryption);
    }

    public function domain(string $domain): static
    {
        return $this->credential('domain', $domain);
    }

    public function secret(string $secret): static
    {
        return $this->credential('secret', $secret);
    }

    public function endpoint(string $endpoint): static
    {
        return $this->credential('endpoint', $endpoint);
    }

    public function apiKey(string $apiKey): static
    {
        return $this->credential('api_key', $apiKey);
    }

    public function region(string $region): static
    {
        return $this->credential('region', $region);
    }

    // ── Credenciais — FCM / APNS ────────────────────────────────────────────────
    public function projectId(string $projectId): static
    {
        return $this->credential('project_id', $projectId);
    }

    /**
     * Lê o arquivo JSON do Service Account (FCM) e envia o CONTEÚDO parseado em
     * `credentials_json` — não o caminho. Ex.: ->credentialsFile(storage_path('app/fcm.json')).
     */
    public function credentialsFile(string $path): static
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \InvalidArgumentException("Service Account file not found or unreadable: {$path}");
        }

        $json = json_decode($contents, true);

        if (! is_array($json)) {
            throw new \InvalidArgumentException("Service Account file is not valid JSON: {$path}");
        }

        return $this->credential('credentials_json', $json);
    }

    /**
     * Service Account inline em `credentials_json`. Aceita array ou string JSON
     * (a string é decodificada para objeto). Alternativa ao ->credentialsFile().
     */
    public function credentialsJson(array|string $json): static
    {
        if (is_string($json)) {
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                throw new \InvalidArgumentException('credentialsJson(): invalid JSON string provided');
            }

            $json = $decoded;
        }

        return $this->credential('credentials_json', $json);
    }

    public function keyPath(string $path): static
    {
        return $this->credential('key_path', $path);
    }

    public function keyId(string $keyId): static
    {
        return $this->credential('key_id', $keyId);
    }

    public function teamId(string $teamId): static
    {
        return $this->credential('team_id', $teamId);
    }

    public function bundleId(string $bundleId): static
    {
        return $this->credential('bundle_id', $bundleId);
    }

    public function production(bool $production = true): static
    {
        return $this->credential('production', $production);
    }

    // ── Credenciais — Telegram / Slack / Discord / Teams ────────────────────────
    public function botToken(string $botToken): static
    {
        return $this->credential('bot_token', $botToken);
    }

    public function webhookUrl(string $url): static
    {
        return $this->credential('webhook_url', $url);
    }

    public function defaultChannel(string $channel): static
    {
        return $this->credential('default_channel', $channel);
    }

    public function avatarUrl(string $url): static
    {
        return $this->credential('avatar_url', $url);
    }

    public function defaultUsername(string $username): static
    {
        return $this->credential('username', $username);
    }

    public function themeColor(string $color): static
    {
        return $this->credential('theme_color', $color);
    }

    // ── Credenciais — Pusher (WebSocket) ────────────────────────────────────────
    public function appId(string $appId): static
    {
        return $this->credential('app_id', $appId);
    }

    public function cluster(string $cluster): static
    {
        return $this->credential('cluster', $cluster);
    }

    public function pusherHost(string $host): static
    {
        return $this->credential('host', $host);
    }

    public function pusherPort(int $port): static
    {
        return $this->credential('port', $port);
    }

    public function pusherScheme(string $scheme): static
    {
        return $this->credential('scheme', $scheme);
    }

    // ── Credenciais — Webhook (driver generic) ──────────────────────────────────
    public function defaultUrl(string $url): static
    {
        return $this->credential('default_url', $url);
    }

    public function timeout(int $seconds): static
    {
        return $this->credential('timeout', $seconds);
    }

    public function authType(string $type): static
    {
        return $this->credential('auth_type', $type);
    }

    public function authToken(string $token): static
    {
        return $this->credential('auth_token', $token);
    }

    public function authUser(string $user): static
    {
        return $this->credential('auth_user', $user);
    }

    public function authPassword(string $password): static
    {
        return $this->credential('auth_password', $password);
    }

    public function apiKeyHeader(string $header): static
    {
        return $this->credential('api_key_header', $header);
    }

    public function injectMetadata(bool $inject = true): static
    {
        return $this->credential('inject_metadata', $inject);
    }

    // ── Ações ───────────────────────────────────────────────────────────────────

    /**
     * Salva a configuração no servidor — decide sozinho entre criar e atualizar:
     *   - sem id (config())      → POST  /api/v1/configurations         (cadastro)
     *   - com id (config('uuid'))→ PUT   /api/v1/configurations/{id}    (atualização, merge parcial)
     *
     * No cadastro o driver é obrigatório; na atualização o driver não muda
     * (envia só label/credentials/is_default/active).
     */
    public function save(): array
    {
        // Atualização (PUT) quando há id do recurso.
        if ($this->id !== null) {
            return $this->http()->put("/api/v1/configurations/{$this->id}", array_filter([
                'label' => $this->label,
                'credentials' => $this->credentials ?: null,
                'is_default' => $this->isDefault,
                'active' => $this->active,
            ], fn ($v) => ! is_null($v)))->json() ?? [];
        }

        // Cadastro (POST).
        if ($this->driver === null) {
            return ['status' => false, 'message' => 'Driver is required (use ->driver(...)).', 'http' => 0];
        }

        return $this->http()->post('/api/v1/configurations', array_filter([
            'channel' => $this->channel,
            'driver' => $this->driver,
            'label' => $this->label,
            'credentials' => $this->credentials ?: null,
            'is_default' => $this->isDefault,
            'active' => $this->active,
        ], fn ($v) => ! is_null($v)))->json() ?? [];
    }

    /**
     * Busca configuração(ões) no servidor:
     *   - com id (config('uuid')) → detalhe (metadados + credential_keys, nunca valores).
     *     GET /api/v1/configurations/{id}.
     *   - sem id (config())       → lista as configs ativas deste canal.
     *     GET /api/v1/configurations?channel={channel}.
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/configurations/{$this->id}")->json('data', []);
        }

        return $this->http()->get('/api/v1/configurations', ['channel' => $this->channel])->json('data', []);
    }

    /** Define como padrão do canal. PATCH /api/v1/configurations/{id}/set-default. */
    public function setDefault(): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        return $this->http()->patch("/api/v1/configurations/{$this->id}/set-default")->json() ?? [];
    }

    /**
     * Envia uma mensagem de teste por esta configuração (exige o id). $to:
     * destino do teste (e-mail, telefone, token…); sem ele, o servidor usa o
     * padrão do canal quando há.
     */
    public function test(?string $to = null): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/configurations/{$this->id}/test", array_filter(['to' => $to]));

        return ($response->json() ?? []) + ['http' => $response->status()];
    }

    /** Remove a configuração. DELETE /api/v1/configurations/{id}. */
    public function delete(): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Config id is required to delete.', 'http' => 0];
        }

        return $this->http()->delete("/api/v1/configurations/{$this->id}")->json() ?? [];
    }

    /**
     * Aliases de canal desta configuração (apenas Telegram e Slack).
     * Nomes lógicos (ex.: "equipe") que o servidor resolve para o destino real
     * (chat_id no Telegram, ID/#canal no Slack) na hora do envio. Requer o id da config.
     *
     *   NotifyQuery::server()->telegram()->config('uuid')->channels()->all();
     *   NotifyQuery::server()->telegram()->config('uuid')->channels()->set('equipe', '-1003142488245');
     *   NotifyQuery::server()->slack()->config('uuid')->channels()->remove('equipe');
     */
    public function channels(): ServerChannelAlias
    {
        return new ServerChannelAlias($this->apiUrl, $this->apiKey, $this->id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
