<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class InfrastructureResource extends Model
{
    public const PROVIDERS = ['digitalocean' => 'DigitalOcean', 'hosting' => 'Hosting compartido', 'other' => 'Otro proveedor'];

    public const KINDS = [
        'database_cluster' => 'Clúster de bases de datos', 'server' => 'Servidor o droplet',
        'kubernetes' => 'Clúster Kubernetes', 'storage' => 'Almacenamiento', 'other' => 'Otro recurso',
    ];

    protected $fillable = ['name', 'provider', 'kind', 'external_id', 'active', 'notes'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function shares(): HasMany { return $this->hasMany(InfrastructureResourceShare::class); }
    public function installations(): HasMany { return $this->hasMany(ClientInstallation::class); }
    public function databases(): HasMany { return $this->hasMany(InstallationDatabase::class); }
    public function fixedExpenses(): HasMany { return $this->hasMany(FixedExpense::class); }

    // Participación vigente en un mes: la última cargada desde ese mes o antes.
    // Sin ninguna, devuelve null y el recurso queda pendiente: nunca se asume un reparto.
    public function shareFor(string $month): ?InfrastructureResourceShare
    {
        return $this->shares->filter(fn ($share) => $share->effective_from <= $month)->sortByDesc('effective_from')->first();
    }

    // Clientes que usan el recurso, ya sea como servidor de una instalación o como clúster de una de sus bases.
    public function clients(): Collection
    {
        return $this->installations->pluck('client')->merge($this->databases->pluck('installation.client'))->filter()->unique('id')->sortBy('name')->values();
    }

    // Un gasto fijo vinculado también lo bloquea: al borrar el recurso, el gasto pasaría a contarse completo como tuyo.
    public function inUse(): bool
    {
        return $this->installations()->exists() || $this->databases()->exists() || $this->fixedExpenses()->exists();
    }

    public function providerLabel(): string { return self::PROVIDERS[$this->provider] ?? $this->provider; }
    public function kindLabel(): string { return self::KINDS[$this->kind] ?? $this->kind; }
}
