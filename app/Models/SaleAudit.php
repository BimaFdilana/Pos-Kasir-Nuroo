<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleAudit extends Model
{
    protected $fillable = ['sale_id', 'action', 'actor_user_id', 'approver_user_id', 'reason', 'total_before', 'total_after', 'payload'];
    protected $casts = ['total_before' => 'integer', 'total_after' => 'integer', 'payload' => 'array'];

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_user_id'); }
}
