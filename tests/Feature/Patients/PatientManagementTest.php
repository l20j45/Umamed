<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('un invitado es enviado al login al pedir el dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('el dashboard muestra la lista de pacientes al personal', function () {
    $admin = usuarioConRol('admin');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('patients', 1)
            ->where('patients.0.email', $paciente->email));
});

test('un paciente es redirigido del dashboard a su portal', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->get('/dashboard')
        ->assertRedirect(route('patient.portal'));
});

test('el admin puede dar de alta un paciente y se le asigna el rol', function () {
    Mail::fake();

    $admin = usuarioConRol('admin');

    $this->actingAs($admin)
        ->post('/admin/users', [
            'name' => 'Paciente de Prueba',
            'email' => 'nuevo.paciente@example.com',
            'auto_password' => true,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $paciente = User::query()->where('email', 'nuevo.paciente@example.com')->first();

    expect($paciente)->not->toBeNull()
        ->and($paciente->hasRole('paciente'))->toBeTrue();
});

test('la medición calcula el IMC con la estatura real del paciente', function () {
    $admin = usuarioConRol('admin');
    $paciente = usuarioConRol('paciente', ['estatura' => 1.60]);

    $this->actingAs($admin)
        ->post(route('patients.measures.store', $paciente->id), ['peso' => 64])
        ->assertRedirect(route('patients.show', $paciente->id));

    $medida = $paciente->medidas()->first();

    expect($medida)->not->toBeNull()
        ->and((float) $medida->imc)->toBe(25.0);
});

test('un paciente no puede abrir el expediente de otro paciente', function () {
    $intruso = usuarioConRol('paciente');
    $otro = usuarioConRol('paciente');

    $this->actingAs($intruso)
        ->get(route('patients.show', $otro->id))
        ->assertForbidden();
});

test('las fechas del portal se muestran en la zona horaria de la clínica', function () {
    $paciente = usuarioConRol('paciente');

    $medida = $paciente->medidas()->create([
        'peso' => 80,
        'imc' => 30,
    ]);

    // 1 de noviembre 02:00 UTC equivale al 31 de octubre 20:00 en México.
    $medida->forceFill(['created_at' => '2026-11-01 02:00:00'])->save();

    $this->actingAs($paciente)
        ->get(route('patient.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.ultima.fecha', '31/10/2026'));
});

test('el paciente ve su portal con sus fotos de progreso', function () {
    $paciente = usuarioConRol('paciente');

    $paciente->progressPhotos()->create([
        'image_path' => 'private_progress_photos/foto.jpg',
        'taken_at' => now(),
    ]);

    $this->actingAs($paciente)
        ->get(route('patient.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/portal')
            ->has('photos', 1)
            ->has('photos.0.photo_url'));
});

test('no se puede ver la foto de otro paciente', function () {
    $dueno = usuarioConRol('paciente');
    $intruso = usuarioConRol('paciente');

    $foto = $dueno->progressPhotos()->create([
        'image_path' => 'private_progress_photos/foto.jpg',
        'taken_at' => now(),
    ]);

    $this->actingAs($intruso)
        ->get(route('patient.photos.show', $foto->id))
        ->assertForbidden();
});
