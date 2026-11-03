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
 * O QR expira em ~40 s. Sem câmera (o número está no próprio celular), passe o
 * telefone: state('5521999998888') devolve também o `pairing_code`, para
 * digitar em "Conectar com número de telefone". Um número só fica conectado
 * em uma configuração.
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
            'driver' => 'wapi',
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

    /**
     * Estado da conexão e, enquanto não conecta, o QR code (data URI); com o
     * telefone, também o pairing_code. Cada pedido com telefone gera um código
     * novo e invalida o anterior: peça uma vez e acompanhe com $qr = false
     * (só o estado, sem QR nem código novo).
     */
    public function state(?string $phone = null, bool $qr = true): array
    {
        return $this->result($this->http()->get($this->path(), self::filled(['phone' => $phone, 'qr' => $qr ? null : 0])));
    }

    /**
     * Reinicia a sessão do número na W-API (reconecta sem QR). É o primeiro
     * passo quando o número aparece como "sem receber" (`connection.stalled_at`);
     * a marca sai sozinha na próxima mensagem ou recibo.
     */
    public function restart(): array
    {
        $result = $this->result($this->http()->post($this->path('/restart')));
        $this->forget('chat.numbers');

        return $result;
    }

    /** Resposta automática ao SAIR e ao VOLTAR: `enabled`, os textos e os `defaults`. */
    public function optOutReply(): array
    {
        return $this->result($this->http()->get($this->path('/opt-out')));
    }

    /**
     * Liga/desliga a resposta ao SAIR e ao VOLTAR. Texto null (ou vazio) usa o
     * padrão do servidor; $enabled = false não responde nada.
     */
    public function updateOptOutReply(bool $enabled, ?string $optOutMessage = null, ?string $optInMessage = null): array
    {
        return $this->result($this->http()->put($this->path('/opt-out'), [
            'enabled' => $enabled,
            'opt_out_message' => $optOutMessage,
            'opt_in_message' => $optInMessage,
        ]));
    }

    /** Desconecta o número (para voltar, leia o QR de novo). */
    public function logout(): array
    {
        $result = $this->result($this->http()->post($this->path('/logout')));
        $this->forget('chat.numbers');

        return $result;
    }

    /** Avisos de queda e volta (e-mail e/ou webhook whatsapp.disconnected/reconnected/stalled/paused). */
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
        $result = $this->result($this->http()->put($this->path('/chat'), $settings));
        $this->forget('chat.numbers');

        return $result;
    }

    /** Proteção do número: envios e contatos novos do dia, limites, aquecimento, saúde e pausa. */
    public function protection(): array
    {
        return $this->result($this->http()->get($this->path('/protection')));
    }

    /**
     * Quanto o número ainda pode mandar hoje, já com o aquecimento aplicado,
     * para conferir antes de um envio grande:
     *
     *   $cap = NotifyQuery::server()->whatsappNumber($id)->capacity()['data'];
     *   if ($cap['paused'] || $cap['new_contacts'] < count($clientesNovos)) { … }
     *
     * sends: envios restantes hoje; new_contacts: contatos novos (quem nunca
     * escreveu ao número) restantes hoje; paused: motivo da pausa ou null;
     * warmup_factor: fração dos limites liberada pela idade do número
     * (0.2 no 1º dia … 1.0 a partir de 14 dias); age_days.
     *
     * @return array{status: bool, data?: array{sends: int, new_contacts: int, paused: ?string, warmup_factor: float, age_days: int}}
     */
    public function capacity(): array
    {
        $summary = $this->protection();

        if (isset($summary['http']) || ! isset($summary['data'])) {
            return $summary;
        }

        $data = $summary['data'];
        $today = (array) ($data['today'] ?? []);

        return ['status' => true, 'data' => [
            'sends' => max(0, (int) ($today['daily_limit'] ?? 0) - (int) ($today['sent'] ?? 0)),
            'new_contacts' => max(0, (int) ($today['new_contacts_limit'] ?? 0) - (int) ($today['new_contacts'] ?? 0)),
            'paused' => $data['paused']['reason'] ?? null,
            'warmup_factor' => (float) ($data['warmup']['factor'] ?? 1.0),
            'age_days' => (int) ($data['warmup']['age_days'] ?? 0),
        ]];
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
