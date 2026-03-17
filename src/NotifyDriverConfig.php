<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

/**
 * Gerencia configurações de driver no servidor NotifyKit.
 *
 * Permite criar, listar, atualizar e remover as credenciais de cada canal
 * diretamente pelo package, sem precisar acessar o painel do servidor.
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * LISTAR
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyDriverConfig::list();                   // todas as configs
 *   NotifyDriverConfig::list('sms');              // filtra por canal
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * CRIAR — SMS
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyDriverConfig::twilio('Twilio Principal')
 *       ->sid('ACxxxxxxxx')
 *       ->token('xxxxxxxx')
 *       ->from('+5511999999999')
 *       ->asDefault()
 *       ->save();
 *
 *   NotifyDriverConfig::zenvia('Zenvia Prod')
 *       ->apiToken('xxxxxxxx')
 *       ->senderId('EMPRESA')
 *       ->save();
 *
 *   NotifyDriverConfig::mobizon('Mobizon')
 *       ->key('xxxxxxxx')
 *       ->save();
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * CRIAR — EMAIL
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyDriverConfig::smtp('SMTP Produção')
 *       ->host('smtp.empresa.com')
 *       ->port(587)
 *       ->username('user@empresa.com')
 *       ->password('senha')
 *       ->encryption('tls')
 *       ->save();
 *
 *   NotifyDriverConfig::mailgun('Mailgun')
 *       ->domain('mg.empresa.com')
 *       ->secret('key-xxxxxxxx')
 *       ->save();
 *
 *   NotifyDriverConfig::resend('Resend')
 *       ->apiKey('re_xxxxxxxx')
 *       ->save();
 *
 *   NotifyDriverConfig::sendgrid('Sendgrid')
 *       ->apiKey('SG.xxxxxxxx')
 *       ->save();
 *
 *   NotifyDriverConfig::ses('SES')
 *       ->key('AKIAXXXXXXXX')
 *       ->secret('xxxxxxxx')
 *       ->region('us-east-1')
 *       ->save();
 *
 *   NotifyDriverConfig::postmark('Postmark')
 *       ->token('xxxxxxxx')
 *       ->save();
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * CRIAR — OUTROS CANAIS
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyDriverConfig::fcm('FCM Android')
 *       ->projectId('meu-projeto')
 *       ->credentialsFile('/path/to/service-account.json')
 *       ->save();
 *
 *   NotifyDriverConfig::apns('APNS iOS')
 *       ->keyPath('/path/to/AuthKey.p8')
 *       ->keyId('XXXXXXXXXX')
 *       ->teamId('XXXXXXXXXX')
 *       ->bundleId('com.empresa.app')
 *       ->production(true)
 *       ->save();
 *
 *   NotifyDriverConfig::telegram('Telegram Bot')
 *       ->botToken('123456789:AAxxxxxxxxxx')
 *       ->save();
 *
 *   NotifyDriverConfig::slack('Slack Workspace')
 *       ->webhookUrl('https://hooks.slack.com/services/xxx')
 *       ->botToken('xoxb-xxxxxxxx')        // opcional — para envio via API
 *       ->defaultChannel('#geral')
 *       ->save();
 *
 *   NotifyDriverConfig::discord('Discord Server')
 *       ->webhookUrl('https://discord.com/api/webhooks/xxx/yyy')
 *       ->username('NotifyBot')
 *       ->avatarUrl('https://...')
 *       ->save();
 *
 *   NotifyDriverConfig::teams('Teams Channel')
 *       ->webhookUrl('https://outlook.office.com/webhook/xxx')
 *       ->save();
 *
 *   NotifyDriverConfig::pusher('Pusher')
 *       ->appId('xxxxxxxx')
 *       ->key('xxxxxxxx')
 *       ->secret('xxxxxxxx')
 *       ->cluster('mt1')
 *       ->save();
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * ATUALIZAR / REMOVER / DEFINIR PADRÃO
 * ══════════════════════════════════════════════════════════════════════════════
 *
 *   NotifyDriverConfig::update('config-uuid', ['label' => 'Novo nome']);
 *   NotifyDriverConfig::setDefault('config-uuid');
 *   NotifyDriverConfig::delete('config-uuid');
 *   NotifyDriverConfig::get('config-uuid');
 */
class NotifyDriverConfig
{
    protected string $channel;
    protected string $driver;
    protected string $label;
    protected array  $credentials = [];
    protected bool   $isDefault   = false;

    protected string $apiUrl;
    protected string $apiKey;

