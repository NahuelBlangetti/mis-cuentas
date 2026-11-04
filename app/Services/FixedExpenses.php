<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\FixedExpense;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FixedExpenses
{
    private const ORDER = ['ok' => 0, 'pending_share' => 0, 'pending_rate' => 0, 'not_started' => 1, 'ended' => 2];

    // Precio de compra de USDC en Arq según comparadolar.ar: lo que pagás por cada dólar.
    // Devuelve null si la API no responde, no informa a Arq o marca su precio como desactualizado: nunca un cero ni un precio viejo.
    public static function fetchRate(): ?array
    {
        try {
            $response = Http::acceptJson()->timeout(6)->get(config('services.exchange_rate.url'));
            $quotes = $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('No se pudo consultar la cotización del dólar.', ['error' => $e->getMessage()]);
            return null;
        }

        $quote = collect(is_array($quotes) ? $quotes : [])->first(fn ($item) => is_array($item) && ($item['slug'] ?? null) === config('services.exchange_rate.provider'));
        $ask = $quote['totalAsk'] ?? $quote['ask'] ?? null;
        if (! is_numeric($ask) || (float) $ask <= 0 || ($quote['stale'] ?? false) === true) return null;

        return ['rate' => round((float) $ask, 4), 'quoted_at' => self::quoteTime($quote['time'] ?? null)];
    }

    // Reporte del mes que, la primera vez que se consulta el mes en curso, guarda la cotización de Arq; después queda fija
    // hasta que la actualices. Si la API falla, se reintenta a los 15 minutos para no demorar cada visita.
    // Devuelve [reporte, si falló la consulta].
    public static function withCurrentRate(User $user, string $month): array
    {
        $report = self::report($user, $month);
        if ($month !== Carbon::now()->format('Y-m') || ! $report['needsRate'] || $report['rate'] || ! Cache::add("exchange-rate-attempt:{$user->id}:{$month}", true, 900)) {
            return [$report, false];
        }

        $quote = self::fetchRate();
        if (! $quote) return [$report, true];
        self::storeRate($user, $month, $quote['rate'], 'arq', $quote['quoted_at']);

        return [self::report($user, $month), false];
    }

    public static function storeRate(User $user, string $month, float $rate, string $source, ?Carbon $quotedAt = null): ExchangeRate
    {
        return $user->exchangeRates()->updateOrCreate(['currency' => 'USD', 'month' => $month], ['rate' => $rate, 'source' => $source, 'quoted_at' => $quotedAt]);
    }

    // Tu parte de cada gasto en un mes, en su moneda y en pesos. Un gasto sin participación cargada o en dólares sin cotización
    // queda pendiente y no suma al total: nunca se asume un reparto ni una cotización.
    public static function report(User $user, string $month): array
    {
        $rate = $user->exchangeRates()->where('currency', 'USD')->where('month', $month)->first();
        $rows = $user->fixedExpenses()->with(['amounts' => fn ($query) => $query->orderByDesc('effective_from'), 'resource.shares'])->get()
            ->map(function (FixedExpense $expense) use ($month, $rate) {
                $amount = $expense->amountFor($month);
                // Sin recurso vinculado, el gasto es completamente tuyo.
                $share = $expense->resource ? $expense->resource->shareFor($month)?->share_percent : '100';
                $mine = $amount && $share !== null ? (float) $amount->amount * (float) $share / 100 : null;
                $ars = $mine !== null && $expense->currency === 'USD' ? ($rate ? $mine * (float) $rate->rate : null) : $mine;
                $status = match (true) {
                    $amount === null => 'not_started',
                    (float) $amount->amount === 0.0 => 'ended',
                    $share === null => 'pending_share',
                    $ars === null => 'pending_rate',
                    default => 'ok',
                };

                return ['expense' => $expense, 'amount' => $amount, 'share' => $share, 'status' => $status,
                    'mine' => $mine === null ? null : round($mine, 2), 'ars' => $ars === null ? null : round($ars, 2)];
            })
            ->sortBy(fn ($row) => self::ORDER[$row['status']].'|'.$row['expense']->category.'|'.mb_strtolower($row['expense']->name))->values();

        $current = $rows->whereNotIn('status', ['not_started', 'ended']);
        $usd = $current->filter(fn ($row) => $row['expense']->currency === 'USD');

        return [
            'rows' => $rows, 'rate' => $rate,
            'totalArs' => round($rows->where('status', 'ok')->sum('ars'), 2),
            'totalUsd' => round($usd->whereIn('status', ['ok', 'pending_rate'])->sum('mine'), 2),
            'activeCount' => $current->count(),
            'pendingShare' => $current->where('status', 'pending_share')->count(),
            'pendingRate' => $current->where('status', 'pending_rate')->count(),
            'needsRate' => $usd->isNotEmpty(),
        ];
    }

    // comparadolar informa la hora como texto ISO o como marca Unix (en segundos o milisegundos).
    private static function quoteTime(mixed $time): Carbon
    {
        try {
            $parsed = match (true) {
                is_numeric($time) => Carbon::createFromTimestampMs((float) $time > 1e12 ? (int) $time : (int) $time * 1000),
                is_string($time) && $time !== '' => Carbon::parse($time),
                default => Carbon::now(),
            };
        } catch (Throwable) {
            $parsed = Carbon::now();
        }

        // Eloquent guarda la fecha sin zona: se pasa a la de la app para que se lea igual que se informó.
        return $parsed->setTimezone(config('app.timezone'));
    }
}
