<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolesAndUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();


        Permission::create(['name' => 'manage patients']);
        Permission::create(['name' => 'manage consultations']);
        Permission::create(['name' => 'schedule appointments']);
        Permission::create(['name' => 'view dashboard']);
        Permission::create(['name' => 'view profile']);
        Permission::create(['name' => 'schedule my appointments']);


        $roleRecepcionista = Role::create(['name' => 'recepcionista']);
        $roleRecepcionista->givePermissionTo(['schedule appointments', 'view dashboard']);

                $roleUsuario = Role::create(['name' => 'paciente']);
        $roleUsuario->givePermissionTo(['view profile', 'schedule my appointments']);

        // El Bariatra puede hacer todo lo clínico y ver su agenda
        $roleBariatra = Role::create(['name' => 'bariatra']);
        $roleBariatra->givePermissionTo(['manage patients', 'manage consultations', 'schedule appointments', 'view dashboard']);

        // El Administrador tiene control total (Spatie permite darle todo de golpe)
        $roleAdmin = Role::create(['name' => 'admin']);
        $roleAdmin->givePermissionTo(Permission::all());


        // 4. Crear Usuarios de Prueba y Asignarles su Rol

        // Usuario Bariatra
        $bariatra = User::create([
            'name' => 'Dr. Alejandro Rojas',
            'email' => 'bariatra@sistema.com',
            'password' => Hash::make('password123'),
        ]);
        $bariatra->assignRole($roleBariatra);

                // Usuario Paciente
        $usuario = User::create([
            'name' => 'yazmin',
            'email' => 'yazmin@usuario.com',
            'password' => Hash::make('password123'),
        ]);
        $usuario->assignRole($roleUsuario);

        // Usuario Recepcionista
        $recepcionista = User::create([
            'name' => 'Secretaria Ana',
            'email' => 'recepcion@sistema.com',
            'password' => Hash::make('password123'),
        ]);
        $recepcionista->assignRole($roleRecepcionista);

        // Usuario Administrador / Dev
        $admin = User::create([
            'name' => 'Admin Sistema',
            'email' => 'admin@sistema.com',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole($roleAdmin);
    }
}
