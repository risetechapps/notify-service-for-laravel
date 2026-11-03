<?php

namespace RiseTechApps\Notify\Server;

use RiseTechApps\Notify\Support\NotifyHttp;

/**
 * Fluxos de autoatendimento. O formato do `definition` (menu, palavras-chave,
 * horário, ações reply/menu/flow/handoff/close, anexos) está no guia
 * /docs/whatsapp do servidor. O número usa um fluxo principal
 * (bot_flow_id nas configurações do chat); os outros entram como subfluxos.
 */
class BotFlows extends Resource
{
    /** Guardado por notify.cache.ttl segundos; $fresh = true busca na hora. */
    public function all(bool $fresh = false): array
    {
        return $this->remember('chat.bot-flows', fn () => $this->result($this->http()->get('/api/v1/chat/bot-flows')), $fresh);
    }

    public function get(string $id): array
    {
        return $this->result($this->http()->get("/api/v1/chat/bot-flows/{$id}"));
    }

    public function create(string $name, array $definition): array
    {
        return $this->changed($this->result($this->http()->post('/api/v1/chat/bot-flows', ['name' => $name, 'definition' => $definition])));
    }

    public function update(string $id, string $name, array $definition): array
    {
        return $this->changed($this->result($this->http()->put("/api/v1/chat/bot-flows/{$id}", ['name' => $name, 'definition' => $definition])));
    }

    /**
     * Altera só partes do fluxo: lê o fluxo salvo, troca as chaves de primeiro
     * nível do `definition` que vierem em $changes (null remove a chave) e
     * salva. O resto (menu, palavras-chave…) fica como estava.
     *
     *   $flows->patch($id, ['close_message' => 'Até logo!', 'max_invalid' => 2]);
     */
    public function patch(string $id, array $changes, ?string $name = null): array
    {
        return $this->modify($id, function (array $definition) use ($changes) {
            foreach ($changes as $key => $value) {
                if ($value === null) {
                    unset($definition[$key]);
                } else {
                    $definition[$key] = $value;
                }
            }

            return $definition;
        }, $name);
    }

    /**
     * Horário de atendimento do fluxo e o que fazer fora dele.
     *
     *   $flows->updateBusinessHours($id, [
     *       'mon' => '08:00-18:00',
     *       'tue' => ['08:00-12:00', '13:00-18:00'],
     *       'sat' => [['start' => '08:00', 'end' => '12:00']],
     *   ], 'Estamos fora do horário. Respondemos amanhã a partir das 8h.');
     *
     * Dias: sun, mon, tue, wed, thu, fri, sat. Dia ausente = fechado o dia todo.
     * Horários em HH:MM, início antes do fim (o fim não entra: 18:00 já é fora).
     * $outsideHours: 'handoff' manda a mensagem e passa para a fila do
     * atendente; 'menu' manda a mensagem e segue no autoatendimento.
     * $awayMessage null mantém a mensagem que já estava (ou o padrão do servidor).
     */
    public function updateBusinessHours(
        string $id,
        array $days,
        ?string $awayMessage = null,
        string $timezone = 'America/Sao_Paulo',
        string $outsideHours = 'handoff',
    ): array {
        if (! in_array($outsideHours, ['handoff', 'menu'], true)) {
            throw new \InvalidArgumentException("outsideHours deve ser 'handoff' ou 'menu', recebido '{$outsideHours}'.");
        }

        $days = self::normalizeDays($days);

        return $this->modify($id, function (array $definition) use ($days, $awayMessage, $timezone, $outsideHours) {
            $definition['business_hours'] = self::filled([
                'enabled' => true,
                'timezone' => $timezone,
                'days' => $days,
                'away_message' => $awayMessage ?? $definition['business_hours']['away_message'] ?? null,
            ]);
            $definition['outside_hours'] = $outsideHours;

            return $definition;
        });
    }

    /** Só a mensagem de fora do expediente, sem mexer nos horários. */
    public function updateAwayMessage(string $id, string $awayMessage): array
    {
        return $this->modify($id, function (array $definition) use ($awayMessage) {
            $definition['business_hours'] = ['away_message' => $awayMessage] + (array) ($definition['business_hours'] ?? []);

            return $definition;
        });
    }

    /** Desliga o horário (atende a qualquer hora), guardando os horários para religar depois. */
    public function disableBusinessHours(string $id): array
    {
        return $this->modify($id, function (array $definition) {
            $definition['business_hours'] = ['enabled' => false] + (array) ($definition['business_hours'] ?? []);

            return $definition;
        });
    }

    /**
     * Dia sem expediente (feriado, folga, inventário…): nesse dia o bot
     * responde $message (null = a mensagem de fora do horário) e segue o
     * outside_hours, sem mexer no horário da semana. Vale mesmo com o horário
     * desligado. Marcar de novo a mesma data troca a mensagem.
     *
     *   $flows->addClosedDate($id, '2026-12-25', 'Feliz Natal! Voltamos dia 26.');
     *   $flows->addClosedDate($id, now()->addDay());   // amanhã, com a mensagem padrão
     *
     * Datas que já passaram (no fuso do fluxo) saem da lista ao salvar.
     * Precisa do notify-server com suporte a closed_dates.
     */
    public function addClosedDate(string $id, string|\DateTimeInterface $date, ?string $message = null): array
    {
        return $this->addClosedDates($id, [self::date($date) => $message]);
    }

    /**
     * Vários dias de uma vez: ['2026-12-24' => 'Só até 12h…', '2026-12-25' => null].
     * Também aceita uma lista de datas, todas com a mensagem padrão.
     */
    public function addClosedDates(string $id, array $dates): array
    {
        $new = [];

        foreach ($dates as $key => $value) {
            [$date, $message] = is_int($key) ? [$value, null] : [$key, $value];
            $new[self::date($date)] = $message;
        }

        return $this->modify($id, function (array $definition) use ($new) {
            $current = self::closedDatesOf($definition);

            foreach ($new as $date => $message) {
                $current[$date] = $message;
            }

            return self::withClosedDates($definition, $current);
        });
    }

