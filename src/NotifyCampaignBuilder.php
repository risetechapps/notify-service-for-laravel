<?php

namespace RiseTechApps\Notify;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RiseTechApps\Notify\Message\EmailTable;
use RiseTechApps\Notify\Models\NotifyCampaign as NotifyCampaignModel;
use RiseTechApps\Notify\Models\NotifyCampaignContact;
use RiseTechApps\Notify\Models\NotifyLog;

/**
 * Fluent builder para campanhas de SMS e Email.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SMS — array direto
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo Junho')
 *       ->content('Olá {{name}}, sua oferta: https://loja.com')
 *       ->contacts([
 *           ['phone' => '5511999887766', 'name' => 'João'],
 *           ['phone' => '5521988776655', 'name' => 'Maria'],
 *       ])
 *       ->webhookUrl(route('notify.webhook.campaign'))
 *       ->send();
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SMS — via query Eloquent (processa em chunks, não carrega tudo em memória)
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo Junho')
 *       ->content('Olá {{name}}, sua oferta chegou!')
 *       ->fromQuery(
 *           User::where('active', true),  // Builder
 *           contactColumn: 'phone',       // coluna do telefone no banco
 *           nameColumn:    'name',        // coluna do nome (opcional)
 *       )
 *       ->send();
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Email — via Collection
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::email()
 *       ->name('Black Friday 2026')
 *       ->subject('Ofertas imperdíveis!')
 *       ->line('Confira os descontos de até 70%.')
 *       ->action('https://loja.com/bf', 'Ver ofertas')
 *       ->from('ofertas@loja.com', 'Loja')
 *       ->fromCollection($users, contactColumn: 'email', nameColumn: 'name')
 *       ->scheduledAt('2026-11-29 08:00:00')
 *       ->send();
 */
class NotifyCampaignBuilder
{
    protected string $channel = 'sms';

    // Compartilhados entre SMS e Email
    protected string $name = '';
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;
    protected int $ratePerMinute = 60;
    protected ?string $scheduledAt = null;

    // Contatos resolvidos (array final)
    protected array $contacts = [];

    // Fonte lazy (query) — processada só no send()
    protected ?Builder $querySource = null;
    protected string $queryContactColumn = 'email';
    protected ?string $queryNameColumn = null;

    // Template SMS
    protected string $smsContent = '';
    protected string $smsFrom = '';

    // Template Email
    protected string $emailSubject = '';
    protected ?string $emailSubjectMessage = null;
    protected ?string $emailLine = null;
    protected array $emailLineHeader = [];
    protected array $emailLineFooter = [];
    protected array $emailAction = [];
    protected array $emailTables = [];
    protected array $emailLists = [];
    protected string $emailTheme = 'default';
    protected ?string $emailSignature = null;
    protected string $emailFrom = '';
    protected string $emailNameFrom = '';

    // ── Construtores estáticos ─────────────────────────────────────────────────

    public static function sms(): static
    {
        $instance = new static();
        $instance->channel = 'sms';
        return $instance;
    }

    public static function email(): static
    {
        $instance = new static();
        $instance->channel = 'email';
        return $instance;
    }

    // ── Campos compartilhados ─────────────────────────────────────────────────

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function configId(string $configId): static
    {
        $this->configId = $configId;
        return $this;
    }

    public function webhookUrl(string $url): static
    {
        $this->webhookUrl = $url;
        return $this;
    }

    public function ratePerMinute(int $rate): static
    {
        $this->ratePerMinute = $rate;
        return $this;
    }

    public function scheduledAt(string $datetime): static
    {
        $this->scheduledAt = $datetime;
        return $this;
    }

    // ── Fontes de contatos ────────────────────────────────────────────────────

    /**
     * Retorna a chave correta do contato conforme o canal: 'phone' (sms) ou 'email' (email).
     */
    protected function contactKey(): string
    {
        return $this->channel === 'sms' ? 'phone' : 'email';
    }

    /**
     * Contatos como array direto.
     *
     * SMS:   [['phone' => '5511999887766', 'name' => 'João'], ...]
     * Email: [['email' => 'joao@ex.com',   'name' => 'João'], ...]
     */
    public function contacts(array $contacts): static
    {
        $this->contacts = $contacts;
        $this->querySource = null;
        return $this;
    }

