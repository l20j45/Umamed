<?php

use App\Models\User;
use App\Notifications\PerfilActualizadoPush;
use App\Notifications\RecordatorioCitaPush;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

test('un invitado no puede registrar una suscripción push', function () {
    $this->postJson(route('push.suscribir'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'keys' => ['p256dh' => 'llave', 'auth' => 'token'],
    ])->assertUnauthorized();
});

test('un usuario autenticado registra su suscripción push', function () {
    $usuario = usuarioConRol('paciente');

    $this->actingAs($usuario)
        ->postJson(route('push.suscribir'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'llave', 'auth' => 'token'],
            'content_encoding' => 'aes128gcm',
        ])->assertOk();

    expect($usuario->pushSubscriptions()->count())->toBe(1)
        ->and($usuario->pushSubscriptions()->first()->endpoint)
        ->toBe('https://fcm.googleapis.com/fcm/send/abc');
});

test('la misma suscripción se actualiza sin duplicar el registro', function () {
    $usuario = usuarioConRol('paciente');

    foreach (['llave-nueva', 'llave-renewed'] as $llave) {
        $this->actingAs($usuario)
            ->postJson(route('push.suscribir'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
                'keys' => ['p256dh' => $llave, 'auth' => 'token'],
            ])->assertOk();

        $usuario->refresh();
    }

    $usuario = User::find($usuario->id);

    expect($usuario->pushSubscriptions()->count())->toBe(1)
        ->and($usuario->pushSubscriptions()->first()->public_key)->toBe('llave-renewed');
});

test('borra la suscripción por endpoint', function () {
    $usuario = usuarioConRol('paciente');

    $this->actingAs($usuario)
        ->postJson(route('push.suscribir'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'llave', 'auth' => 'token'],
        ])->assertOk();

    $this->actingAs($usuario)
        ->deleteJson(route('push.eliminar'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        ])->assertOk();

    expect($usuario->pushSubscriptions()->count())->toBe(0);
});

test('el recordatorio va por el canal de Web Push', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => true]);
    $cita = agendarCita($bariatra, $paciente, now()->addDay()->toDateString().' 15:00:00');

    $paciente->notify(new RecordatorioCitaPush($cita));

    Notification::assertSentTo($paciente, RecordatorioCitaPush::class);
});

test('notifica al paciente cuando el doctor registra una medición', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => true]);
    $paciente->updatePushSubscription('https://fcm.googleapis.com/fcm/send/med', 'k', 'a');

    $this->actingAs($bariatra)
        ->post(route('patients.measures.store', $paciente->id), ['peso' => 80])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($paciente, PerfilActualizadoPush::class);
});

test('no notifica si el paciente pidió no recibir avisos', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => false]);
    $paciente->updatePushSubscription('https://fcm.googleapis.com/fcm/send/med', 'k', 'a');

    $this->actingAs($bariatra)
        ->post(route('patients.measures.store', $paciente->id), ['peso' => 80])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertNothingSentTo($paciente);
});

test('notifica cuando la clínica agrega notas a una cita', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => true]);
    $paciente->updatePushSubscription('https://fcm.googleapis.com/fcm/send/med', 'k', 'a');

    $cita = agendarCita($bariatra, $paciente, now()->addDays(3)->format('Y-m-d').' 15:00:00');
    $zona = config('clinic.zona_horaria');

    $this->actingAs($bariatra)
        ->patch(route('agenda.update', $cita), [
            'user_id' => $paciente->id,
            'bariatra_id' => $bariatra->id,
            'tipo' => 'seguimiento',
            'inicia_en' => $cita->inicia_en->timezone($zona)->format('Y-m-d H:i'),
            'termina_en' => $cita->termina_en->timezone($zona)->format('Y-m-d H:i'),
            'estado' => 'pendiente',
            'notas' => 'Traer resultados de laboratorio.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($paciente, PerfilActualizadoPush::class);
});

test('no notifica al editar la cita sin cambiar las notas', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => true]);
    $paciente->updatePushSubscription('https://fcm.googleapis.com/fcm/send/med', 'k', 'a');

    $cita = agendarCita($bariatra, $paciente, now()->addDays(3)->format('Y-m-d').' 15:00:00');
    $cita->forceFill(['notas' => 'Traer resultados de laboratorio.'])->save();
    $zona = config('clinic.zona_horaria');

    $this->actingAs($bariatra)
        ->patch(route('agenda.update', $cita), [
            'user_id' => $paciente->id,
            'bariatra_id' => $bariatra->id,
            'tipo' => 'seguimiento',
            'inicia_en' => $cita->inicia_en->timezone($zona)->format('Y-m-d H:i'),
            'termina_en' => $cita->termina_en->timezone($zona)->format('Y-m-d H:i'),
            'estado' => 'pendiente',
            'notas' => 'Traer resultados de laboratorio.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertNothingSentTo($paciente);
});
