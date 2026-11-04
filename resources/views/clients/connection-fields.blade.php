{{-- Nunca se precargan valores guardados: las credenciales solo se escriben. --}}
<div class="form-grid">
    <label class="field"><span>Host de la base</span><input name="host" maxlength="255" autocomplete="off" @required(! $optional)></label>
    <label class="field"><span>Puerto</span><input name="port" type="number" min="1" max="65535" value="3306" @required(! $optional)></label>
    <label class="field"><span>Base de datos</span><input name="database" maxlength="255" autocomplete="off" @required(! $optional)></label>
    <label class="field"><span>Usuario de solo lectura</span><input name="username" maxlength="255" autocomplete="off" @required(! $optional)></label>
    <label class="field field-wide"><span>Contraseña de la base</span><input name="password" type="password" maxlength="4096" autocomplete="new-password" @required(! $optional)></label>
</div>
