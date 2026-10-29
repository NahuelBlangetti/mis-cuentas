<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['client_id', 'type', 'area', 'description', 'category', 'amount', 'date', 'billing_month'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'date' => 'date']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
}
