<?php

use App\Models\PersonalExpense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Categoría libre de Movimientos que coincide con una categoría personal (sin importar mayúsculas ni tildes).
    private const MATCHES = [
        'vivienda' => 'housing', 'alquiler' => 'housing', 'expensas' => 'housing',
        'servicios' => 'utilities', 'luz' => 'utilities', 'gas' => 'utilities', 'agua' => 'utilities', 'internet' => 'utilities', 'celular' => 'utilities',
        'comida' => 'food', 'super' => 'food', 'supermercado' => 'food',
        'transporte' => 'transport', 'nafta' => 'transport', 'combustible' => 'transport',
        'salud' => 'health', 'farmacia' => 'health',
        'suscripciones' => 'subscriptions', 'suscripcion' => 'subscriptions',
        'educacion' => 'education',
        'ocio' => 'leisure', 'salidas' => 'leisure',
        'compras' => 'shopping', 'ropa' => 'shopping',
        'impuestos' => 'taxes',
    ];

    // Los gastos personales cargados en Movimientos pasan al módulo de Finanzas personales.
    // Los ingresos personales quedan en Movimientos: por ahora el módulo personal solo guarda gastos.
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('transactions')->where('area', 'personal')->where('type', 'expense')->chunkById(500, function ($rows) {
                DB::table('personal_expenses')->insert($rows->map(function ($row) {
                    $text = trim((string) $row->category);
                    $category = self::MATCHES[$this->fold($text)] ?? 'other';
                    // Una categoría que no coincide se conserva en la descripción para no perderla.
                    $description = $category === 'other' && $text !== '' && mb_strlen($row->description.' · '.$text) <= 180 ? $row->description.' · '.$text : $row->description;

                    return ['user_id' => $row->user_id, 'description' => $description, 'category' => $category, 'amount' => $row->amount, 'date' => $row->date, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at];
                })->all());
                DB::table('transactions')->whereIn('id', $rows->pluck('id'))->delete();
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('personal_expenses')->chunkById(500, function ($rows) {
                DB::table('transactions')->insert($rows->map(fn ($row) => [
                    'user_id' => $row->user_id, 'type' => 'expense', 'area' => 'personal', 'description' => $row->description,
                    'category' => $row->category === 'other' ? null : (PersonalExpense::CATEGORIES[$row->category] ?? $row->category), 'amount' => $row->amount, 'date' => $row->date,
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ])->all());
                DB::table('personal_expenses')->whereIn('id', $rows->pluck('id'))->delete();
            });
        });
    }

    private function fold(string $text): string
    {
        return strtr(mb_strtolower($text), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }
};
