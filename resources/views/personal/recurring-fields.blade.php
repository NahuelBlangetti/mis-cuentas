@use('App\Models\FixedExpense')
@use('App\Models\PersonalExpense')
{{-- Los datos de un alta que no pasó la validación vuelven solo a su formulario: el de gasto del mes usa los mismos nombres. --}}
@php($fromOld = ! $item && old('form') === 'recurring')
<div class="form-grid">
    <label class="field field-wide"><span>Nombre</span><input name="name" maxlength="160" value="{{ $item?->name ?? ($fromOld ? old('name') : '') }}" placeholder="Ej. alquiler, luz, Netflix" required></label>
    <label class="field"><span>Categoría</span><select name="category" required><option value="">Elegí una categoría</option>@foreach (PersonalExpense::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(($item?->category ?? ($fromOld ? old('category') : null)) === $value)>{{ $label }}</option>@endforeach</select></label>
    @if ($item)
        <div class="field"><span>Moneda</span><b>{{ FixedExpense::CURRENCIES[$item->currency] ?? $item->currency }}</b><small class="field-hint">No se cambia: los importes cargados están en esta moneda.</small></div>
    @else
        <label class="field"><span>Moneda</span><select name="currency" required>@foreach (FixedExpense::CURRENCIES as $value => $label)<option value="{{ $value }}" @selected(($fromOld ? old('currency') : 'ARS') === $value)>{{ $label }}</option>@endforeach</select></label>
    @endif
    <label class="field field-wide"><span>Notas (opcional)</span><textarea name="notes" rows="2" maxlength="5000" placeholder="Ej. débito automático de la tarjeta">{{ $item?->notes ?? ($fromOld ? old('notes') : '') }}</textarea></label>
</div>
