<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    protected $fillable = ['tenant_id', 'name', 'unit', 'cost_per_unit', 'min_stock', 'is_active'];
    protected $casts = ['cost_per_unit' => 'integer', 'min_stock' => 'float', 'is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function stocks(): HasMany { return $this->hasMany(IngredientStock::class); }
    public function recipes(): HasMany { return $this->hasMany(Recipe::class); }
}
