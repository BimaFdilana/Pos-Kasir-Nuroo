<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientStockMovement extends Model
{
    protected $fillable = [
        'tenant_id', 'warehouse_id', 'ingredient_id', 'user_id', 'sale_id', 'opname_id',
        'type', 'quantity_change', 'quantity_after', 'reason', 'transfer_group',
    ];
    protected $casts = ['quantity_change' => 'float', 'quantity_after' => 'float'];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function opname(): BelongsTo { return $this->belongsTo(Opname::class); }
}
