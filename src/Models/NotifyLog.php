<?php

namespace RiseTechApps\Notify\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RiseTechApps\HasUuid\Traits\HasUuid;

class NotifyLog extends Model
{
    use HasUuid;

    protected $table = 'notify_logs';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'channel',
        'server_notification_id',
        'status',
        'payload',
        'server_response',
        'error_message',
        'sent_at',
        'delivered_at',
        'failed_at',
        'notify_campaign_id',
    ];

    protected $casts = [
        'payload'         => 'array',
        'server_response' => 'array',
        'sent_at'         => 'datetime',
        'delivered_at'    => 'datetime',
        'failed_at'       => 'datetime',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────────

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotifyCampaign::class, 'notify_campaign_id');
    }

    // ── Helpers de ciclo de vida ──────────────────────────────────────────────

    public function markAsSent(string $serverNotificationId, array $response = []): void
    {
        $this->update([
            'status'                 => 'sent',
            'server_notification_id' => $serverNotificationId,
            'server_response'        => $response,
            'sent_at'                => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage, array $response = []): void
    {
        $this->update([
            'status'          => 'error',
            'error_message'   => $errorMessage,
            'server_response' => $response ?: $this->server_response,
            'failed_at'       => now(),
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
