<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    public const SOURCES = ['arq' => 'Compra de USDC en Arq', 'manual' => 'Carga manual'];

    protected $fillable = ['currency', 'month', 'rate', 'source', 'quoted_at'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'quoted_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function sourceLabel(): string { return self::SOURCES[$this->source] ?? $this->source; }
}
