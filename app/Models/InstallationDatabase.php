<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationDatabase extends Model
{
    public const CONNECTION_FIELDS = ['host', 'port', 'database', 'username', 'password'];

    protected $fillable = ['name', 'infrastructure_resource_id', 'connection'];

    protected $hidden = ['connection'];

    protected function casts(): array
    {
        return ['connection' => 'encrypted:array'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(ClientInstallation::class, 'client_installation_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(InfrastructureResource::class, 'infrastructure_resource_id');
    }

    // Reglas de los datos de conexión. Opcionales: si se completa uno de host, base, usuario o contraseña, se exigen todos.
    public static function connectionRules(bool $optional = false): array
    {
        $presence = $optional ? 'required_with:host,database,username,password' : 'required';

        return [
            'host' => [$presence, 'nullable', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9.:-]+\z/'],
            'port' => [$presence, 'nullable', 'integer', 'between:1,65535'],
            'database' => [$presence, 'nullable', 'string', 'max:255'],
            'username' => [$presence, 'nullable', 'string', 'max:255'],
            'password' => [$presence, 'nullable', 'string', 'max:4096'],
        ];
    }

    // Conexión a guardar a partir de datos validados, o null si no se cargó ninguna.
    public static function connectionFrom(array $data): ?array
    {
        if (blank($data['host'] ?? null)) return null;

        return [...array_intersect_key($data, array_flip(self::CONNECTION_FIELDS)), 'port' => (int) $data['port']];
    }
}
