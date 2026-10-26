<?php

use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('el bariatra agenda su propia cita y se guarda en UTC', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($doctora)
        ->post(route('agenda.store'), [
            'user_id' => $paciente->id,
            'bariatra_id' => $doctora->id,
            'tipo' => 'primera_vez',
            'inicia_en' => '2026-11-02T10:00',
            'termina_en' => '2026-11-02T11:00',
            'estado' => 'pendiente',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $cita = Appointment::query()->firstOrFail();

    // México ya no aplica horario de verano: 10:00 local = 16:00 UTC.
    expect($cita->inicia_en->utc()->format('Y-m-d H:i'))->toBe('2026-11-02 16:00')
        ->and($cita->termina_en->utc()->format('Y-m-d H:i'))->toBe('2026-11-02 17:00')
        ->and($cita->bariatra_id)->toBe($doctora->id)
        ->and($cita->user_id)->toBe($paciente->id);
});

test('el bariatra no puede agendar en la agenda de otro doctor', function () {
    $doctora = usuarioConRol('bariatra');
    $otroDoctor = usuarioConRol('bariatra');

    $this->actingAs($doctora)
        ->post(route('agenda.store'), [
            'bariatra_id' => $otroDoctor->id,
            'tipo' => 'seguimiento',
            'inicia_en' => '2026-11-03T09:00',
            'termina_en' => '2026-11-03T10:00',
            'estado' => 'pendiente',
        ])
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->firstOrFail()->bariatra_id)->toBe($doctora->id);
});

test('no se permiten citas encimadas para el mismo doctor', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    agendarCita($doctora, $paciente, '2026-11-04 16:00:00');

    $this->actingAs($doctora)
        ->post(route('agenda.store'), [
            'bariatra_id' => $doctora->id,
            'tipo' => 'seguimiento',
            'inicia_en' => '2026-11-04T10:30',
            'termina_en' => '2026-11-04T11:30',
            'estado' => 'pendiente',
        ])
        ->assertSessionHasErrors('inicia_en');

    expect(Appointment::query()->count())->toBe(1);
});

test('un horario cancelado no bloquea la agenda', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    agendarCita($doctora, $paciente, '2026-11-05 16:00:00', estado: 'cancelada');

    $this->actingAs($doctora)
        ->post(route('agenda.store'), [
            'bariatra_id' => $doctora->id,
            'tipo' => 'seguimiento',
            'inicia_en' => '2026-11-05T10:00',
            'termina_en' => '2026-11-05T11:00',
            'estado' => 'pendiente',
        ])
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->count())->toBe(2);
});

test('la recepcionista puede agendar para cualquier doctor', function () {
    $recepcionista = usuarioConRol('recepcionista');
    $doctora = usuarioConRol('bariatra');

    $this->actingAs($recepcionista)
        ->post(route('agenda.store'), [
            'bariatra_id' => $doctora->id,
            'tipo' => 'valoracion',
            'inicia_en' => '2026-11-06T12:00',
            'termina_en' => '2026-11-06T12:45',
            'estado' => 'confirmada',
        ])
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->firstOrFail()->bariatra_id)->toBe($doctora->id);
});

test('un paciente no puede entrar a la agenda', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)->get(route('agenda.index'))->assertForbidden();
});

test('la agenda del mes sólo trae las citas del rango y del doctor filtrado', function () {
    $doctora = usuarioConRol('bariatra');
    $otroDoctor = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    agendarCita($doctora, $paciente, '2026-11-10 16:00:00');
    agendarCita($doctora, $paciente, '2026-11-20 16:00:00');
    agendarCita($otroDoctor, $paciente, '2026-11-15 16:00:00');
    agendarCita($doctora, $paciente, '2026-12-01 16:00:00');

    $this->actingAs($doctora)
        ->get(route('agenda.index', ['mes' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Agenda/Index')
            ->has('citas', 2));

    $this->actingAs(usuarioConRol('admin'))
        ->get(route('agenda.index', ['mes' => '2026-11', 'bariatra_id' => $otroDoctor->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('citas', 1));
});

test('el bariatra no puede editar la cita de otro doctor', function () {
    $doctora = usuarioConRol('bariatra');
    $otroDoctor = usuarioConRol('bariatra');

    $cita = agendarCita($otroDoctor, null, '2026-11-11 16:00:00');

    $this->actingAs($doctora)
        ->patch(route('agenda.update', $cita), [
            'bariatra_id' => $otroDoctor->id,
            'tipo' => 'seguimiento',
            'inicia_en' => '2026-11-11T11:00',
            'termina_en' => '2026-11-11T12:00',
            'estado' => 'confirmada',
        ])
        ->assertForbidden();
});

test('se puede cambiar el estado de una cita', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-12 16:00:00');

    $this->actingAs($doctora)
        ->patch(route('agenda.update', $cita), [
            'bariatra_id' => $doctora->id,
            'tipo' => 'seguimiento',
            'inicia_en' => '2026-11-12T10:00',
            'termina_en' => '2026-11-12T11:00',
            'estado' => 'confirmada',
        ])
        ->assertSessionHasNoErrors();

    expect($cita->fresh()->estado)->toBe('confirmada');
});

test('cancelar conserva la cita en el historial', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-14 16:00:00');

    $this->actingAs($doctora)
        ->post(route('agenda.cancelar', $cita))
        ->assertRedirect();

    expect($cita->fresh()->estado)->toBe('cancelada')
        ->and(Appointment::query()->count())->toBe(1);
});

test('sólo administración puede borrar una cita definitivamente', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-15 16:00:00');

    $this->actingAs($doctora)
        ->delete(route('agenda.destroy', $cita))
        ->assertForbidden();

    $this->actingAs(usuarioConRol('admin'))
        ->delete(route('agenda.destroy', $cita))
        ->assertRedirect();

    expect(Appointment::query()->count())->toBe(0);
});

test('mover una cita de horario reinicia el recordatorio', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-13 16:00:00');

    $cita->forceFill(['recordatorio_enviado_en' => Carbon::now()])->save();

    $this->actingAs($doctora)
        ->patch(route('agenda.update', $cita), [
            'bariatra_id' => $doctora->id,
            'tipo' => 'seguimiento',
            'inicia_en' => '2026-11-13T12:00',
            'termina_en' => '2026-11-13T13:00',
            'estado' => 'pendiente',
        ])
        ->assertSessionHasNoErrors();

    expect($cita->fresh()->recordatorio_enviado_en)->toBeNull();
});

test('el paciente ve únicamente sus propias citas', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');
    $otroPaciente = usuarioConRol('paciente');

    agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString());
    agendarCita($doctora, $otroPaciente, now()->addDays(2)->toDateTimeString());

    $this->actingAs($paciente)
        ->get(route('patient.citas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/MisCitas')
            ->has('citas', 1));
});
