<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'outlet_id',
        'checked_in_at', 'checked_out_at',
        'latitude', 'longitude', 'note',
        'checkout_latitude', 'checkout_longitude', 'checkout_note',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'checkout_latitude' => 'float',
        'checkout_longitude' => 'float',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }

    public function isOpen(): bool
    {
        return $this->checked_out_at === null;
    }
}
