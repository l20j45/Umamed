<?php

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Spatie\Permission\Models\Role::findOrCreate($role, 'web');
    }
});

test('el bariatra puede guardar observaciones, dieta y recomendaciones y exportarlas en pdf', function () {
    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($bariatra)
        ->post(route('patients.observations.store', $paciente->id), [
            'titulo' => 'Seguimiento 04',
            'observaciones' => 'Ha bajado 1.2 kg en la última quincena y presenta mejor energía.',
            'dieta' => 'Dieta 1,800 kcal, 120 g de proteína, 4 comidas diarias y mayor hidratación.',
            'recomendaciones' => 'Caminar 25 minutos diarios, 2 litros de agua y no saltear la comida principal.',
            'formato' => 'estandar',
        ])
        ->assertRedirect(route('patients.show', $paciente->id))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('paciente_observaciones', [
        'user_id' => $paciente->id,
        'created_by' => $bariatra->id,
        'titulo' => 'Seguimiento 04',
    ]);

    $observacion = $paciente->observaciones()->first();

    $this->actingAs($bariatra)
        ->get(route('patients.observations.pdf', ['id' => $paciente->id, 'observacion' => $observacion->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('el paciente descarga el pdf de sus propios seguimientos desde su portal', function () {
    $paciente = usuarioConRol('paciente');
    $bariatra = usuarioConRol('bariatra');

    $observacion = $paciente->observaciones()->create([
        'created_by' => $bariatra->id,
        'titulo' => 'Seguimiento 01',
        'observaciones' => 'Evolución estable.',
    ]);

    $this->actingAs($paciente)
        ->get(route('patients.observations.pdf', ['id' => $paciente->id, 'observacion' => $observacion->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('un paciente no puede descargar el seguimiento de otro paciente', function () {
    $intruso = usuarioConRol('paciente');
    $otro = usuarioConRol('paciente');
    $bariatra = usuarioConRol('bariatra');

    $observacion = $otro->observaciones()->create([
        'created_by' => $bariatra->id,
        'titulo' => 'Seguimiento privado',
        'observaciones' => 'Información clínica de otra persona.',
    ]);

    $this->actingAs($intruso)
        ->get(route('patients.observations.pdf', ['id' => $otro->id, 'observacion' => $observacion->id]))
        ->assertForbidden();
});
