<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfrastructureResourceShare extends Model
{
    protected $fillable = ['effective_from', 'share_percent', 'notes'];

    protected function casts(): array
    {
        return ['share_percent' => 'decimal:2'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(InfrastructureResource::class, 'infrastructure_resource_id');
    }

    // "45 %" o "37,5 %", sin ceros de más.
    public function percentLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->share_percent, 2, ',', '.'), '0'), ',').' %';
    }
}
