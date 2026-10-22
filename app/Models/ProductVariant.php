<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'sku', 'price', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'price' => 'integer'];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stocks(): HasMany { return $this->hasMany(OutletStock::class); }
}
