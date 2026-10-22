<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletStock extends Model
{
    protected $fillable = ['outlet_id', 'product_variant_id', 'quantity', 'min_stock'];
    protected $casts = ['quantity' => 'integer', 'min_stock' => 'integer'];

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
