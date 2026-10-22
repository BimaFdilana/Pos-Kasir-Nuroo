<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'tenant_id', 'outlet_id', 'sale_id', 'user_id',
        'entry_date', 'description', 'reference', 'is_reversal',
    ];
    protected $casts = ['entry_date' => 'date', 'is_reversal' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }
}
