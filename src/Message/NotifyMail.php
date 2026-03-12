<?php

namespace RiseTechApps\Notify\Message;

class NotifyMail
{
    protected $notifiable;
    public string $email = "";
    public string $emailFrom = "";
    protected string $subject;
    protected array $content;
    protected array $attach = [];
    protected string $name = "";
    protected string $nameFrom = "";
    protected string $theme = 'default';
    protected ?string $line = null;
    protected array $lineHeader = [];
    protected array $lineFooter = [];
    protected array $action = [];
    /**
     * @var array<int, EmailTable>
     */
    protected array $tables = [];
    protected array $lists = [];
    protected ?string $subjectMessage = null;
    protected $signature = null;

    protected array $cc = [];
    protected array $bcc = [];

    protected ?string $webhookUrl = null;

    private function getEmail(): void
    {
        if (method_exists($this->notifiable, 'routeNotificationForEmail')) {
            $this->email = $this->notifiable->routeNotificationForEmail();
        }
    }

    private function getName(): void
    {
        if (method_exists($this->notifiable, 'routeNotificationForName')) {
            $this->name = $this->notifiable->routeNotificationForName();
        }
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function content(array $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function attachFromUrl(array|string $attach): static
    {
        $this->attach = array_merge($this->attach, (array) $attach);
        return $this;
    }

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

    public function theme(string $theme): static
    {
        $this->theme = $theme;
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

    public function line(string $line): static
    {
        $this->line = $line;
        return $this;
    }

    public function action(string $url, string $text): static
    {
        $this->action['url'] = $url;
        $this->action['text'] = $text;

        return $this;
    }

    /**
     * @param array{headers?: array<int, string>, rows?: array<int, array<int|string, mixed>>}|EmailTable $table
     */
    public function addTable(EmailTable|array $table): static
    {
        if (! $table instanceof EmailTable) {
            $table = $this->resolveTableFromArray($table);
        }

        $this->tables[] = $table;

        return $this;
    }

    /**
     * @param array<int, EmailTable|array{headers?: array<int, string>, rows?: array<int, array<int|string, mixed>>}> $tables
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
     * @param callable(EmailTable): void $callback
     */
    public function table(callable $callback): static
    {
        $table = EmailTable::make();

        $callback($table);

        $this->tables[] = $table;

        return $this;
    }

    public function addList(string $type, array $items): static
    {
        $this->lists[] = [
            'type' => $type,
            'items' => $items,
        ];

        return $this;
    }

    public function lists(array $lists): static
    {
        $this->lists = $lists;

        return $this;
    }

    public function subjectMessage(string $subjectMessage): static
    {
        $this->subjectMessage = $subjectMessage;
        return $this;
    }

    public function setSignature(string $signature): static
    {
        if (is_null($this->signature)) {
            $this->signature = [];
        }

        $this->signature[] = $signature;
        return $this;
    }


    public function toArray(): array
    {
        return array_filter([
            'email'           => $this->email,
            'name'            => $this->name,
            'email_from'      => blank($this->emailFrom) ? 'no-reply@risetech.com.br' : $this->emailFrom,
            'name_from'       => blank($this->nameFrom) ? 'RiseTech' : $this->nameFrom,
            'subject'         => $this->subject,
            'theme'           => $this->theme,
            'subject_message' => $this->subjectMessage ?? '',
            'app_name'        => config('notify.from_name', config('app.name')),

            // Novos campos de cópia
            'cc'              => count($this->cc) > 0 ? $this->cc : null,
            'bcc'             => count($this->bcc) > 0 ? $this->bcc : null,

            'attach'          => count($this->attach) > 0 ? $this->attach : null,
            'line'            => $this->line,
            'line_header'     => count($this->lineHeader) > 0 ? $this->lineHeader : null,
            'line_footer'     => count($this->lineFooter) > 0 ? $this->lineFooter : null,
            'action'          => count($this->action) > 0 ? $this->action : null,
            'tables'          => count($this->tables) > 0 ? array_map(fn($t) => $t->toArray(), $this->tables) : null,
            'lists'           => count($this->lists) > 0 ? $this->lists : null,
            'signature'       => $this->signature,
            'webhook_url'     => $this->webhookUrl,
        ], fn($value) => !is_null($value));
    }

    public function webhookUrl(string $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
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
