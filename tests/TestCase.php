<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bases en las que la suite puede correr: `testing` (MySQL de Sail) y `:memory:` (SQLite del CI).
     */
    private const BASES_PERMITIDAS = ['testing', ':memory:'];

    /**
     * Crea la aplicación y detiene la prueba si la conexión no apunta a una base de pruebas
     * aislada. Corre antes de RefreshDatabase, así que nunca se migra la base de desarrollo.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-25
     *
     * @módulo Plan de pruebas – CC-134 (B2 Entorno de pruebas)
     *
     * @return void
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $baseActual = $this->app['db']->connection()->getDatabaseName();

        if (! in_array($baseActual, self::BASES_PERMITIDAS, true)) {
            $this->fail(
                "La suite apunta a la base '{$baseActual}'. Solo puede correr sobre: "
                .implode(', ', self::BASES_PERMITIDAS)
                .'. Revise phpunit.xml y ejecute `php artisan config:clear`.'
            );
        }
    }
}
