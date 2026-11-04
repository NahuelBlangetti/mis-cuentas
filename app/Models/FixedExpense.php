<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedExpense extends Model
{
    public const CATEGORIES = ['tools' => 'Herramientas y suscripciones', 'infrastructure' => 'Infraestructura', 'other' => 'Otro gasto'];

    public const CURRENCIES = ['USD' => 'Dólares (USD)', 'ARS' => 'Pesos (ARS)'];

    protected $fillable = ['name', 'category', 'currency', 'infrastructure_resource_id', 'notes'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function resource(): BelongsTo { return $this->belongsTo(InfrastructureResource::class, 'infrastructure_resource_id'); }
    public function amounts(): HasMany { return $this->hasMany(FixedExpenseAmount::class); }

    // Importe vigente en un mes: el último cargado desde ese mes o antes. Null si el gasto todavía no había empezado.
    public function amountFor(string $month): ?FixedExpenseAmount
    {
        return $this->amounts->filter(fn ($amount) => $amount->effective_from <= $month)->sortByDesc('effective_from')->first();
    }

    public function categoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }

    public function format(string|int|float|null $amount): string
    {
        return $this->currency === 'USD' ? usd($amount) : money($amount);
    }
}
