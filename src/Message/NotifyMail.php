<?php

namespace RiseTechApps\Notify\Message;

use RiseTechApps\Notify\Message\Concerns\HasSendOptions;
use RiseTechApps\Notify\Message\Concerns\HasTemplate;

class NotifyMail
{
    use HasSendOptions, HasTemplate;

    protected string $email = '';

    protected string $name = '';

    protected string $emailFrom = '';

    protected string $nameFrom = '';

    protected ?string $appName = null;

    protected ?string $subject = null;

    protected ?string $subjectMessage = null;

    protected ?string $theme = null;

    protected ?string $line = null;

    protected array $lineHeader = [];

    protected array $lineFooter = [];

    /** @var string|array<int, string>|null */
    protected string|array|null $signature = null;

    protected array $action = [];

    protected array $attach = [];

    protected array $attachments = [];

    protected array $inline = [];

    protected string|array|null $replyTo = null;

    protected array $cc = [];

    protected array $bcc = [];

    protected ?string $preheader = null;

    protected array $headers = [];

    protected ?string $htmlRaw = null;

    /** @var array<int, EmailTable> */
    protected array $tables = [];

    protected array $lists = [];

    protected array $tags = [];

    protected ?string $configId = null;

    protected ?string $webhookUrl = null;

    public function to(string $email, string $name = ''): static
    {
        if (blank($this->email)) {
            $this->email = $email;
        }

        if (blank($this->name)) {
            $this->name = $name;
        }

        return $this;
    }

    public function from(string $email, string $name = ''): static
    {
        if (blank($this->emailFrom)) {
            $this->emailFrom = $email;
        }

        if (blank($this->nameFrom)) {
            $this->nameFrom = $name;
        }

        return $this;
    }

