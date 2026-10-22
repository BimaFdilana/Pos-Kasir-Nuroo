<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    protected $fillable = ['product_variant_id', 'ingredient_id', 'quantity'];
    protected $casts = ['quantity' => 'float'];

    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }

    /** Cost of this one recipe line for a single serving, in rupiah. */
    public function lineCost(): int
    {
        return (int) round($this->quantity * $this->ingredient->cost_per_unit);
    }
}
