<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_variant_id', 'product_name', 'variant_name', 'unit_price', 'quantity', 'subtotal'];
    protected $casts = ['unit_price' => 'integer', 'quantity' => 'integer', 'subtotal' => 'integer'];

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
