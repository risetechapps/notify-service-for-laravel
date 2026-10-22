<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Sub-recurso de contatos de uma campanha — status e motivo de falha por destinatário.
 * Instanciado via NotifyQuery::server()->campaigns($canal, $id)->contacts().
 *
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->contacts()
 *       ->status('failed')   // pending | sending | sent | failed | skipped
 *       ->page(2)
 *       ->get();
 *
 * GET /api/v1/send/campaigns/{sms|mail}/{id}/contacts?status=&page=
 */
class ServerCampaignContactQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $segment,
        protected ?string $campaignId = null,
    ) {}

    /** status: pending | sending | sent | failed | skipped */
    public function status(string $status): static
    {
        $this->params['status'] = $status;

        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;

        return $this;
    }

    /**
     * Lista os contatos (paginado). Retorna a resposta crua do servidor:
     * ['status' => bool, 'data' => [...], 'pagination' => ['current_page','per_page','total','last_page']].
     */
    public function get(): array
    {
        if ($this->campaignId === null) {
            return ['status' => false, 'data' => [], 'pagination' => []];
        }

        return $this->http()
            ->get("/api/v1/send/campaigns/{$this->segment}/{$this->campaignId}/contacts", $this->params)
            ->json() ?? [];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
