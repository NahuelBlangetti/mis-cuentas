@use('App\Models\FixedExpense')
@use('App\Models\PersonalExpense')
{{-- Edición de un gasto recurrente. El alta está en el formulario "Cargar gasto". --}}
<div class="form-grid">
    <label class="field field-wide"><span>Nombre</span><input name="name" maxlength="160" value="{{ $item->name }}" required></label>
    <label class="field"><span>Categoría</span><select name="category" required>@foreach (PersonalExpense::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected($item->category === $value)>{{ $label }}</option>@endforeach</select></label>
    <div class="field"><span>Moneda</span><b>{{ FixedExpense::CURRENCIES[$item->currency] ?? $item->currency }}</b><small class="field-hint">No se cambia: los importes cargados están en esta moneda.</small></div>
    <label class="field field-wide"><span>Notas (opcional)</span><textarea name="notes" rows="2" maxlength="5000" placeholder="Ej. débito automático de la tarjeta">{{ $item->notes }}</textarea></label>
</div>
