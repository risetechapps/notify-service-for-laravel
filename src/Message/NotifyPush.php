<?php

namespace RiseTechApps\Notify\Message;

/**
 * Mensagem de push (FCM) enviada ao servidor via /api/v1/send/push.
 *
 * Cobre todo o contrato do servidor: alvo (token/topic/condition), conteúdo,
 * comportamento (sound/badge/priority/ttl/etc.), push silencioso (data-only)
 * e overrides crus por plataforma (android/apns).
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Exemplos:
 * ──────────────────────────────────────────────────────────────────────────────
 *
 *   // Token único, simples
 *   (new NotifyPush)->token($fcm)->title('Olá')->body('Mensagem');
 *
 *   // Multicast com imagem, prioridade e deep link
 *   (new NotifyPush)
 *       ->token([$token1, $token2])
 *       ->title('Pedido a caminho 🚚')
 *       ->body('Seu pedido #123 saiu para entrega.')
 *       ->image('https://cdn.site.com/banner.jpg')
 *       ->channelId('pedidos')
 *       ->priority('high')
 *       ->ttl(600)
 *       ->collapseKey('pedido-123')
 *       ->link('https://site.com/pedidos/123')
 *       ->data(['route' => '/pedidos/123']);
 *
 *   // Som crítico no iOS (toca no modo silencioso)
 *   (new NotifyPush)
 *       ->token($fcm)
 *       ->title('Alerta de segurança')
 *       ->body('Login suspeito detectado.')
 *       ->criticalSound('alarme.caf', 1.0)
 *       ->interruptionLevel('critical')
 *       ->priority('high');
 *
 *   // Push silencioso / data-only (background sync)
 *   (new NotifyPush)->token($fcm)->silent()->data(['action' => 'sync']);
 *
 *   // Por tópico / condition
 *   (new NotifyPush)->topic('promocoes')->title('Black Friday')->body('50% OFF');
 *   (new NotifyPush)->condition("'esportes' in topics && 'flamengo' in topics")
 *       ->title('Gol do Mengão!')->body('Flamengo 1 x 0');
 *
 *   // Override bruto por plataforma
 *   (new NotifyPush)
 *       ->token($fcm)->title('Custom')->body('Avançado')
 *       ->android(['notification' => ['color' => '#E53935', 'notification_priority' => 'PRIORITY_MAX']])
 *       ->apns(['payload' => ['aps' => ['thread-id' => 'grupo-pedidos']]]);
 */
class NotifyPush
{
    // ── Alvo ────────────────────────────────────────────────────────────────────
    public string|array $token = '';
    protected ?string $topic = null;
    protected ?string $condition = null;

    // ── Conteúdo ──────────────────────────────────────────────────────────────
    protected ?string $title = null;
    protected ?string $body = null;
    protected ?string $image = null;
    protected ?string $icon = null;
    protected array $data = [];

    // ── Comportamento ─────────────────────────────────────────────────────────
    protected string|array|null $sound = null;
    protected ?string $channelId = null;
    protected ?int $badge = null;
    protected ?string $priority = null;
    protected int|string|null $ttl = null;
    protected ?string $collapseKey = null;
    protected ?bool $silent = null;

    // ── iOS / ações ─────────────────────────────────────────────────────────────
    protected ?string $clickAction = null;
    protected ?string $category = null;
    protected ?string $interruptionLevel = null;

    // ── Heads-up / agrupamento / interação ──────────────────────────────────────
    protected ?string $tag = null;
    protected ?string $notificationPriority = null;
    protected ?bool $requireInteraction = null;
    protected array $actions = [];
    protected array $lines = [];

    // ── Extras ──────────────────────────────────────────────────────────────────
    protected ?string $link = null;
    protected ?string $analyticsLabel = null;
    protected ?array $android = null;
    protected ?array $apns = null;
    protected ?array $webpush = null;

    // ── Servidor ──────────────────────────────────────────────────────────────
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;

    // ── Alvo ────────────────────────────────────────────────────────────────────

    /** Device token(s) FCM. Write-once: o primeiro valor não-vazio prevalece. */
    public function token(string|array $token): static
    {
        if (blank($this->token)) {
            $this->token = $token;
        }

        return $this;
    }

