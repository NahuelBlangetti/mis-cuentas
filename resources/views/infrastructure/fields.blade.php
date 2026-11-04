@use('App\Models\InfrastructureResource')
<div class="form-grid">
    <label class="field field-wide"><span>Nombre</span><input name="name" maxlength="160" value="{{ $item?->name ?? old('name') }}" placeholder="Ej. Clúster MySQL compartido" required></label>
    <label class="field"><span>Proveedor</span><select name="provider" required>@foreach (InfrastructureResource::PROVIDERS as $value => $label)<option value="{{ $value }}" @selected(($item?->provider ?? old('provider')) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="field"><span>Tipo</span><select name="kind" required>@foreach (InfrastructureResource::KINDS as $value => $label)<option value="{{ $value }}" @selected(($item?->kind ?? old('kind')) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="field field-wide"><span>ID en el proveedor (opcional)</span><input name="external_id" maxlength="255" value="{{ $item?->external_id ?? old('external_id') }}" autocomplete="off"><small class="field-hint">Por ejemplo, el UUID del clúster en DigitalOcean. Sirve para conciliar la facturación más adelante.</small></label>
    <label class="field field-wide"><span>Notas (opcional)</span><textarea name="notes" rows="2" maxlength="5000">{{ $item?->notes ?? old('notes') }}</textarea></label>
</div>
