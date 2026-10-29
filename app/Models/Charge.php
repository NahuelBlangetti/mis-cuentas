<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charge extends Model
{
    protected $fillable = ['billing_month', 'amount'];

    protected function casts(): array { return ['amount' => 'decimal:2']; }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
}
