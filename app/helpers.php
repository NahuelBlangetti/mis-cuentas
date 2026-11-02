<?php

// URL de un archivo de public/ con la fecha de modificación, para no reutilizar una copia vieja del navegador.
if (! function_exists('asset_version')) {
    function asset_version(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? filemtime($file) : '0';

        return asset($path).'?v='.$version;
    }
}

// Importe en pesos con formato argentino: sin decimales si es entero, con centavos si los tiene.
if (! function_exists('money')) {
    function money(string|int|float|null $amount): string
    {
        $cents = (int) round((float) $amount * 100);
        return ($cents < 0 ? '−' : '').'$'.number_format(abs($cents) / 100, $cents % 100 === 0 ? 0 : 2, ',', '.');
    }
}