    public function appName(string $appName): static
    {
        $this->appName = $appName;

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /** Assunto detalhado; tem prioridade sobre subject no servidor (máx 1000). */
    public function subjectMessage(string $subjectMessage): static
    {
        $this->subjectMessage = $subjectMessage;

        return $this;
    }

    public function theme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    /** Parágrafo principal do corpo (máx 1000). */
    public function line(string $line): static
    {
        $this->line = $line;

        return $this;
    }

    public function lineHeader(string $line): static
    {
        $this->lineHeader[] = $line;

        return $this;
    }

    public function lineFooter(string $line): static
    {
        $this->lineFooter[] = $line;

        return $this;
    }

    /** Botão CTA. A url é obrigatória quando a action está presente. */
    public function action(string $url, string $text): static
    {
        $this->action = ['url' => $url, 'text' => $text];

        return $this;
    }

    /** Anexos por URL. */
    public function attachFromUrl(array|string $attach): static
    {
        $this->attach = array_merge($this->attach, (array) $attach);

        return $this;
    }

    /**
     * @param  array{headers?: array<int, string>, rows?: array<int, array<int|string, mixed>>}|EmailTable  $table
     */
    /**
     * Anexo por URL com o nome do arquivo (o servidor baixa). Até 10 entre
     * attachFromUrl() e attachUrl().
     */
    public function attachUrl(string $url, ?string $name = null): static
    {
        $this->attach[] = array_filter(['url' => $url, 'name' => $name], fn ($v) => $v !== null);

        return $this;
    }

    /**
     * Anexo no corpo da requisição, sem URL pública: conteúdo cru (o pacote
     * converte para base64). Até 5 MB cada e 10 MB somados com o inline().
     */
    public function attachData(string $content, string $name, ?string $mime = null): static
    {
        $this->attachments[] = array_filter(['name' => $name, 'content' => base64_encode($content), 'mime' => $mime], fn ($v) => $v !== null);

        return $this;
    }

    /** Anexo a partir de um arquivo local. */
    public function attachFile(string $path, ?string $name = null, ?string $mime = null): static
    {
        return $this->attachData((string) file_get_contents($path), $name ?? basename($path), $mime ?? (mime_content_type($path) ?: null));
    }

    /**
     * Imagem embutida, referenciada no HTML como cid:{$cid}. Informe a URL ou
     * o conteúdo cru (o pacote converte para base64).
     */
    public function inline(string $cid, ?string $url = null, ?string $content = null, ?string $mime = null): static
    {
        $this->inline[] = array_filter([
            'cid' => $cid,
            'url' => $url,
            'content' => $content !== null ? base64_encode($content) : null,
            'mime' => $mime,
        ], fn ($v) => $v !== null);

        return $this;
    }

    public function replyTo(string $email, ?string $name = null): static
    {
        $this->replyTo = $name === null ? $email : ['email' => $email, 'name' => $name];

        return $this;
    }

    /** Cópias (até 10). Cada cópia conta 1 na cota. */
    public function cc(string|array $emails): static
    {
        $this->cc = array_values(array_unique(array_merge($this->cc, (array) $emails)));

        return $this;
    }

    /** Cópias ocultas (até 10). Cada cópia conta 1 na cota. */
    public function bcc(string|array $emails): static
    {
        $this->bcc = array_values(array_unique(array_merge($this->bcc, (array) $emails)));

        return $this;
    }

    /** Texto de prévia, mostrado ao lado do assunto na caixa de entrada. */
    public function preheader(string $preheader): static
    {
        $this->preheader = $preheader;

        return $this;
    }

    /** Header extra; só X-… (até 10). */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** HTML pronto no lugar do tema (até 256 KB). */
    public function html(string $html): static
    {
        $this->htmlRaw = $html;

        return $this;
    }

    public function addTable(EmailTable|array $table): static
    {
        if (! $table instanceof EmailTable) {
            $table = $this->resolveTableFromArray($table);
        }

        $this->tables[] = $table;

        return $this;
    }

    /**
     * @param  array<int, EmailTable|array{headers?: array<int, string>, rows?: array<int, array<int|string, mixed>>}>  $tables
     */
    public function tables(array $tables): static
    {
        $this->tables = [];

        foreach ($tables as $table) {
            $this->addTable($table);
        }

        return $this;
    }

    /**
     * @param  callable(EmailTable): void  $callback
     */
    public function table(callable $callback): static
    {
        $table = EmailTable::make();

        $callback($table);

        $this->tables[] = $table;

        return $this;
    }

    /** type: ordered | unordered */
    public function addList(string $type, array $items): static
    {
        $this->lists[] = ['type' => $type, 'items' => $items];

        return $this;
    }

    public function lists(array $lists): static
    {
        $this->lists = $lists;

        return $this;
    }

    /** Assinatura — uma linha (acumula) ou o array completo. */
    public function setSignature(string $signature): static
    {
        if (! is_array($this->signature)) {
            $this->signature = [];
        }

        $this->signature[] = $signature;

        return $this;
    }

    public function signature(string|array $signature): static
    {
        $this->signature = $signature;

        return $this;
    }

    /** Tag(s) para filtrar histórico — acumula e mescla com as tags do config. */
    public function tag(string|array $tag): static
    {
        $this->tags = array_values(array_unique(array_merge($this->tags, (array) $tag)));

        return $this;
    }

    /** UUID ou label da credencial (multi-tenant). Sobrescreve a default do config. */
    public function configId(string $configId): static
    {
        $this->configId = $configId;

        return $this;
    }

    public function webhookUrl(string $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    public function toArray(): array
    {
        $tags = array_values(array_unique(array_merge(
            (array) config('notify.mail.tags', []),
            $this->tags,
        )));

        if (blank($this->name)) {
            $this->name = $this->email;
        }

        return array_filter([
            'email' => $this->email,
            'name' => $this->name,
            'email_from' => $this->emailFrom ?: config('notify.mail.from.address'),
            'name_from' => $this->nameFrom ?: config('notify.mail.from.name'),
            'app_name' => $this->appName ?: config('notify.mail.app_name') ?: config('app.name'),

            'subject' => $this->subject,
            'subject_message' => $this->subjectMessage,
            'theme' => $this->theme ?: config('notify.mail.theme', 'default'),

            'line' => $this->line,
            'line_header' => $this->lineHeader ?: null,
            'line_footer' => $this->lineFooter ?: null,
            'signature' => $this->signature,
            'action' => $this->action ?: null,
            'attach' => $this->attach ?: null,
            'attachments' => $this->attachments ?: null,
            'inline' => $this->inline ?: null,
            'reply_to' => $this->replyTo,
            'cc' => $this->cc ?: null,
            'bcc' => $this->bcc ?: null,
            'preheader' => $this->preheader,
            'headers' => $this->headers ?: null,
            'html_raw' => $this->htmlRaw,
            'tables' => $this->tables ? array_map(fn ($t) => $t->toArray(), $this->tables) : null,
            'lists' => $this->lists ?: null,

            'tag' => $tags ?: null,
            'config_id' => $this->configId ?: config('notify.mail.config_id'),
            'webhook_url' => $this->webhookUrl,
        ], fn ($value) => ! is_null($value));
    }

    private function resolveTableFromArray(array $table): EmailTable
    {
        $emailTable = EmailTable::make();

        if (isset($table['headers']) && is_array($table['headers'])) {
            $emailTable->headers($table['headers']);
        }

        if (isset($table['rows']) && is_array($table['rows'])) {
            $emailTable->rows($table['rows']);
        }

        return $emailTable;
    }
}
