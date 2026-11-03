<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInstallation extends Model
{
    protected $fillable = ['name', 'url', 'connection'];

    protected $hidden = ['connection'];

    protected function casts(): array
    {
        return ['connection' => 'encrypted:array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
