<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableOrder extends Model
{
    public const OPEN_STATUSES = ['new', 'preparing', 'ready', 'served'];

    public const TRANSITIONS = [
        'new' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['served', 'cancelled'],
        'served' => [],
        'cancelled' => [],
        'paid' => [],
    ];

    protected $fillable = [
        'tenant_id', 'outlet_id', 'dining_table_id', 'customer_name',
        'customer_phone', 'note', 'status', 'sale_id',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function diningTable(): BelongsTo { return $this->belongsTo(DiningTable::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function items(): HasMany { return $this->hasMany(TableOrderItem::class); }

    public function isOpen(): bool { return in_array($this->status, self::OPEN_STATUSES, true); }

    public function total(): int { return $this->items->sum('subtotal'); }
}
