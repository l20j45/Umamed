<?php

use App\Models\AuditLog;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('el bariatra corrige una medición y se recalcula el IMC', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['estatura' => 1.60]);

    $medida = $paciente->medidas()->create(['peso' => 80, 'imc' => 31.25]);

    $this->actingAs($doctora)
        ->patch(route('patients.measures.update', ['id' => $paciente->id, 'medida' => $medida->id]), [
            'peso' => 64,
            'notes' => 'Corregido por error de captura',
        ])
        ->assertRedirect(route('patients.show', $paciente->id));

    $actualizada = $medida->fresh();

    // 64 / 1.60² = 25.0
    expect((float) $actualizada->peso)->toBe(64.0)
        ->and((float) $actualizada->imc)->toBe(25.0)
        ->and($actualizada->notes)->toBe('Corregido por error de captura');
});

test('el bariatra puede eliminar una medición', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $medida = $paciente->medidas()->create(['peso' => 90, 'imc' => 35]);

    $this->actingAs($doctora)
        ->delete(route('patients.measures.destroy', ['id' => $paciente->id, 'medida' => $medida->id]))
        ->assertRedirect();

    expect($paciente->medidas()->count())->toBe(0)
        ->and(AuditLog::query()->where('accion', 'deleted')->exists())->toBeTrue();
});

test('el bariatra puede capturar o corregir la estatura del paciente', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($doctora)
        ->patch(route('patients.update', $paciente->id), ['estatura' => 1.72])
        ->assertRedirect(route('patients.show', $paciente->id));

    expect((float) $paciente->fresh()->estatura)->toBe(1.72);
});

test('la estatura fuera de rango se rechaza', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($doctora)
        ->patch(route('patients.update', $paciente->id), ['estatura' => 12])
        ->assertSessionHasErrors('estatura');
});

test('un paciente no puede editar mediciones de otro', function () {
    $paciente = usuarioConRol('paciente');
    $otro = usuarioConRol('paciente');

    $medida = $otro->medidas()->create(['peso' => 90, 'imc' => 35]);

    $this->actingAs($paciente)
        ->patch(route('patients.measures.update', ['id' => $otro->id, 'medida' => $medida->id]), [
            'peso' => 70,
        ])
        ->assertForbidden();

    expect((float) $medida->fresh()->peso)->toBe(90.0);
});
