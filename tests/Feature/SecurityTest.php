<?php

use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('el visor de logs no está abierto a invitados', function () {
    $this->get('/log-viewer')->assertForbidden();
});

test('el visor de logs no está abierto a los pacientes', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)->get('/log-viewer')->assertForbidden();
});

test('el administrador sí puede abrir el visor de logs', function () {
    $admin = usuarioConRol('admin');

    $this->actingAs($admin)->get('/log-viewer')->assertOk();
});
