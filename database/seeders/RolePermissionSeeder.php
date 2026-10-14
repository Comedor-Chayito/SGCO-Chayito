<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder de Roles y Permisos oficiales de SGCO-Chayito (US-ADM-01 / RF-ADM-008).
 * Define la matriz de control de acceso basada en roles (RBAC).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Ejecuta el sembrado de roles, permisos y matriz de asignación.
     *
     * @return void
     */
    public function run(): void
    {
        // Limpiar la caché de Spatie Permission
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definición de permisos por módulo
        $permisos = [
            // Administración de usuarios
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.desactivar',

            // POS y Comandas
            'comandas.ver',
            'comandas.crear',

            // Cobros y Caja
            'cobros.ver',
            'cobros.procesar',

            // Inventario
            'inventario.ver',
            'inventario.gestionar',

            // Contabilidad y Reportes
            'contabilidad.ver',
            'reportes.ver',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // 2. Definición y configuración de roles oficiales
        $rolAdmin = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $rolCajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $rolMesero = Role::firstOrCreate(['name' => 'mesero', 'guard_name' => 'web']);
        $rolCocinero = Role::firstOrCreate(['name' => 'cocinero', 'guard_name' => 'web']);
        $rolCocinera = Role::firstOrCreate(['name' => 'cocinera', 'guard_name' => 'web']);
        $rolContadora = Role::firstOrCreate(['name' => 'contadora', 'guard_name' => 'web']);

        // 3. Matriz de asignación de permisos
        // Administrador tiene acceso total a todos los permisos
        $rolAdmin->syncPermissions(Permission::all());

        // Cajero
        $rolCajero->syncPermissions([
            'comandas.ver',
            'cobros.ver',
            'cobros.procesar',
        ]);

        // Mesero
        $rolMesero->syncPermissions([
            'comandas.ver',
            'comandas.crear',
        ]);

        // Cocinero / Cocinera
        $permisosCocina = [
            'comandas.ver',
            'inventario.ver',
            'inventario.gestionar',
        ];
        $rolCocinero->syncPermissions($permisosCocina);
        $rolCocinera->syncPermissions($permisosCocina);

        // Contadora
        $rolContadora->syncPermissions([
            'contabilidad.ver',
            'reportes.ver',
        ]);

        // 4. Crear o actualizar usuario administrador oficial
        $admin = User::updateOrCreate(
            ['email' => 'admin@sgco-chayito.local'],
            [
                'name'     => 'Administrador General',
                'password' => \Illuminate\Support\Facades\Hash::make('Admin1234!'),
                'activo'   => true,
            ]
        );
        $admin->syncRoles(['administrador']);

        // 5. Crear usuarios de muestra con otros roles para visualización en el panel
        $cajero = User::updateOrCreate(
            ['email' => 'cajero@sgco-chayito.local'],
            [
                'name'     => 'Marcos Cajero',
                'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
                'activo'   => true,
            ]
        );
        $cajero->syncRoles(['cajero']);

        $mesero = User::updateOrCreate(
            ['email' => 'mesero@sgco-chayito.local'],
            [
                'name'     => 'Carlos Mesero',
                'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
                'activo'   => true,
            ]
        );
        $mesero->syncRoles(['mesero']);
    }
}

