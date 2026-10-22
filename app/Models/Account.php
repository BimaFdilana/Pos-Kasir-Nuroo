<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['tenant_id', 'code', 'name', 'type'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }
}
