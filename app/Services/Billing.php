<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Billing
{
    // Genera un abono por mes a cada cliente activo, desde el mes siguiente a su último abono hasta el actual.
    // Un cliente sin abonos empieza a cobrarse en el mes actual.
    public static function sync(User $user): void
    {
        $current = Carbon::now()->startOfMonth();
        foreach ($user->clients()->where('active', true)->withMax('charges', 'billing_month')->get() as $client) {
            $last = $client->charges_max_billing_month;
            $month = $last ? Carbon::createFromFormat('!Y-m', $last)->addMonth() : $current->copy();
            for (; $month->lte($current); $month->addMonth()) {
                $client->charges()->firstOrCreate(['billing_month' => $month->format('Y-m')], ['amount' => $client->monthly_fee]);
            }
        }
    }

    // Al pausar, el mes actual deja de cobrarse si todavía no tiene pagos. La deuda de meses anteriores se mantiene.
    public static function pause(Client $client): void
    {
        $month = Carbon::now()->format('Y-m');
        if (! $client->transactions()->where('billing_month', $month)->exists()) {
            $client->charges()->where('billing_month', $month)->delete();
        }
    }

    // Al reactivar, se cobra desde el mes actual; los meses en pausa no generan abono.
    public static function resume(Client $client): void
    {
        $client->charges()->firstOrCreate(['billing_month' => Carbon::now()->format('Y-m')], ['amount' => $client->monthly_fee]);
    }

    // Deuda de cada cliente, mes por mes: el abono del mes menos los cobros imputados a ese mes.
    // Un sobrepago no compensa otros meses ni otros clientes. Se calcula en centavos para evitar errores de float.
    public static function balances(User $user, string $month): Collection
    {
        $charges = Charge::query()->whereIn('client_id', $user->clients()->select('id'))->get(['client_id', 'billing_month', 'amount'])->groupBy('client_id');
        $payments = $user->transactions()->whereNotNull('client_id')->where('type', 'income')->get(['client_id', 'billing_month', 'amount'])->groupBy('client_id');

        return $user->clients()->pluck('id')->mapWithKeys(function ($id) use ($charges, $payments, $month) {
            $clientCharges = $charges->get($id, collect());
            $paid = $payments->get($id, collect())->groupBy('billing_month')->map(fn ($rows) => $rows->sum(fn ($row) => self::cents($row->amount)));
            $owed = $clientCharges->mapWithKeys(fn ($charge) => [$charge->billing_month => max(0, self::cents($charge->amount) - $paid->get($charge->billing_month, 0))])->filter()->sortKeys();

            return [$id => [
                'charged' => self::cents($clientCharges->firstWhere('billing_month', $month)?->amount ?? 0) / 100,
                'paid' => $paid->get($month, 0) / 100,
                'arrears' => $owed->except($month)->sum() / 100,
                'total' => $owed->sum() / 100,
                'months' => $owed->map(fn ($cents) => $cents / 100)->all(),
            ]];
        });
    }

    // Meses que se pueden cobrar: los adeudados y, si el cliente está activo, el actual y el siguiente como adelanto.
    // Un cliente pausado no genera abonos, así que un pago al mes actual o siguiente no saldaría nada.
    public static function paymentMonths(array $balance, string $month, bool $active): array
    {
        return $active ? $balance['months'] + [$month => 0, Carbon::createFromFormat('!Y-m', $month)->addMonth()->format('Y-m') => 0] : $balance['months'];
    }

    public static function monthLabel(string $month): string
    {
        return Carbon::createFromFormat('!Y-m', $month)->locale('es')->translatedFormat('F Y');
    }

    private static function cents(string|int|float $amount): int { return (int) round((float) $amount * 100); }
}
