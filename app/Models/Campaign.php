<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $guarded = ['id'];

    public function getRouteKeyName(): string { return 'slug'; }

    protected function casts(): array
    {
        return ['deadline' => 'datetime', 'goal_minor' => 'integer', 'allows_anonymous' => 'boolean'];
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function donations(): HasMany { return $this->hasMany(Donation::class); }
    public function events(): HasMany { return $this->hasMany(CampaignEvent::class)->orderByDesc('created_at'); }

    public function confirmedTotal(): int
    {
        return (int) $this->donations()->where('status', 'confirmed')->sum('amount_minor');
    }
}
