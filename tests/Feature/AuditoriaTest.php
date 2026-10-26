<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\MedidaPaciente;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('registra quién crea y modifica una cita', function () {
    $doctora = usuarioConRol('bariatra');

    $this->actingAs($doctora);

    $cita = agendarCita($doctora, null, '2026-11-20 16:00:00');

    $alta = AuditLog::query()->where('modelo', Appointment::class)->latest('id')->first();

    expect($alta)->not->toBeNull()
        ->and($alta->accion)->toBe('created')
        ->and((int) $alta->user_id)->toBe($doctora->id)
        ->and((int) $alta->modelo_id)->toBe($cita->id);

    $cita->update(['estado' => 'confirmada']);

    $cambio = AuditLog::query()->where('accion', 'updated')->latest('id')->first();

    expect($cambio->cambios['estado'])->toBe(['pendiente', 'confirmada']);
});

test('registra la eliminación de una medición', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $medida = $paciente->medidas()->create(['peso' => 80, 'imc' => 30]);

    $this->actingAs($doctora);

    $medida->delete();

    $registro = AuditLog::query()->where('accion', 'deleted')->latest('id')->first();

    expect($registro->modelo)->toBe(MedidaPaciente::class)
        ->and((int) $registro->user_id)->toBe($doctora->id);
});

test('nunca guarda contraseñas en la bitácora', function () {
    $usuario = usuarioConRol('paciente');

    $usuario->update(['password' => 'nueva-clave-segura']);

    $cambios = AuditLog::query()
        ->where('modelo', User::class)
        ->where('accion', 'updated')
        ->pluck('cambios')
        ->toJson();

    expect($cambios)->not->toContain('password');
});

test('sólo el administrador puede abrir la bitácora', function () {
    $doctora = usuarioConRol('bariatra');

    $this->actingAs($doctora)->get(route('auditoria.index'))->assertForbidden();

    $admin = usuarioConRol('admin');

    $this->actingAs($admin)->get(route('auditoria.index'))->assertOk();
});

test('muestra los movimientos con su autor y el detalle del cambio', function () {
    $doctora = usuarioConRol('bariatra');
    $admin = usuarioConRol('admin');

    $this->actingAs($doctora);

    $cita = agendarCita($doctora, null, '2026-11-20 16:00:00');
    $cita->update(['estado' => 'confirmada']);

    $this->actingAs($admin)
        ->get(route('auditoria.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auditoria/Index')
            ->where('registros.data.0.accion', 'updated')
            ->where('registros.data.0.usuario', $doctora->name)
            ->where('registros.data.0.modelo', 'Appointment')
            ->where('registros.data.0.cambios.estado.0', 'pendiente')
            ->where('registros.data.0.cambios.estado.1', 'confirmada'));
});

test('filtra por acción y por tipo de registro', function () {
    $doctora = usuarioConRol('bariatra');
    $admin = usuarioConRol('admin');

    $this->actingAs($doctora);

    agendarCita($doctora, null, '2026-11-21 16:00:00');

    $paciente = usuarioConRol('paciente');
    $medida = $paciente->medidas()->create(['peso' => 80, 'imc' => 30]);
    $medida->delete();

    $this->actingAs($admin)
        ->get(route('auditoria.index', ['accion' => 'deleted']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('registros.data', 1)
            ->where('registros.data.0.modelo', 'MedidaPaciente'));

    $this->actingAs($admin)
        ->get(route('auditoria.index', ['modelo' => 'Appointment']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('registros.data', 1));
});

test('marca los registros que ya no existen', function () {
    $admin = usuarioConRol('admin');
    $doctora = usuarioConRol('bariatra');

    $cita = agendarCita($doctora, null, '2026-11-22 16:00:00');

    $this->actingAs($admin)->delete(route('agenda.destroy', $cita));

    $this->actingAs($admin)
        ->get(route('auditoria.index', ['accion' => 'deleted']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registros.data.0.referencia', fn (string $texto) => str_contains($texto, 'ya no existe')));
});

test('la referencia de una cita incluye fecha y hora', function () {
    $doctora = usuarioConRol('bariatra');
    $admin = usuarioConRol('admin');

    $this->actingAs($doctora);

    agendarCita($doctora, null, '2026-11-23 16:00:00');

    $this->actingAs($admin)
        ->get(route('auditoria.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registros.data.0.referencia', fn (string $texto) => str_contains($texto, '23/11/2026')));
});
