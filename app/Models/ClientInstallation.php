<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientInstallation extends Model
{
    protected $fillable = ['name', 'url', 'infrastructure_resource_id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Servidor donde corre el sistema. Las bases pueden estar en otro recurso (por ejemplo, un clúster compartido).
    public function resource(): BelongsTo
    {
        return $this->belongsTo(InfrastructureResource::class, 'infrastructure_resource_id');
    }

    public function databases(): HasMany
    {
        return $this->hasMany(InstallationDatabase::class);
    }
}
