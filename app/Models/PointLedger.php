<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointLedger extends Model
{
    public const TYPES = ['earn', 'redeem', 'adjust', 'reversal'];

    protected $fillable = ['tenant_id', 'member_id', 'sale_id', 'user_id', 'type', 'points', 'reason'];
    protected $casts = ['points' => 'integer'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
