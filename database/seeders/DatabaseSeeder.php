<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ejecuta todos los seeders del sistema SGCO-Chayito.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-18
     * @módulo Core
     *
     * @return void
     */
    public function run(): void
    {
        // Usuario de prueba base
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Roles y Permisos (RBAC — US-ADM-01)
        $this->call(RolePermissionSeeder::class);

        // Datos del módulo POS: mesas y platillos del menú diario
        $this->call(PosSeeder::class);
    }
}
