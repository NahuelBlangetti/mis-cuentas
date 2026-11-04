<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Se ejecuta antes de RefreshDatabase: si el .env apunta a producción, ningún test llega a borrar tablas.
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $connection = config('database.connections.'.config('database.default'));
        $memory = ($connection['driver'] ?? null) === 'sqlite' && ($connection['database'] ?? null) === ':memory:';
        $localTestDatabase = in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            && str_ends_with((string) ($connection['database'] ?? ''), '_test');

        if (! $memory && ! $localTestDatabase) {
            throw new RuntimeException('Los tests solo corren con SQLite en memoria o con una base local terminada en _test.');
        }
    }
}
