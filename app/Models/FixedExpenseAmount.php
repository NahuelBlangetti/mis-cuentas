<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedExpenseAmount extends Model
{
    protected $fillable = ['effective_from', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class, 'fixed_expense_id');
    }
}
