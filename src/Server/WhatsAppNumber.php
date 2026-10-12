<?php

namespace RiseTechApps\Notify\Server;

/**
 * Um número de WhatsApp (configuração do canal whatsapp no servidor).
 *
 *   $numero = NotifyQuery::server()->whatsappNumber()->create('Atendimento');   // cria; devolve o id
 *   NotifyQuery::server()->whatsappNumber($id)->state();      // { state, qrcode (data URI), number }
 *   NotifyQuery::server()->whatsappNumber($id)->protection(); // uso do dia, limites, saúde
 *
 * Conectar: chame state() a cada 3–5 s e mostre o `qrcode` até `state` = open.
 * O QR expira em ~40 s. Um número só fica conectado em uma configuração.
 */
class WhatsAppNumber extends Resource
{
    public function __construct(protected ?string $configId = null, array $headers = [])
    {
        parent::__construct($headers);
    }

    /** Cria a configuração do número (o provedor é o da plataforma; nada de credenciais). */
    public function create(?string $label = null, bool $asDefault = false): array
    {
        return $this->result($this->http()->post('/api/v1/configurations', self::filled([
            'channel' => 'whatsapp',
            'driver' => 'evolution',
            'label' => $label,
            'credentials' => [],
            'is_default' => $asDefault ?: null,
        ])));
    }

    /** Lista os números (configurações do canal whatsapp). */
    public function all(): array
    {
        return $this->result($this->http()->get('/api/v1/configurations', ['channel' => 'whatsapp']));
    }

    /** Estado da conexão e, enquanto não conecta, o QR code (data URI). */
    public function state(): array
    {
        return $this->result($this->http()->get($this->path()));
    }

    /** Desconecta o número (para voltar, leia o QR de novo). */
    public function logout(): array
    {
        return $this->result($this->http()->post($this->path('/logout')));
    }

    /** Avisos de queda e volta (e-mail e/ou webhook whatsapp.disconnected/reconnected/paused). */
    public function alerts(): array
    {
        return $this->result($this->http()->get($this->path('/alerts')));
    }

    /** @param array{email?: bool, emails?: string[], webhook?: bool, webhook_url?: ?string, reconnect?: bool} $alerts */
    public function updateAlerts(array $alerts): array
    {
        return $this->result($this->http()->put($this->path('/alerts'), $alerts));
    }

    /** Configurações do chat: fluxo, avisos ao contato, inatividade, webhook e filtro de eventos. */
    public function chatSettings(): array
    {
        return $this->result($this->http()->get($this->path('/chat')));
    }

    /**
     * Liga/ajusta o chat do número. O servidor exige `enabled` e `mark_read`
     * em toda chamada; os demais campos ausentes mantêm o que estava.
     */
    public function updateChatSettings(array $settings): array
    {
        return $this->result($this->http()->put($this->path('/chat'), $settings));
    }

    /** Importa conversas do histórico do celular: '1d', '7d' ou '30d' (ou 'gap'/'none' para a pergunta pendente). */
    public function importHistory(string $period = '1d'): array
    {
        return $this->result($this->http()->post($this->path('/history'), ['period' => $period]));
    }

    /** Proteção do número: envios e contatos novos do dia, limites, aquecimento, saúde e pausa. */
    public function protection(): array
    {
        return $this->result($this->http()->get($this->path('/protection')));
    }

    /** Retoma os envios pausados pela proteção (as campanhas pausadas junto voltam a andar). */
    public function resumeProtection(): array
    {
        return $this->result($this->http()->post($this->path('/protection/resume')));
    }

    protected function path(string $suffix = ''): string
    {
        if ($this->configId === null) {
            throw new \InvalidArgumentException('Informe o id do número: NotifyQuery::server()->whatsappNumber($configId).');
        }

        return "/api/v1/configurations/{$this->configId}/whatsapp{$suffix}";
    }
}
