<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_muestra_centavos_solo_cuando_existen(): void
    {
        $this->assertSame('$27.500', money(27500));
        $this->assertSame('$27.500', money('27500.00'));
        $this->assertSame('$1.000,50', money(1000.5));
        $this->assertSame('$0,07', money(0.07));
        $this->assertSame('$0', money(null));
    }

    public function test_los_negativos_llevan_el_signo_antes_del_peso(): void
    {
        $this->assertSame('−$1.250,30', money(-1250.3));
    }

    public function test_redondea_errores_de_float(): void
    {
        $this->assertSame('$0,30', money(0.1 + 0.2));
        $this->assertSame('$0', money(0.8 - 0.7 - 0.1));
    }
}
