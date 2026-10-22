<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'outlet_id', 'date', 'start_time', 'end_time', 'note',
    ];

    protected $casts = ['date' => 'date'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
}
