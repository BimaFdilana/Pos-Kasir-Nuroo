<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['tenant_id', 'outlet_id', 'name', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function stocks(): HasMany { return $this->hasMany(IngredientStock::class); }
}
