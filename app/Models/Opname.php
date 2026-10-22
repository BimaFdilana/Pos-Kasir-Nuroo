<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opname extends Model
{
    protected $fillable = [
        'tenant_id', 'warehouse_id', 'ingredient_id', 'user_id',
        'system_quantity', 'counted_quantity', 'difference', 'note',
    ];
    protected $casts = [
        'system_quantity' => 'float', 'counted_quantity' => 'float', 'difference' => 'float',
    ];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
