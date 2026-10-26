<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndUsersSeeder extends Seeder
{
    /**
     * Permisos que conoce el sistema.
     *
     * @var list<string>
     */
    private array $permissions = [
        'manage patients',
        'manage consultations',
        'schedule appointments',
        'view dashboard',
        'view profile',
        'schedule my appointments',
    ];

    /**
     * Ejecuta el seeder de roles, permisos y usuarios de prueba.
     *
     * Es idempotente: puede correrse varias veces sin duplicar registros.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // El bariatra puede hacer todo lo clínico y ver su agenda.
        $bariatra = Role::findOrCreate('bariatra', 'web');
        $bariatra->syncPermissions([
            'manage patients',
            'manage consultations',
            'schedule appointments',
            'view dashboard',
        ]);

        // El administrador tiene control total.
        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions($this->permissions);

        $recepcionista = Role::findOrCreate('recepcionista', 'web');
        $recepcionista->syncPermissions(['schedule appointments', 'view dashboard']);

        $paciente = Role::findOrCreate('paciente', 'web');
        $paciente->syncPermissions(['view profile', 'schedule my appointments']);

        $doctora = $this->crearUsuario('Dr. Alejandro Rojas', 'bariatra@sistema.com', $bariatra);
        $this->crearUsuario('Secretaria Ana', 'recepcion@sistema.com', $recepcionista);
        $this->crearUsuario('Admin Sistema', 'admin@sistema.com', $admin);
        $this->crearUsuario('yazmin', 'yazmin@usuario.com', $paciente);

        $this->crearCatalogoDeEjemplo($doctora);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Catálogo de conceptos de muestra para el doctor de demostración.
     */
    private function crearCatalogoDeEjemplo(User $bariatra): void
    {
        $conceptos = [
            [
                'nombre' => 'Consulta de primera vez',
                'tipo' => 'consulta',
                'precio' => 900,
                'descripcion' => 'Valoración inicial con mediciones y plan de tratamiento.',
            ],
            [
                'nombre' => 'Consulta de seguimiento',
                'tipo' => 'consulta',
                'precio' => 600,
                'descripcion' => 'Revisión de avances y ajuste del plan.',
            ],
            [
                'nombre' => 'Tratamiento GLP-1 (mensual)',
                'tipo' => 'tratamiento',
                'precio' => 2500,
                'descripcion' => 'Seguimiento mensual con ajuste de dosis.',
            ],
            [
                'nombre' => 'Proteína de suero 1 kg',
                'tipo' => 'producto',
                'precio' => 850,
                'descripcion' => 'Suplemento de proteína para el plan nutricional.',
            ],
        ];

        foreach ($conceptos as $concepto) {
            CatalogItem::query()->firstOrCreate(
                ['user_id' => $bariatra->id, 'nombre' => $concepto['nombre']],
                $concepto,
            );
        }
    }

    /**
     * Crea el usuario si no existe y le asegura el rol indicado.
     */
    private function crearUsuario(string $name, string $email, Role $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(config('clinic.seeder_password')),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
