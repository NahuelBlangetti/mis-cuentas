<?php

namespace Tests\Feature;

use App\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_columnas_leídas_de_una_vez_coinciden_con_el_esquema(): void
    {
        $this->assertSame(array_keys(Ledger::COLUMNS), array_keys(Ledger::SOURCES));
        foreach (Ledger::COLUMNS as $table => $columns) {
            $this->assertEqualsCanonicalizing($columns, Schema::getColumnListing($table), $table);
        }
    }
}
