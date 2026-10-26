<?php

use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('el bariatra descarga el expediente en PDF', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['estatura' => 1.65, 'last_name' => 'López']);

    $paciente->medidas()->create([
        'peso' => 82.5,
        'imc' => 30.3,
        'tSuperior' => 40,
        'notes' => 'Primera consulta',
    ]);

    $respuesta = $this->actingAs($doctora)->get(route('patients.pdf', $paciente->id));

    $respuesta->assertOk();
    $respuesta->assertHeader('content-type', 'application/pdf');

    $contenido = $respuesta->getContent();

    expect(str_starts_with($contenido, '%PDF'))->toBeTrue()
        ->and(strlen($contenido))->toBeGreaterThan(1000);
});

test('el PDF se genera aunque el paciente no tenga mediciones', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $respuesta = $this->actingAs($doctora)->get(route('patients.pdf', $paciente->id));

    $respuesta->assertOk();
    expect(str_starts_with((string) $respuesta->getContent(), '%PDF'))->toBeTrue();
});

test('un paciente no puede descargar el expediente', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->get(route('patients.pdf', $paciente->id))
        ->assertForbidden();
});
