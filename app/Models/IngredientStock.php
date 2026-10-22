<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientStock extends Model
{
    protected $fillable = ['warehouse_id', 'ingredient_id', 'quantity'];
    protected $casts = ['quantity' => 'float'];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
}
