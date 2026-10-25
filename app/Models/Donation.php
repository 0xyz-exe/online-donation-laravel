<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'is_anonymous' => 'boolean', 'show_amount' => 'boolean', 'reviewed_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function events(): HasMany { return $this->hasMany(DonationEvent::class)->orderByDesc('created_at'); }
}
