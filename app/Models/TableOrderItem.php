<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableOrderItem extends Model
{
    protected $fillable = [
        'table_order_id', 'product_variant_id', 'product_name',
        'variant_name', 'unit_price', 'quantity', 'subtotal',
    ];
    protected $casts = ['unit_price' => 'integer', 'quantity' => 'integer', 'subtotal' => 'integer'];

    public function order(): BelongsTo { return $this->belongsTo(TableOrder::class, 'table_order_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
