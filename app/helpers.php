<?php

// Importe en pesos con formato argentino: sin decimales si es entero, con centavos si los tiene.
if (! function_exists('money')) {
    function money(string|int|float|null $amount): string
    {
        $cents = (int) round((float) $amount * 100);
        return ($cents < 0 ? '−' : '').'$'.number_format(abs($cents) / 100, $cents % 100 === 0 ? 0 : 2, ',', '.');
    }
}
