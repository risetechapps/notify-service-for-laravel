<?php

namespace RiseTechApps\Notify\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RiseTechApps\HasUuid\Traits\HasUuid;

class NotifyCampaignContact extends Model
{
    use HasUuid;

    protected $table = 'notify_campaign_contacts';

    protected $fillable = [
        'notify_campaign_id',
        'contact',
        'name',
        'extra_data',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'sent_at'    => 'datetime',
    ];

    // ── Relacionamentos ───────────────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotifyCampaign::class, 'notify_campaign_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