    /** Desmarca o dia (volta a seguir o horário da semana). */
    public function removeClosedDate(string $id, string|\DateTimeInterface $date): array
    {
        $date = self::date($date);

        return $this->modify($id, function (array $definition) use ($date) {
            $current = self::closedDatesOf($definition);
            unset($current[$date]);

            return self::withClosedDates($definition, $current);
        });
    }

    /**
     * Dias sem expediente marcados no fluxo, em ordem: ['2026-12-25' => 'mensagem' | null].
     * Em erro, o retorno do servidor (com `http`).
     */
    public function closedDates(string $id): array
    {
        $current = $this->get($id);

        if (isset($current['http']) || ! isset($current['data']['definition'])) {
            return $current;
        }

        $dates = self::closedDatesOf((array) $current['data']['definition']);
        ksort($dates);

        return $dates;
    }

    /** @return array<string, ?string> data => mensagem */
    protected static function closedDatesOf(array $definition): array
    {
        $dates = [];

        foreach ((array) ($definition['business_hours']['closed_dates'] ?? []) as $entry) {
            if (isset($entry['date'])) {
                $dates[(string) $entry['date']] = isset($entry['message']) && $entry['message'] !== '' ? (string) $entry['message'] : null;
            }
        }

        return $dates;
    }

    /** Grava a lista em ordem, sem as datas que já passaram no fuso do fluxo. */
    protected static function withClosedDates(array $definition, array $dates): array
    {
        $timezone = (string) ($definition['business_hours']['timezone'] ?? 'America/Sao_Paulo');

        try {
            $today = (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d');
        } catch (\Exception) {
            $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        }

        ksort($dates);
        $list = [];

        foreach ($dates as $date => $message) {
            if ($date >= $today) {
                $list[] = $message === null ? ['date' => $date] : ['date' => $date, 'message' => $message];
            }
        }

        $definition['business_hours'] = ['closed_dates' => $list] + (array) ($definition['business_hours'] ?? []);

        return $definition;
    }

    /** AAAA-MM-DD; recusa data que não existe (ex.: 2026-02-30). */
    protected static function date(string|\DateTimeInterface $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Data inválida '{$date}': use AAAA-MM-DD.");
        }

        return $date;
    }

    /**
     * Lê o fluxo, aplica $change no `definition` e salva. Se a leitura falhar,
     * devolve o erro dela (com `http`) sem salvar nada.
     *
     * @param  callable(array): array  $change
     */
    protected function modify(string $id, callable $change, ?string $name = null): array
    {
        $current = $this->get($id);

        if (isset($current['http']) || ! isset($current['data']['definition'])) {
            return $current;
        }

        return $this->update($id, $name ?? (string) $current['data']['name'], $change((array) $current['data']['definition']));
    }

    /**
     * Aceita '08:00-18:00', ['08:00-12:00', '13:00-18:00'] ou
     * [['start' => '08:00', 'end' => '18:00']] por dia.
     *
     * @return array<string, array<int, array{start: string, end: string}>>
     */
    protected static function normalizeDays(array $days): array
    {
        $valid = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        $normalized = [];

        foreach ($days as $day => $intervals) {
            $day = strtolower((string) $day);

            if (! in_array($day, $valid, true)) {
                throw new \InvalidArgumentException("Dia inválido '{$day}': use ".implode(', ', $valid).'.');
            }

            $normalized[$day] = [];

            foreach (is_array($intervals) && ! isset($intervals['start']) ? $intervals : [$intervals] as $interval) {
                if (is_string($interval)) {
                    [$start, $end] = array_map('trim', explode('-', $interval, 2)) + [1 => ''];
                    $interval = ['start' => $start, 'end' => $end];
                }

                $normalized[$day][] = ['start' => (string) ($interval['start'] ?? ''), 'end' => (string) ($interval['end'] ?? '')];
            }
        }

        return $normalized;
    }

    /** Remove o fluxo (recusado se outro fluxo abre este). */
    public function delete(string $id): array
    {
        return $this->changed($this->result($this->http()->delete("/api/v1/chat/bot-flows/{$id}")));
    }

    private function changed(array $result): array
    {
        $this->forget('chat.bot-flows');

        return $result;
    }

    /**
     * Anexo de resposta do fluxo. Devolve o `media` ({ path, mime, file_name, … })
     * para pôr na ação: ['type' => 'reply', 'text' => 'Segue', 'media' => $media].
     */
    public function uploadMedia(string $path, ?string $fileName = null): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("Arquivo não encontrado ou sem leitura: {$path}");
        }

        return $this->result(NotifyHttp::upload($this->http())->attach('file', file_get_contents($path), $fileName ?? basename($path))->post('/api/v1/chat/bot-flows/media'));
    }

    /**
     * Simula o fluxo sem enviar nada: mande o `state` devolvido na chamada
     * anterior (null para começar) e o que o contato escreveu. $editingId: o
     * fluxo aberto no editor (um subfluxo que volta a ele usa esta definição,
     * não a salva).
     *
     * @return array{data: array{replies: array, state: array, action: ?string}}
     */
    public function simulate(array $definition, ?array $state, ?string $input, ?string $editingId = null): array
    {
        return $this->result($this->http()->post('/api/v1/chat/bot-flows/simulate', self::filled([
            'definition' => $definition,
            'state' => $state,
            'input' => $input,
            'editing_id' => $editingId,
        ])));
    }
}
