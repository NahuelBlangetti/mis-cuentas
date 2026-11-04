<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalRecurringExpense extends Model
{
    protected $fillable = ['name', 'category', 'currency', 'notes'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function amounts(): HasMany { return $this->hasMany(PersonalRecurringAmount::class, 'recurring_expense_id'); }

    // Importe vigente en un mes: el último cargado desde ese mes o antes. Null si el gasto todavía no había empezado.
    public function amountFor(string $month): ?PersonalRecurringAmount
    {
        return $this->amounts->filter(fn ($amount) => $amount->effective_from <= $month)->sortByDesc('effective_from')->first();
    }

    public function categoryLabel(): string { return PersonalExpense::CATEGORIES[$this->category] ?? $this->category; }

    public function format(string|int|float|null $amount): string
    {
        return $this->currency === 'USD' ? usd($amount) : money($amount);
    }
}