    /**
     * Contatos via query Eloquent — processada em chunks no send().
     * Não carrega todos os registros em memória.
     *
     * @param Builder     $query          Query já montada (sem ->get())
     * @param string      $contactColumn  Coluna com telefone (sms) ou email (email)
     * @param string|null $nameColumn     Coluna com o nome (opcional)
     */
    public function fromQuery(Builder $query, string $contactColumn, ?string $nameColumn = null): static
    {
        $this->querySource        = $query;
        $this->queryContactColumn = $contactColumn;
        $this->queryNameColumn    = $nameColumn;
        $this->contacts           = [];
        return $this;
    }

    /**
     * Contatos via Collection.
     *
     * @param Collection  $collection
     * @param string      $contactColumn  Atributo com telefone (sms) ou email (email)
     * @param string|null $nameColumn     Atributo com o nome (opcional)
     */
    public function fromCollection(Collection $collection, string $contactColumn, ?string $nameColumn = null): static
    {
        $key = $this->contactKey();

        $this->contacts = $collection->map(function ($item) use ($contactColumn, $nameColumn, $key) {
            $row = [$key => data_get($item, $contactColumn)];

            if ($nameColumn) {
                $row['name'] = data_get($item, $nameColumn);
            }

            return $row;
        })->filter(fn($r) => !empty($r[$key]))->values()->all();

        $this->querySource = null;
        return $this;
    }

    // ── Campos de SMS ─────────────────────────────────────────────────────────

    public function content(string $content): static
    {
        $this->smsContent = $content;
        return $this;
    }

    public function from(string $from, string $nameFrom = ''): static
    {
        // SMS usa só o primeiro argumento; email usa os dois
        $this->smsFrom       = $from;
        $this->emailFrom     = $from;
        $this->emailNameFrom = $nameFrom;
        return $this;
    }

    // ── Campos de Email ───────────────────────────────────────────────────────

    public function subject(string $subject): static
    {
        $this->emailSubject = $subject;
        return $this;
    }

    public function subjectMessage(string $msg): static
    {
        $this->emailSubjectMessage = $msg;
        return $this;
    }

    public function line(string $line): static
    {
        $this->emailLine = $line;
        return $this;
    }

    public function lineHeader(string $line): static
    {
        $this->emailLineHeader[] = $line;
        return $this;
    }

    public function lineFooter(string $line): static
    {
        $this->emailLineFooter[] = $line;
        return $this;
    }

    public function action(string $url, string $text): static
    {
        $this->emailAction = ['url' => $url, 'text' => $text];
        return $this;
    }

    public function addTable(EmailTable|array $table): static
    {
        $this->emailTables[] = $table instanceof EmailTable ? $table->toArray() : $table;
        return $this;
    }

    public function addList(string $type, array $items): static
    {
        $this->emailLists[] = ['type' => $type, 'items' => $items];
        return $this;
    }

    public function theme(string $theme): static
    {
        $this->emailTheme = $theme;
        return $this;
    }

    public function signature(string $signature): static
    {
        $this->emailSignature = $signature;
        return $this;
    }

    // ── Envio ─────────────────────────────────────────────────────────────────