    /** Envia para todos os inscritos em um tópico FCM. */
    public function topic(string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    /** Combina tópicos via expressão, ex: "'a' in topics && 'b' in topics". */
    public function condition(string $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    // ── Conteúdo ──────────────────────────────────────────────────────────────

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /** URL da imagem grande da notificação (chave 'image' no servidor). */
    public function image(string $url): static
    {
        $this->image = $url;

        return $this;
    }

    /** Alias de image(). Mantido por compatibilidade. */
    public function imageUrl(string $url): static
    {
        return $this->image($url);
    }

    /** URL do ícone pequeno da notificação. */
    public function icon(string $url): static
    {
        $this->icon = $url;

        return $this;
    }

    /** Payload de dados (todos os valores devem ser strings — regra do FCM). */
    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    // ── Comportamento ─────────────────────────────────────────────────────────

    /**
     * Som da notificação. Aceita string (nome do arquivo) ou objeto
     * ['name' => '...', 'volume' => 1.0] para som crítico no iOS.
     */
    public function sound(string|array $sound): static
    {
        $this->sound = $sound;

        return $this;
    }

    /**
     * Som crítico no iOS — toca mesmo no modo silencioso.
     * Exige permissão de "critical alerts" no app. Lembre de interruptionLevel('critical').
     */
    public function criticalSound(string $name, float $volume = 1.0): static
    {
        $this->sound = ['name' => $name, 'volume' => $volume];

        return $this;
    }

    /** Android notification channel id. */
    public function channelId(string $channelId): static
    {
        $this->channelId = $channelId;

        return $this;
    }

    /** Número no badge do ícone (0 limpa o badge). */
    public function badge(int $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /** Prioridade de entrega: 'high' | 'normal'. */
    public function priority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /** Time-to-live antes da mensagem expirar. Segundos (int) ou string ("3600s"). */
    public function ttl(int|string $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    /** Agrupa/substitui notificações com a mesma chave. */
    public function collapseKey(string $key): static
    {
        $this->collapseKey = $key;

        return $this;
    }

    /** Push silencioso / data-only (background sync, sem alerta visível). */
    public function silent(bool $silent = true): static
    {
        $this->silent = $silent;

        return $this;
    }

    // ── iOS / ações ─────────────────────────────────────────────────────────────

    /** Ação ao tocar na notificação (Android intent action). */
    public function clickAction(string $action): static
    {
        $this->clickAction = $action;

        return $this;
    }

    /** Categoria de action buttons no iOS. */
    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    /** Nível de interrupção no iOS: passive | active | time-sensitive | critical. */
    public function interruptionLevel(string $level): static
    {
        $this->interruptionLevel = $level;

        return $this;
    }

    // ── Heads-up / agrupamento / interação ──────────────────────────────────────

    /**
     * Tag de agrupamento/substituição no device (Android+web) e etiqueta no histórico.
     * No push é uma STRING única (regra do servidor) — não aceita array.
     * Sobrescreve a default de config('notify.push.tag').
     */
    public function tag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    /** Heads-up no Android: PRIORITY_HIGH | PRIORITY_MAX | PRIORITY_DEFAULT ... */
    public function notificationPriority(string $priority): static
    {
        $this->notificationPriority = $priority;

        return $this;
    }

    /** Fixa a notificação até o usuário interagir (web push). */
    public function requireInteraction(bool $require = true): static
    {
        $this->requireInteraction = $require;

        return $this;
    }

    /**
     * Adiciona um botão de ação (máx 3). Nativo no web, via data no Android,
     * via category no iOS. Cada chamada acrescenta um botão.
     */
    public function action(string $title, string $id, ?string $icon = null): static
    {
        $this->actions[] = array_filter([
            'id'    => $id,
            'title' => $title,
            'icon'  => $icon,
        ], fn ($value) => !is_null($value));

        return $this;
    }

    /** Define todos os botões de ação de uma vez (substitui os anteriores). */
    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    /** Adiciona um item de lista (InboxStyle via data, máx 10). Acumula. */
    public function line(string $line): static
    {
        $this->lines[] = $line;

        return $this;
    }

    /** Define todos os itens de lista de uma vez (substitui os anteriores). */
    public function lines(array $lines): static
    {
        $this->lines = array_values($lines);

        return $this;
    }

    // ── Extras ──────────────────────────────────────────────────────────────────

    /** Deep link / URL aberta ao tocar na notificação. */
    public function link(string $url): static
    {
        $this->link = $url;

        return $this;
    }

    /** Rótulo de analytics no Firebase. */
    public function analyticsLabel(string $label): static
    {
        $this->analyticsLabel = $label;

        return $this;
    }

    /** Override bruto do bloco 'android' do FCM (poder total). */
    public function android(array $config): static
    {
        $this->android = $config;

        return $this;
    }

    /** Override bruto do bloco 'apns' do FCM (poder total). */
    public function apns(array $config): static
    {
        $this->apns = $config;

        return $this;
    }

    /** Override bruto do bloco 'webpush' do FCM (poder total). */
    public function webpush(array $config): static
    {
        $this->webpush = $config;

        return $this;
    }

    // ── Servidor ──────────────────────────────────────────────────────────────

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

    public function toArray(): array
    {
        return array_filter([
            // Alvo
            'token'              => $this->token ?: null,
            'topic'              => $this->topic,
            'condition'          => $this->condition,

            // Conteúdo
            'title'              => $this->title,
            'body'               => $this->body,
            'image'              => $this->image,
            'icon'               => $this->icon,
            'data'               => $this->data ?: null,

            // Comportamento
            'sound'              => $this->sound,
            'channel_id'         => $this->channelId,
            'badge'              => $this->badge,
            'priority'           => $this->priority,
            'ttl'                => $this->ttl,
            'collapse_key'       => $this->collapseKey,
            'silent'             => $this->silent ?: null,

            // iOS / ações
            'click_action'       => $this->clickAction,
            'category'           => $this->category,
            'interruption_level' => $this->interruptionLevel,

            // Heads-up / agrupamento / interação
            'tag'                   => $this->tag ?: config('notify.push.tag'),
            'notification_priority' => $this->notificationPriority,
            'require_interaction'   => $this->requireInteraction ?: null,
            'actions'               => $this->actions ?: null,
            'lines'                 => $this->lines ?: null,

            // Extras
            'link'               => $this->link,
            'analytics_label'    => $this->analyticsLabel,
            'android'            => $this->android,
            'apns'               => $this->apns,
            'webpush'            => $this->webpush,

            // Servidor
            'config_id'          => $this->configId ?: config('notify.push.config_id'),
            'webhook_url'        => $this->webhookUrl,
        ], fn ($value) => !is_null($value));
    }
}
