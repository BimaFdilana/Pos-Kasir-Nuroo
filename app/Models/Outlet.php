<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = ['tenant_id', 'name', 'code', 'address', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function stocks(): HasMany { return $this->hasMany(OutletStock::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
}