    public function send(): NotifyCampaignModel
    {
        $contacts   = $this->resolveContacts();
        $template   = $this->buildTemplate();
        $webhookUrl = $this->webhookUrl ?: config('notify.webhook');

        // Salva a campanha localmente
        $campaign = NotifyCampaignModel::create([
            'notifiable_type' => 'system',
            'notifiable_id'   => '0',
            'channel'         => $this->channel,
            'name'            => $this->name,
            'status'          => 'pending',
            'template'        => $template,
            'config_id'       => $this->configId,
            'webhook_url'     => $webhookUrl,
            'rate_per_minute' => $this->ratePerMinute,
            'scheduled_at'    => $this->scheduledAt,
            'total_contacts'  => count($contacts),
        ]);

        // Salva os contatos em lotes de 500
        $contactKey = $this->contactKey();
        $rows = array_map(fn($c) => [
            'id'                 => Str::uuid()->toString(),
            'notify_campaign_id' => $campaign->id,
            'contact'            => $c[$contactKey] ?? null,
            'name'               => $c['name']      ?? null,
            'extra_data'         => isset($c['extra_data']) ? json_encode($c['extra_data']) : null,
            'status'             => 'pending',
            'created_at'         => now(),
            'updated_at'         => now(),
        ], $contacts);

        foreach (array_chunk($rows, 500) as $chunk) {
            NotifyCampaignContact::insert($chunk);
        }

        // Log do disparo
        $log = NotifyLog::create([
            'notifiable_type'    => 'system',
            'notifiable_id'      => '0',
            'channel'            => $this->channel,
            'status'             => 'sending',
            'payload'            => $this->buildPayload($contacts, $template, $webhookUrl),
            'notify_campaign_id' => $campaign->id,
        ]);

        // Envia ao servidor
        try {
            $endpoint = $this->channel === 'sms' ? 'sms' : 'mail';

            $response = Http::withHeaders(['X-API-KEY' => config('notify.key')])
                ->acceptJson()
                ->post("https://notifykit.app.br/api/v1/campaigns/{$endpoint}",
                    $this->buildPayload($contacts, $template, $webhookUrl)
                );

            if ($response->failed()) {
                throw new \Exception('Error creating campaign: ' . $response->body());
            }

            $responseJson = $response->json();

            // O servidor retorna 'status': true (boolean de aceite), não uma string de estado.
            // O estado real da campanha vem via webhook depois.
            $campaign->update([
                'server_campaign_id' => $responseJson['campaign_id']    ?? null,
                'status'             => 'processing',
                'total_contacts'     => $responseJson['total_contacts'] ?? $campaign->total_contacts,
            ]);

            $log->markAsSent($responseJson['campaign_id'] ?? '', $responseJson);

        } catch (\Exception $e) {
            $campaign->update(['status' => 'failed']);
            $log->markAsFailed($e->getMessage());
            report($e);
        }

        return $campaign;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function resolveContacts(): array
    {
        // Se veio de fromQuery(), processa agora em chunks
        if ($this->querySource !== null) {
            $contacts   = [];
            $contactKey = $this->contactKey();

            $this->querySource->chunk(500, function ($rows) use (&$contacts, $contactKey) {
                foreach ($rows as $row) {
                    $value = data_get($row, $this->queryContactColumn);

                    if (empty($value)) {
                        continue;
                    }

                    $contact = [$contactKey => $value];

                    if ($this->queryNameColumn) {
                        $contact['name'] = data_get($row, $this->queryNameColumn);
                    }

                    $contacts[] = $contact;
                }
            });

            return $contacts;
        }

        return $this->contacts;
    }

    protected function buildTemplate(): array
    {
        if ($this->channel === 'sms') {
            return array_filter([
                'content' => $this->smsContent,
                'from'    => $this->smsFrom ?: config('app.name'),
            ], fn($v) => $v !== '' && !is_null($v));
        }

        // Email
        return array_filter([
            'email_from'      => $this->emailFrom     ?: 'no-reply@risetech.com.br',
            'name_from'       => $this->emailNameFrom ?: 'RiseTech',
            'subject'         => $this->emailSubject,
            'subject_message' => $this->emailSubjectMessage,
            'theme'           => $this->emailTheme,
            'app_name'        => config('notify.from_name', config('app.name')),
            'line'            => $this->emailLine,
            'line_header'     => $this->emailLineHeader ?: null,
            'line_footer'     => $this->emailLineFooter ?: null,
            'action'          => $this->emailAction    ?: null,
            'tables'          => $this->emailTables    ?: null,
            'lists'           => $this->emailLists     ?: null,
            'signature'       => $this->emailSignature,
        ], fn($v) => !is_null($v));
    }

    protected function buildPayload(array $contacts, array $template, string $webhookUrl): array
    {
        return array_filter([
            'name'            => $this->name,
            'template'        => $template,
            'contacts'        => $contacts,
            'config_id'       => $this->configId,
            'webhook_url'     => $webhookUrl,
            'rate_per_minute' => $this->ratePerMinute !== 60 ? $this->ratePerMinute : null,
            'scheduled_at'    => $this->scheduledAt,
        ], fn($v) => !is_null($v));
    }
}
