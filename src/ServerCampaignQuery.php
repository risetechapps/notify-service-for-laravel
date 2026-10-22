<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Recurso de campanhas no servidor — listagem, detalhe e cancelamento.
 * O canal define o endpoint: /api/v1/send/campaigns/{sms|mail|whatsapp}.
 *
 *   // Listar
 *   NotifyQuery::server()->campaigns('sms')->status('processing')->get();
 *
 *   // Detalhe + progresso (progress_percent, pending_count)
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->get();
 *
 *   // Cancelar (pending/paused/processing)
 *   NotifyQuery::server()->campaigns('sms', 'campaign-uuid')->cancel();
 *   NotifyQuery::server()->campaigns('sms')->cancel('campaign-uuid');
 *
 *   // Contatos da campanha (status/erro por destinatário) — exige o id
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->contacts()->status('failed')->get();
 */
class ServerCampaignQuery
{
    protected array $params = [];

    /** Segmento do endpoint por canal: sms | mail | whatsapp. */
    protected string $segment;

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        string $channel,
        protected ?string $id = null,
    ) {
        $this->segment = match ($channel) {
            'sms' => 'sms',
            'whatsapp' => 'whatsapp',
            default => 'mail',
        };
    }

    /** Filtra por tag(s) — contém qualquer uma das informadas. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;

        return $this;
    }

    /** status: pending | processing | paused | completed | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;

        return $this;
    }

    public function from(string $date): static
    {
        $this->params['from'] = $date;

        return $this;
    }

    public function to(string $date): static
    {
        $this->params['to'] = $date;

        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;

        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;

        return $this;
    }

    /**
     * Com id → detalhe + progresso de uma campanha.
     * Sem id  → listagem paginada (filtrada por status/tag/datas).
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/send/campaigns/{$this->segment}/{$this->id}")->json() ?? [];
        }

        return $this->http()->get("/api/v1/send/campaigns/{$this->segment}", $this->params)->json() ?? [];
    }

    /**
     * Cancela a campanha — válido enquanto pending/paused/processing.
     * DELETE /api/v1/send/campaigns/{canal}/{id}
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Campaign id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->delete("/api/v1/send/campaigns/{$this->segment}/{$id}");

        return [
            'status' => $response->json('status', false),
            'message' => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http' => $response->status(),
        ];
    }

    /**
     * Sub-recurso de contatos da campanha — status/erro por destinatário.
     * Exige o id da campanha (->campaigns($canal, $id)->contacts()).
     */
    public function contacts(): ServerCampaignContactQuery
    {
        return new ServerCampaignContactQuery($this->apiUrl, $this->apiKey, $this->segment, $this->id);
    }

    /**
     * Relatório da campanha: contatos por estado, entregues, abertos, cliques,
     * bounces, descadastros e o teste A/B. Exige o id.
     */
    public function report(): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Campaign id is required.', 'http' => 0];
        }

        return $this->http()->get("/api/v1/send/campaigns/{$this->segment}/{$this->id}/report")->json() ?? [];
    }

    /** Exporta os contatos da campanha em CSV (o conteúdo do arquivo). Exige o id. */
    public function export(): string
    {
        if ($this->id === null) {
            throw new \InvalidArgumentException('Campaign id is required to export.');
        }

        return $this->http()->get("/api/v1/send/campaigns/{$this->segment}/{$this->id}/export")->body();
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
