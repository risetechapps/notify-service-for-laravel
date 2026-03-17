<?php

namespace RiseTechApps\Notify\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RiseTechApps\Notify\Models\NotifyLog;

class NotifyCampaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'notify_campaigns';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'channel',
        'name',
        'server_campaign_id',
        'status',
        'template',
        'config_id',
        'webhook_url',
        'rate_per_minute',
        'scheduled_at',
        'total_contacts',
        'sent_count',
        'failed_count',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'template'     => 'array',
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────────

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(NotifyCampaignContact::class, 'notify_campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotifyLog::class, 'notify_campaign_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Progresso em % baseado nos contatos já processados */
    public function getProgressAttribute(): int
    {
        if ($this->total_contacts === 0) {
            return 0;
        }

        return (int) round(
            (($this->sent_count + $this->failed_count) / $this->total_contacts) * 100
        );
    }

    /** Atualiza contadores e status a partir de um payload de webhook */
    public function syncFromWebhook(array $data): void
    {
        $fields = array_filter([
            'server_campaign_id' => $data['campaign_id']   ?? $this->server_campaign_id,
            'status'             => $data['status']         ?? null,
            'total_contacts'     => $data['total']          ?? null,
            'sent_count'         => $data['sent']           ?? null,
            'failed_count'       => $data['failed']         ?? null,
            'started_at'         => $data['started_at']     ?? null,
            'finished_at'        => $data['finished_at']    ?? null,
        ], fn($v) => !is_null($v));

        $this->update($fields);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
