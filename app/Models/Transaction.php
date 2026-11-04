<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['client_id', 'client_name', 'type', 'area', 'description', 'category', 'amount', 'date', 'billing_month'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'date' => 'date']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }

    // Un cobro de abono sale de Clientes y cobros; cualquier otro movimiento puede ser de un cliente registrado o de uno ocasional.
    public function isSubscriptionPayment(): bool { return $this->billing_month !== null; }

    public function clientLabel(): ?string
    {
        if ($this->client_id) return $this->client?->label();
        return $this->client_name ? $this->client_name.' (ocasional)' : null;
    }

    // Valor del selector de cliente del formulario: el id, "occasional" o vacío.
    public function clientChoice(): string
    {
        return $this->client_id ? (string) $this->client_id : ($this->client_name ? 'occasional' : '');
    }
}
