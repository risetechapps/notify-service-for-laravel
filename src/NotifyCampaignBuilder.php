<?php

namespace RiseTechApps\Notify;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Message\EmailTable;

/**
 * Fluent builder para campanhas de SMS e Email.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SMS — array direto
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo Junho')
 *       ->content('Olá {name}, sua oferta: https://loja.com')   // SMS usa {chave} simples
 *       ->tag(['promo', 'junho'])
 *       ->contacts([
 *           ['phone' => '5511999887766', 'name' => 'João'],
 *           ['phone' => '5521988776655', 'name' => 'Maria', 'extra_data' => ['cupom' => 'JUN10']],
 *       ])
 *       ->webhookUrl(route('notify.webhook.campaign'))
 *       ->send();
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SMS — via query Eloquent (processa em chunks, não carrega tudo em memória)
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo Junho')
 *       ->content('Olá {name}, seu cupom é {cupom}!')
 *       ->fromQuery(
 *           User::where('active', true),       // Builder
 *           contactColumn: 'phone',            // coluna do telefone no banco
 *           nameColumn:    'name',             // coluna do nome (opcional)
 *           extraColumns:  ['cupom' => 'coupon_code'], // viram extra_data (placeholders)
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
    protected string|array|null $tag = null;
    protected ?string $listId = null;
    protected ?string $templateId = null;
    protected ?array $sendWindow = null;
    protected ?array $abTest = null;
    protected ?string $idempotencyKey = null;
    protected bool $testMode = false;

    // Contatos resolvidos (array final)
    protected array $contacts = [];

    // Fonte lazy (query) — processada só no send()
    protected ?Builder $querySource = null;
    protected string $queryContactColumn = 'email';
    protected ?string $queryNameColumn = null;
    protected array $queryExtraColumns = [];

    // Template SMS
    protected string $smsContent = '';

    // Template WhatsApp (o texto é o mesmo content() do SMS)
    protected ?string $mediaUrl = null;
    protected ?string $mediaPath = null;
    protected ?string $mediaLocalFile = null;
    protected ?string $mediaType = null;
    protected ?string $mediaFileName = null;
    protected bool $optOutFooter = true;
    protected ?string $optOutText = null;

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

    /**
     * Campanha pelo número de WhatsApp da conta. O ritmo é o da proteção do
     * número (sem ratePerMinute): quem já conversou sai primeiro e os contatos
     * novos se distribuem pelos dias; a resposta traz `estimate` (dias).
     * O texto aceita {name}, campos extras e variações {Olá|Oi}.
     */
    public static function whatsapp(): static
    {
        $instance = new static();
        $instance->channel = 'whatsapp';
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

    /** Lista de contatos salva no servidor, no lugar de contacts()/fromQuery(). */
    public function listId(string $listId): static
    {
        $this->listId = $listId;
        return $this;
    }

    /**
     * Modelo salvo (id ou nome). O conteúdo vem dele; o que você definir no
     * builder tem prioridade.
     */
    public function template(string $idOrName): static
    {
        $this->templateId = $idOrName;
        return $this;
    }

    /**
     * Horário permitido: fora dele a campanha pausa e retoma na abertura
     * seguinte. $days: 1 (segunda) a 7 (domingo).
     */
    public function sendWindow(string $start, string $end, array $days = [1, 2, 3, 4, 5, 6, 7], ?string $timezone = null): static
    {
        $this->sendWindow = array_filter([
            'start' => $start,
            'end' => $end,
            'days' => array_values($days),
            'timezone' => $timezone ?? config('app.timezone'),
        ], fn ($v) => $v !== null);
        return $this;
    }

    /**
     * Teste A/B de assunto (e-mail): $samplePercent% recebe A ou B; depois de
     * $waitMinutes, o resto recebe o assunto com mais aberturas.
     */
    public function abTest(string $subjectB, int $samplePercent = 20, int $waitMinutes = 240): static
    {
        $this->abTest = ['subject_b' => $subjectB, 'sample_percent' => $samplePercent, 'wait_minutes' => $waitMinutes];
        return $this;
    }

    /** Repetir a criação com a mesma chave (em 24 h) devolve a resposta original, sem duplicar. */
    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    /** Modo de teste: a campanha roda, mas nada sai pelo provedor e a cota não é consumida. */
    public function test(bool $test = true): static
    {
        $this->testMode = $test;
        return $this;
    }

    /**
     * Tag(s) propagada(s) para cada notificação gerada pela campanha.
     * Aceita string única ou array de strings.
     */
    public function tag(string|array $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    // ── Fontes de contatos ────────────────────────────────────────────────────

    /**
     * Chave do contato que o servidor espera: 'email' no e-mail, 'phone' no
     * SMS e no WhatsApp.
     */
    protected function contactKey(): string
    {
        return $this->channel === 'email' ? 'email' : 'phone';
    }

    /**
     * Contatos como array direto.
     *
     * SMS:   [['phone' => '5511999887766', 'name' => 'João'], ...]
     * Email: [['email' => 'joao@ex.com',   'name' => 'João'], ...]
     */
    public function contacts(array $contacts): static
    {
        // Aceita um único contato achatado: ['phone' => '...', 'name' => '...']
        // e normaliza para lista, evitando que array_map() itere sobre os valores.
        if ($contacts !== [] && !array_is_list($contacts)) {
            $contacts = [$contacts];
        }

        $this->contacts = $contacts;
        $this->querySource = null;
        return $this;
    }

    /**
     * Contatos via query Eloquent — processada em chunks no send().
     * Não carrega todos os registros em memória.
     *
     * @param Builder $query Query já montada (sem ->get())
     * @param string $contactColumn Coluna com telefone (sms) ou email (email)
     * @param string|null $nameColumn Coluna com o nome (opcional)
     * @param array $extraColumns Colunas extras viram `extra_data` (placeholders).
     *              Lista (['cupom']) usa o mesmo nome; mapa (['cupom' => 'coupon_code'])
     *              renomeia a chave do placeholder para o nome da coluna de origem.
     */
    public function fromQuery(Builder $query, string $contactColumn, ?string $nameColumn = null, array $extraColumns = []): static
    {
        $this->querySource = $query;
        $this->queryContactColumn = $contactColumn;
        $this->queryNameColumn = $nameColumn;
        $this->queryExtraColumns = $extraColumns;
        $this->contacts = [];
        return $this;
    }

    /**
     * Contatos via Collection.
     *
     * @param Collection $collection
     * @param string $contactColumn Atributo com telefone (sms) ou email (email)
     * @param string|null $nameColumn Atributo com o nome (opcional)
     * @param array $extraColumns Atributos extras viram `extra_data` (placeholders).
     *              Lista (['cupom']) usa o mesmo nome; mapa (['cupom' => 'coupon_code'])
     *              renomeia a chave do placeholder para o nome do atributo de origem.
     */
    public function fromCollection(Collection $collection, string $contactColumn, ?string $nameColumn = null, array $extraColumns = []): static
    {
        $key = $this->contactKey();

        $this->contacts = $collection->map(function ($item) use ($contactColumn, $nameColumn, $extraColumns, $key) {
            $row = [$key => data_get($item, $contactColumn)];

            if ($nameColumn) {
                $row['name'] = data_get($item, $nameColumn);
            }

            $extra = $this->extractExtraData($item, $extraColumns);

            if ($extra !== []) {
                $row['extra_data'] = $extra;
            }

            return $row;
        })->filter(fn($r) => !empty($r[$key]))->values()->all();

        $this->querySource = null;
        return $this;
    }

    /**
     * Extrai colunas/atributos extras de um registro para o array `extra_data`.
     * Chave numérica = usa o nome da coluna como chave do placeholder; chave string
     * = renomeia o placeholder. Valores nulos são ignorados.
     */
    protected function extractExtraData(mixed $item, array $extraColumns): array
    {
        $extra = [];

        foreach ($extraColumns as $placeholder => $column) {
            $outKey = is_int($placeholder) ? $column : $placeholder;
            $value = data_get($item, $column);

            if (!is_null($value)) {
                $extra[$outKey] = $value;
            }
        }

        return $extra;
    }

    // ── Campos de SMS ─────────────────────────────────────────────────────────

    public function content(string $content): static
    {
        $this->smsContent = $content;
        return $this;
    }

    public function from(string $from, string $nameFrom = ''): static
    {
        // Só email usa remetente; o servidor não aceita mais `from` em SMS.
        $this->emailFrom = $from;
        $this->emailNameFrom = $nameFrom;
        return $this;
    }

    // ── Campos de WhatsApp ────────────────────────────────────────────────────

    /**
     * Mídia por URL pública. $type: image | video | document | audio. O texto
     * de content() vira a legenda.
     */
    public function media(string $url, string $type, ?string $fileName = null): static
    {
        $this->mediaUrl = $url;
        $this->mediaType = $type;
        $this->mediaFileName = $fileName;
        $this->mediaLocalFile = null;
        $this->mediaPath = null;
        return $this;
    }

    /**
     * Mídia de um arquivo local: enviada ao servidor no send() (vídeo fora de
     * MP4 já é convertido lá). O tipo vem do arquivo.
     */
    public function mediaFile(string $path, ?string $fileName = null): static
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Arquivo não encontrado ou sem leitura: {$path}");
        }

        $this->mediaLocalFile = $path;
        $this->mediaFileName = $fileName ?? basename($path);
        $this->mediaUrl = null;
        return $this;
    }

    /**
     * Rodapé de descadastro ("Para não receber mais mensagens, responda SAIR.").
     * Ligado por padrão; $text troca o texto.
     */
    public function optOutFooter(bool $enabled = true, ?string $text = null): static
    {
        $this->optOutFooter = $enabled;
        $this->optOutText = $text;
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

    /**
     * Dispara a campanha ao servidor. Não persiste nada localmente.
     *
     * @return array Resposta crua do servidor (campaign_id, status, ...) ou [] em falha.
     */
    public function send(): array
    {
        $this->validate();

        $contacts   = $this->resolveContacts();
        $template   = $this->buildTemplate();
        $webhookUrl = $this->webhookUrl ?: config('notify.webhook');

        if (empty($contacts) && $this->listId === null) {
            throw new \InvalidArgumentException('At least one contact (or listId()) is required.');
        }

        return $this->dispatch($this->buildPayload($contacts, $template, $webhookUrl));
    }

    /**
     * Dispara uma campanha de email com template HTML cru.
     *
     * @return array Resposta crua do servidor ou [] em falha.
     */
    public function sendHtml(string $path): array
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("HTML template file not found or unreadable: {$path}");
        }

        $this->validate();

        $contacts   = $this->resolveContacts();
        $template   = $this->buildTemplateHtml($path);
        $webhookUrl = $this->webhookUrl ?: config('notify.webhook');

        if (empty($contacts) && $this->listId === null) {
            throw new \InvalidArgumentException('At least one contact (or listId()) is required.');
        }

        return $this->dispatch($this->buildPayload($contacts, $template, $webhookUrl));
    }

    protected function validate(): void
    {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Campaign name is required.');
        }

        // Com modelo salvo, o conteúdo pode vir dele.
        if ($this->templateId !== null) {
            return;
        }

        if ($this->channel === 'sms' && $this->smsContent === '') {
            throw new \InvalidArgumentException('SMS content is required.');
        }

        if ($this->channel === 'whatsapp' && $this->smsContent === '' && !$this->mediaUrl && !$this->mediaLocalFile) {
            throw new \InvalidArgumentException('WhatsApp content or media is required.');
        }

        if ($this->channel === 'email' && $this->emailSubject === '') {
            throw new \InvalidArgumentException('Email subject is required.');
        }
    }

    /**
     * POST do payload para o endpoint de campanha do servidor.
     */
    protected function dispatch(array $payload): array
    {
        try {
            $response = $this->http()->post(Notify::BASE_URL . "/api/v1/send/campaigns/{$this->endpoint()}", $payload);

            if ($response->failed()) {
                return $response->json() ?: ['error' => $response->body()];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            report($e);

            return ['error' => $e->getMessage()];
        }
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function endpoint(): string
    {
        return match ($this->channel) {
            'sms' => 'sms',
            'whatsapp' => 'whatsapp',
            default => 'mail',
        };
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(array_filter([
            'X-API-KEY' => config('notify.key'),
            'Idempotency-Key' => $this->idempotencyKey,
            'X-Notify-Test' => $this->testMode ? 'true' : null,
        ]))->acceptJson();
    }

    /** Envia o arquivo local da mídia (mediaFile) e guarda o path devolvido. */
    protected function uploadMedia(): void
    {
        if ($this->mediaLocalFile === null || $this->mediaPath !== null) {
            return;
        }

        $response = $this->http()
            ->attach('file', file_get_contents($this->mediaLocalFile), $this->mediaFileName ?? basename($this->mediaLocalFile))
            ->post(Notify::BASE_URL . '/api/v1/send/campaigns/whatsapp/media');

        if ($response->failed() || !$response->json('data.path')) {
            throw new \RuntimeException('Não foi possível enviar a mídia da campanha: ' . ($response->json('message') ?? $response->body()));
        }

        $this->mediaPath = $response->json('data.path');
        $this->mediaType = $response->json('data.media_type') ?? $response->json('data.type');
        $this->mediaFileName = $response->json('data.file_name') ?? $this->mediaFileName;
    }

    protected function resolveContacts(): array
    {
        if ($this->querySource !== null) {
            $contactKey = $this->contactKey();

            return $this->querySource
                ->lazy(500)
                ->filter(fn($row) => !empty(data_get($row, $this->queryContactColumn)))
                ->map(fn($row) => $this->buildContactFromRow($row, $contactKey))
                ->values()
                ->all();
        }

        return $this->contacts;
    }

    protected function buildContactFromRow(mixed $row, string $contactKey): array
    {
        $contact = [$contactKey => data_get($row, $this->queryContactColumn)];

        if ($this->queryNameColumn) {
            $contact['name'] = data_get($row, $this->queryNameColumn);
        }

        $extra = $this->extractExtraData($row, $this->queryExtraColumns);

        if ($extra !== []) {
            $contact['extra_data'] = $extra;
        }

        return $contact;
    }

    protected function buildTemplate(): array
    {
        if ($this->channel === 'whatsapp') {
            $this->uploadMedia();

            return array_filter([
                'content' => $this->smsContent !== '' ? $this->smsContent : null,
                'media_url' => $this->mediaUrl,
                'media_path' => $this->mediaPath,
                'media_type' => $this->mediaType,
                'file_name' => $this->mediaFileName,
                'opt_out_footer' => $this->optOutFooter,
                'opt_out_text' => $this->optOutText,
            ], fn($v) => !is_null($v));
        }

        if ($this->channel === 'sms') {
            return array_filter([
                'content' => $this->smsContent,
            ], fn($v) => $v !== '' && !is_null($v));
        }

        // Email
        return array_filter([
            'email_from' => $this->emailFrom ?: config('notify.mail.from.address'),
            'name_from' => $this->emailNameFrom ?: config('notify.mail.from.name'),
            'subject' => $this->emailSubject,
            'subject_message' => $this->emailSubjectMessage,
            'theme' => $this->emailTheme,
            'app_name' => config('notify.mail.app_name', config('app.name')),
            'line' => $this->emailLine,
            'line_header' => $this->emailLineHeader ?: null,
            'line_footer' => $this->emailLineFooter ?: null,
            'action' => $this->emailAction ?: null,
            'tables' => $this->emailTables ?: null,
            'lists' => $this->emailLists ?: null,
            'signature' => $this->emailSignature,
        ], fn($v) => !is_null($v));
    }

    protected function buildTemplateHtml(string $html): array
    {
        // Email
        return array_filter([
            'email_from' => $this->emailFrom ?: config('notify.mail.from.address'),
            'name_from' => $this->emailNameFrom ?: config('notify.mail.from.name'),
            'subject' => $this->emailSubject,
            'subject_message' => $this->emailSubjectMessage,
            'app_name' => config('notify.mail.app_name', config('app.name')),
            'html_raw' => file_get_contents($html),
        ], fn($v) => !is_null($v));
    }

    protected function buildPayload(array $contacts, array $template, ?string $webhookUrl): array
    {
        return array_filter([
            'name' => $this->name,
            'template' => $template,
            'template_id' => $this->templateId,
            // Lista salva no lugar dos contatos (o servidor não aceita os dois).
            'contacts' => $this->listId ? null : $contacts,
            'list_id' => $this->listId,
            'config_id' => $this->configId,
            'webhook_url' => $webhookUrl,
            // WhatsApp segue o ritmo do número, sem rate_per_minute.
            'rate_per_minute' => $this->channel !== 'whatsapp' && $this->ratePerMinute !== 60 ? $this->ratePerMinute : null,
            'scheduled_at' => $this->buildScheduledAt(),
            'send_window' => $this->sendWindow,
            'ab_test' => $this->channel === 'email' ? $this->abTest : null,
            'tag' => $this->tag,
        ], fn($v) => !is_null($v));
    }

    protected function buildScheduledAt(): ?string
    {
        if ($this->scheduledAt === null) {
            return null;
        }

        $timezone = config('app.timezone', 'UTC');

        return \Carbon\Carbon::parse($this->scheduledAt, $timezone)
            ->format('Y-m-d\TH:i:sP');
    }
}
