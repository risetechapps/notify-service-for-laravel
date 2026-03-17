<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Gerencia as configurações de driver no servidor NotifyKit.
 *
 * Cada canal (sms, email, push, etc.) pode ter múltiplas configurações
 * de credenciais salvas no servidor. Uma delas pode ser marcada como padrão (is_default).
 *
 * ── Exemplos de uso ───────────────────────────────────────────────────────────
 *
 *   // Listar todas as configs
 *   NotifyConfiguration::all();
 *
 *   // Filtrar por canal
 *   NotifyConfiguration::channel('sms');
 *
 *   // Criar config de SMS (Twilio)
 *   NotifyConfiguration::create([
 *       'channel'    => 'sms',
 *       'driver'     => 'twilio',
 *       'label'      => 'Twilio Principal',
 *       'is_default' => true,
 *       'credentials' => [
 *           'account_sid'  => 'ACxxxxxxxx',
 *           'auth_token'   => 'xxxxxxxx',
 *           'from'         => '+15551234567',
 *       ],
 *   ]);
 *
 *   // Criar config de Email (SMTP)
 *   NotifyConfiguration::create([
 *       'channel'    => 'email',
 *       'driver'     => 'smtp',
 *       'label'      => 'SMTP Produção',
 *       'is_default' => true,
 *       'credentials' => [
 *           'host'       => 'smtp.mailserver.com',
 *           'port'       => 587,
 *           'username'   => 'user@dominio.com',
 *           'password'   => 'senha',
 *           'encryption' => 'tls',
 *       ],
 *   ]);
 *
 *   // Buscar uma config
 *   NotifyConfiguration::find('uuid');
 *
 *   // Atualizar (merge parcial nas credenciais)
 *   NotifyConfiguration::update('uuid', ['label' => 'Novo nome']);
 *   NotifyConfiguration::update('uuid', ['credentials' => ['password' => 'nova-senha']]);
 *
 *   // Definir como padrão
 *   NotifyConfiguration::setDefault('uuid');
 *
 *   // Remover
 *   NotifyConfiguration::delete('uuid');
 */
class NotifyConfiguration
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = 'https://notifykit.app.br';
        $this->apiKey = config('notify.key', '');
    }

    // ── Estáticos (atalhos) ───────────────────────────────────────────────────

    /** Instância para encadeamento interno. */
    protected static function make(): static
    {
        return new static();
    }

    /**
     * Lista todas as configurações de driver.
     *
     * @param  string|null $channel  Filtra por canal: sms|email|push|apns|telegram|slack|discord|teams|websocket|webhook
     * @return array
     */
    public static function all(?string $channel = null): array
    {
        return static::make()->index($channel);
    }

    /**
     * Lista configurações de um canal específico.
     */
    public static function channel(string $channel): array
    {
        return static::make()->index($channel);
    }

    /**
     * Cria uma nova configuração de driver no servidor.
     *
     * @param  array $data  Campos: channel*, driver*, label, credentials*, is_default
     * @return array        Dados da configuração criada: id, label, channel, driver
     */
    public static function create(array $data): array
    {
        return static::make()->store($data);
    }

    /**
     * Busca detalhes de uma configuração pelo ID.
     * Retorna as chaves das credenciais (nunca os valores).
     */
    public static function find(string $id): array
    {
        return static::make()->show($id);
    }

    /**
     * Atualiza uma configuração existente.
     * Credenciais são mescladas parcialmente — só os campos enviados são alterados.
     *
     * @param  string $id
     * @param  array  $data  Campos: label, credentials, is_default, active
     * @return array
     */
    public static function update(string $id, array $data): array
    {
        return static::make()->put($id, $data);
    }

    /**
     * Remove uma configuração.
     */
    public static function delete(string $id): array
    {
        return static::make()->destroy($id);
    }

    /**
     * Define uma configuração como padrão para seu canal.
     * Remove is_default de todas as outras configs do mesmo canal.
     */
    public static function setDefault(string $id): array
    {
        return static::make()->makeDefault($id);
    }

    // ── Métodos HTTP internos ─────────────────────────────────────────────────

    protected function index(?string $channel = null): array
    {
        $response = $this->http()->get('/api/v1/configurations', array_filter([
            'channel' => $channel,
        ]));

        $this->throwIfFailed($response, 'Erro ao listar configurações.');

        return $response->json('data', []);
    }

    protected function store(array $data): array
    {
        $this->validate($data, ['channel', 'driver', 'credentials']);

        $response = $this->http()->post('/api/v1/configurations', $data);

        $this->throwIfFailed($response, 'Erro ao criar configuração.');

        return $response->json('data', []);
    }

    protected function show(string $id): array
    {
        $response = $this->http()->get("/api/v1/configurations/{$id}");

        $this->throwIfFailed($response, "Configuração {$id} não encontrada.");

        return $response->json('data', []);
    }

    protected function put(string $id, array $data): array
    {
        $response = $this->http()->put("/api/v1/configurations/{$id}", $data);

        $this->throwIfFailed($response, "Erro ao atualizar configuração {$id}.");

        return $response->json() ?? [];
    }

    protected function destroy(string $id): array
    {
        $response = $this->http()->delete("/api/v1/configurations/{$id}");

        $this->throwIfFailed($response, "Erro ao remover configuração {$id}.");

        return $response->json() ?? [];
    }

    protected function makeDefault(string $id): array
    {
        $response = $this->http()->patch("/api/v1/configurations/{$id}/set-default");

        $this->throwIfFailed($response, "Erro ao definir configuração {$id} como padrão.");

        return $response->json() ?? [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }

    protected function validate(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("O campo '{$field}' é obrigatório para criar uma configuração.");
            }
        }
    }

    protected function throwIfFailed($response, string $message): void
    {
        if ($response->failed()) {
            $serverMessage = $response->json('message') ?? $response->body();
            throw new \RuntimeException("{$message} Resposta do servidor: {$serverMessage}");
        }
    }
}
