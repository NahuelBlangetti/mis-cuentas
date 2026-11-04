<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalExpense extends Model
{
    // Las usan también los gastos recurrentes, para sumar ambos por categoría.
    public const CATEGORIES = [
        'housing' => 'Vivienda', 'utilities' => 'Servicios', 'food' => 'Súper y comida', 'transport' => 'Transporte',
        'health' => 'Salud', 'subscriptions' => 'Suscripciones', 'education' => 'Educación', 'leisure' => 'Salidas y ocio',
        'shopping' => 'Compras', 'taxes' => 'Impuestos y trámites', 'other' => 'Otros',
    ];

    protected $fillable = ['description', 'category', 'amount', 'date'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'date' => 'date']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function categoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
}
