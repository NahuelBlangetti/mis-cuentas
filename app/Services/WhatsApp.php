<?php

namespace App\Services;

use App\Models\Client;

class WhatsApp
{
    // Lleva un celular al formato internacional que usa WhatsApp, solo dígitos. Devuelve null si no se puede interpretar.
    // Sin prefijo internacional se asume Argentina: código de área sin 0 y número sin 15 (ej. 351 1234567 → 5493511234567).
    // Los celulares argentinos llevan un 9 después del 54.
    public static function normalize(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') return null;
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        elseif (! str_starts_with($phone, '+') && ! preg_match('/^54\d{10,11}$/', $digits)) {
            $digits = ltrim($digits, '0');
            return strlen($digits) === 10 ? '549'.$digits : null;
        }

        if (preg_match('/^54(\d{10})$/', $digits, $match)) return '549'.$match[1];
        if (str_starts_with($digits, '54') && ! preg_match('/^549\d{10}$/', $digits)) return null;
        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? $digits : null;
    }

    public static function display(string $phone): string
    {
        return preg_match('/^549(\d{3})(\d{3})(\d{4})$/', $phone, $m) ? "+54 9 $m[1] $m[2]-$m[3]" : '+'.$phone;
    }

    // Mensaje de cobro con los meses adeudados. $months: ['2026-10' => 27500.0, ...]
    public static function chargeMessage(Client $client, array $months): string
    {
        ksort($months);
        $name = trim($client->name);
        if (count($months) === 1) {
            $month = array_key_first($months);
            $detail = "¡Hola {$name}! Te paso el abono de ".Billing::monthLabel($month).': '.money($months[$month]).".\nVence el día {$client->due_day}.";
        } else {
            $lines = array_map(fn ($month, $owed) => '• '.ucfirst(Billing::monthLabel($month)).': '.money($owed), array_keys($months), $months);
            $detail = "¡Hola {$name}! Te paso el detalle de los abonos pendientes:\n".implode("\n", $lines)."\nTotal: ".money(array_sum($months)).'.';
        }

        $alias = trim((string) config('services.transfer.alias'));
        return $detail.($alias !== '' ? "\n\nPodés transferir al alias {$alias}." : '')
            ."\n\n¿Ves todo correcto o necesitás algo? Estoy a disposición. ¡Gracias!";
    }

    // Abre WhatsApp con el mensaje escrito, listo para enviar al cliente.
    public static function link(string $phone, string $message): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
