<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'phone', 'systems', 'monthly_fee', 'due_day', 'active', 'notes'];

    protected function casts(): array
    {
        return ['monthly_fee' => 'decimal:2', 'active' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
    public function charges(): HasMany { return $this->hasMany(Charge::class); }
    public function installations(): HasMany { return $this->hasMany(ClientInstallation::class); }
}
