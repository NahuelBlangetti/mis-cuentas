@use('App\Models\FixedExpense')
<div class="form-grid">
    <label class="field field-wide"><span>Nombre</span><input name="name" maxlength="160" value="{{ $item?->name ?? old('name') }}" placeholder="Ej. Cursor o clúster de bases" required></label>
    <label class="field"><span>Tipo</span><select name="category" required>@foreach (FixedExpense::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(($item?->category ?? old('category')) === $value)>{{ $label }}</option>@endforeach</select></label>
    @if ($item)
        <div class="field"><span>Moneda</span><b>{{ FixedExpense::CURRENCIES[$item->currency] ?? $item->currency }}</b><small class="field-hint">No se cambia: los importes cargados están en esta moneda.</small></div>
    @else
        <label class="field"><span>Moneda</span><select name="currency" required>@foreach (FixedExpense::CURRENCIES as $value => $label)<option value="{{ $value }}" @selected(old('currency', 'USD') === $value)>{{ $label }}</option>@endforeach</select></label>
    @endif
    <label class="field field-wide"><span>Recurso de infraestructura (opcional)</span><select name="infrastructure_resource_id"><option value="">Ninguno: lo pagás completo vos</option>@foreach ($resources as $resource)<option value="{{ $resource->id }}" @selected((string) ($item?->infrastructure_resource_id ?? old('infrastructure_resource_id')) === (string) $resource->id)>{{ $resource->name }}{{ $resource->active ? '' : ' (dado de baja)' }}</option>@endforeach</select><small class="field-hint">Si es la factura de un servidor o clúster que compartís, cargá el total y vinculalo: tu parte sale de tu participación en ese recurso.</small></label>
    <label class="field field-wide"><span>Notas (opcional)</span><textarea name="notes" rows="2" maxlength="5000">{{ $item?->notes ?? old('notes') }}</textarea></label>
</div>