    protected function __construct(string $channel, string $driver, string $label)
    {
        $this->channel = $channel;
        $this->driver  = $driver;
        $this->label   = $label;
        $this->apiUrl  = 'https://notifykit.app.br';
        $this->apiKey  = config('notify.key', '');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONSTRUTORES ESTÁTICOS — SMS
    // ══════════════════════════════════════════════════════════════════════════

    public static function twilio(string $label): static
    {
        return new static('sms', 'twilio', $label);
    }

    public static function zenvia(string $label): static
    {
        return new static('sms', 'zenvia', $label);
    }

    public static function mobizon(string $label): static
    {
        return new static('sms', 'mobizon', $label);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONSTRUTORES ESTÁTICOS — EMAIL
    // ══════════════════════════════════════════════════════════════════════════

    public static function smtp(string $label): static
    {
        return new static('email', 'smtp', $label);
    }

    public static function mailgun(string $label): static
    {
        return new static('email', 'mailgun', $label);
    }

    public static function resend(string $label): static
    {
        return new static('email', 'resend', $label);
    }

    public static function sendgrid(string $label): static
    {
        return new static('email', 'sendgrid', $label);
    }

    public static function ses(string $label): static
    {
        return new static('email', 'ses', $label);
    }

    public static function postmark(string $label): static
    {
        return new static('email', 'postmark', $label);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONSTRUTORES ESTÁTICOS — OUTROS CANAIS
    // ══════════════════════════════════════════════════════════════════════════

    public static function fcm(string $label): static
    {
        return new static('push', 'fcm', $label);
    }

    public static function apns(string $label): static
    {
        return new static('apns', 'apns', $label);
    }

    public static function telegram(string $label): static
    {
        return new static('telegram', 'telegram', $label);
    }

    public static function slack(string $label): static
    {
        return new static('slack', 'slack', $label);
    }

    public static function discord(string $label): static
    {
        return new static('discord', 'discord_webhook', $label);
    }

    public static function teams(string $label): static
    {
        return new static('teams', 'teams_webhook', $label);
    }

    public static function pusher(string $label): static
    {
        return new static('websocket', 'pusher', $label);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — TWILIO
    // ══════════════════════════════════════════════════════════════════════════

    public function sid(string $sid): static
    {
        $this->credentials['sid'] = $sid;
        return $this;
    }

    public function token(string $token): static
    {
        $this->credentials['token'] = $token;
        return $this;
    }

    public function from(string $from): static
    {
        $this->credentials['from'] = $from;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — ZENVIA
    // ══════════════════════════════════════════════════════════════════════════

    public function apiToken(string $apiToken): static
    {
        $this->credentials['api_token'] = $apiToken;
        return $this;
    }

    public function senderId(string $senderId): static
    {
        $this->credentials['sender_id'] = $senderId;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — MOBIZON
    // ══════════════════════════════════════════════════════════════════════════

    public function key(string $key): static
    {
        $this->credentials['key'] = $key;
        return $this;
    }

    public function apiServer(string $apiServer): static
    {
        $this->credentials['api_server'] = $apiServer;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — SMTP
    // ══════════════════════════════════════════════════════════════════════════

    public function host(string $host): static
    {
        $this->credentials['host'] = $host;
        return $this;
    }

    public function port(int $port): static
    {
        $this->credentials['port'] = $port;
        return $this;
    }

    public function username(string $username): static
    {
        $this->credentials['username'] = $username;
        return $this;
    }

    public function password(string $password): static
    {
        $this->credentials['password'] = $password;
        return $this;
    }

    public function encryption(string $encryption): static
    {
        $this->credentials['encryption'] = $encryption;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — MAILGUN
    // ══════════════════════════════════════════════════════════════════════════

    public function domain(string $domain): static
    {
        $this->credentials['domain'] = $domain;
        return $this;
    }

    public function secret(string $secret): static
    {
        $this->credentials['secret'] = $secret;
        return $this;
    }

    public function endpoint(string $endpoint): static
    {
        $this->credentials['endpoint'] = $endpoint;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — RESEND / SENDGRID
    // ══════════════════════════════════════════════════════════════════════════

    public function apiKey(string $apiKey): static
    {
        $this->credentials['api_key'] = $apiKey;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — SES
    // ══════════════════════════════════════════════════════════════════════════

    public function region(string $region): static
    {
        $this->credentials['region'] = $region;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — FCM
    // ══════════════════════════════════════════════════════════════════════════

    public function projectId(string $projectId): static
    {
        $this->credentials['project_id'] = $projectId;
        return $this;
    }

    public function credentialsFile(string $path): static
    {
        $this->credentials['credentials_file'] = $path;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — APNS
    // ══════════════════════════════════════════════════════════════════════════

    public function keyPath(string $path): static
    {
        $this->credentials['key_path'] = $path;
        return $this;
    }

    public function keyId(string $keyId): static
    {
        $this->credentials['key_id'] = $keyId;
        return $this;
    }

    public function teamId(string $teamId): static
    {
        $this->credentials['team_id'] = $teamId;
        return $this;
    }

    public function bundleId(string $bundleId): static
    {
        $this->credentials['bundle_id'] = $bundleId;
        return $this;
    }

    public function production(bool $production = true): static
    {
        $this->credentials['production'] = $production;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — TELEGRAM
    // ══════════════════════════════════════════════════════════════════════════

    public function botToken(string $botToken): static
    {
        $this->credentials['bot_token'] = $botToken;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — SLACK
    // ══════════════════════════════════════════════════════════════════════════

    public function webhookUrl(string $url): static
    {
        $this->credentials['webhook_url'] = $url;
        return $this;
    }

    public function botToken2(string $token): static
    {
        // alias para não colidir com botToken() do Telegram
        $this->credentials['bot_token'] = $token;
        return $this;
    }

    public function defaultChannel(string $channel): static
    {
        $this->credentials['default_channel'] = $channel;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — DISCORD
    // ══════════════════════════════════════════════════════════════════════════

    public function avatarUrl(string $url): static
    {
        $this->credentials['avatar_url'] = $url;
        return $this;
    }

    public function defaultUsername(string $username): static
    {
        $this->credentials['username'] = $username;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — TEAMS
    // ══════════════════════════════════════════════════════════════════════════

    public function themeColor(string $color): static
    {
        $this->credentials['theme_color'] = $color;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CREDENCIAIS — PUSHER
    // ══════════════════════════════════════════════════════════════════════════

    public function appId(string $appId): static
    {
        $this->credentials['app_id'] = $appId;
        return $this;
    }

    public function cluster(string $cluster): static
    {
        $this->credentials['cluster'] = $cluster;
        return $this;
    }

    public function pusherHost(string $host): static
    {
        $this->credentials['host'] = $host;
        return $this;
    }

    public function pusherPort(int $port): static
    {
        $this->credentials['port'] = $port;
        return $this;
    }

    public function pusherScheme(string $scheme): static
    {
        $this->credentials['scheme'] = $scheme;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OPÇÕES GERAIS
    // ══════════════════════════════════════════════════════════════════════════

    /** Define esta configuração como padrão para o canal ao salvar. */
    public function asDefault(): static
    {
        $this->isDefault = true;
        return $this;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AÇÕES (POST ao servidor)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Salva a configuração no servidor.
     * Retorna o array de resposta com: status, message, data.id, data.label, etc.
     */
    public function save(): array
    {
        $response = $this->http()->post('/api/v1/configurations', [
            'channel'     => $this->channel,
            'driver'      => $this->driver,
            'label'       => $this->label,
            'credentials' => $this->credentials,
            'is_default'  => $this->isDefault,
        ]);

        return $response->json() ?? [];
    }

    /**
     * Lista as configurações cadastradas no servidor.
     *
     * @param  string|null $channel  Filtra por canal: sms, email, push, etc.
     */
    public static function list(?string $channel = null): array
    {
        $params   = $channel ? ['channel' => $channel] : [];
        $response = static::makeHttp()->get('/api/v1/configurations', $params);

        return $response->json('data', []);
    }

    /**
     * Busca detalhes de uma configuração (retorna chaves das credenciais, sem valores).
     */
    public static function get(string $configId): array
    {
        $response = static::makeHttp()->get("/api/v1/configurations/{$configId}");

        return $response->json('data', []);
    }

    /**
     * Atualiza campos de uma configuração existente.
     * Para credenciais, faz merge parcial — só os campos enviados são atualizados.
     *
     * @param  string $configId
     * @param  array  $data  Campos: label, credentials, is_default, active
     */
    public static function update(string $configId, array $data): array
    {
        $response = static::makeHttp()->put("/api/v1/configurations/{$configId}", $data);

        return $response->json() ?? [];
    }

    /**
     * Define uma configuração como padrão para o canal.
     */
    public static function setDefault(string $configId): array
    {
        $response = static::makeHttp()->patch("/api/v1/configurations/{$configId}/set-default");

        return $response->json() ?? [];
    }

    /**
     * Remove uma configuração do servidor.
     */
    public static function delete(string $configId): array
    {
        $response = static::makeHttp()->delete("/api/v1/configurations/{$configId}");

        return $response->json() ?? [];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INTERNOS
    // ══════════════════════════════════════════════════════════════════════════

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }

    protected static function makeHttp()
    {
        return Http::withHeaders(['X-API-KEY' => config('notify.key', '')])
            ->acceptJson()
            ->baseUrl('https://notifykit.app.br');
    }
}
