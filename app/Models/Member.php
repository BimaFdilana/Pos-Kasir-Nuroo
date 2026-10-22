<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = ['tenant_id', 'name', 'phone', 'email', 'birthdate', 'notes', 'is_active'];
    protected $casts = ['birthdate' => 'date', 'is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function pointLedgers(): HasMany { return $this->hasMany(PointLedger::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }

    /** Loyalty balance is always the sum of the ledger, never a stored column. */
    public function pointsBalance(): int
    {
        return (int) $this->pointLedgers()->sum('points');
    }
}
