<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    // "tables" is an awkward table name, so the model is DiningTable
    // on the dining_tables table; the API and UI still call them tables.
    protected $table = 'dining_tables';

    protected $fillable = ['tenant_id', 'outlet_id', 'name', 'status', 'is_active', 'qr_code'];
    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function orders(): HasMany { return $this->hasMany(TableOrder::class); }

    public static function generateQrCode(): string
    {
        // Random token, deliberately NOT the table number, so a QR code
        // cannot be guessed by trying "meja 1, meja 2, ...".
        do {
            $code = Str::random(32);
        } while (static::where('qr_code', $code)->exists());

        return $code;
    }

    /** Recompute free/occupied from open orders; called after order changes. */
    public function refreshStatus(): void
    {
        $occupied = $this->orders()->whereIn('status', TableOrder::OPEN_STATUSES)->exists();
        $status = $occupied ? 'occupied' : 'free';
        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }
}
