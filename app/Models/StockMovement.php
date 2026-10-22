<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['tenant_id', 'outlet_id', 'product_variant_id', 'sale_id', 'user_id', 'type', 'quantity_change', 'quantity_after', 'reason'];
    protected $casts = ['quantity_change' => 'integer', 'quantity_after' => 'integer'];

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
}
