<?php

use App\Jobs\SendAppointmentReminder;
use App\Mail\JobFallido;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    config([
        'whatsapp.driver' => 'log',
        'whatsapp.cloud.token' => null,
        'whatsapp.cloud.phone_number_id' => null,
        'whatsapp.recordatorios.activos' => true,
    ]);
});

test('no se envía recordatorio al paciente que lo desactivó', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', [
        'phone' => '5512345678',
        'recibir_recordatorios' => false,
    ]);

    $cita = agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString());

    (new SendAppointmentReminder($cita->id))->handle(app(WhatsAppService::class));

    expect($cita->fresh()->recordatorio_enviado_en)->toBeNull();
});

test('el comando ignora a los pacientes que desactivaron los recordatorios', function () {
    Queue::fake();

    $doctora = usuarioConRol('bariatra');
    $conAviso = usuarioConRol('paciente', ['phone' => '5512345678']);
    $sinAviso = usuarioConRol('paciente', [
        'phone' => '5512345679',
        'recibir_recordatorios' => false,
    ]);

    agendarCita($doctora, $conAviso, now()->addHours(24)->addMinutes(5)->toDateTimeString());
    agendarCita($doctora, $sinAviso, now()->addHours(24)->addMinutes(10)->toDateTimeString());

    $this->artisan('citas:recordatorios')->assertSuccessful();

    Queue::assertPushed(SendAppointmentReminder::class, 1);
});

test('el paciente puede desactivar y volver a activar sus recordatorios', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->patch(route('patient.recordatorios'), ['recibir_recordatorios' => false])
        ->assertRedirect();

    expect((bool) $paciente->fresh()->recibir_recordatorios)->toBeFalse();

    $this->actingAs($paciente)
        ->patch(route('patient.recordatorios'), ['recibir_recordatorios' => true])
        ->assertRedirect();

    expect((bool) $paciente->fresh()->recibir_recordatorios)->toBeTrue();
});

test('el envío manual respeta la decisión del paciente', function () {
    Queue::fake();

    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', [
        'phone' => '5512345678',
        'recibir_recordatorios' => false,
    ]);

    $cita = agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString());

    $this->actingAs($doctora)
        ->post(route('agenda.recordatorio', $cita))
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

test('avisa por correo cuando un job agota sus reintentos', function () {
    Mail::fake();

    config(['clinic.correo_admin' => 'admin@example.com']);

    Event::dispatch(new JobFailed(
        'database',
        new SendAppointmentReminder(1),
        new RuntimeException('WhatsApp rechazó el mensaje'),
    ));

    Mail::assertSent(
        JobFallido::class,
        fn (JobFallido $mail) => $mail->hasTo('admin@example.com')
            && str_contains($mail->mensaje, 'WhatsApp rechazó el mensaje'),
    );
});

test('sin correo de administrador no intenta enviar nada', function () {
    Mail::fake();

    config(['clinic.correo_admin' => null]);

    Event::dispatch(new JobFailed(
        'database',
        new SendAppointmentReminder(1),
        new RuntimeException('falló'),
    ));

    Mail::assertNothingSent();
});

test('el paciente puede desactivar los recordatorios y su registro se audita', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->patch(route('patient.recordatorios'), ['recibir_recordatorios' => false]);

    expect(AuditLog::query()
        ->where('modelo', User::class)
        ->where('accion', 'updated')
        ->exists())->toBeTrue();
});
