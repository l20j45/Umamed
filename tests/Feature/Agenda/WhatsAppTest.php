<?php

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
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
        'whatsapp.cloud.plantilla.nombre' => null,
        'whatsapp.recordatorios.activos' => false,
    ]);
});

test('el enlace de whatsapp lleva el número normalizado y el mensaje codificado', function () {
    $enlace = app(WhatsAppService::class)->enlace('55 1234 5678', 'Hola mundo');

    expect($enlace)->toBe('https://wa.me/525512345678?text=Hola%20mundo')
        ->and(app(WhatsAppService::class)->enlace('', 'Hola'))->toBeNull();
});

test('el driver cloud manda el mensaje a la API de Meta', function () {
    Http::fake();

    config([
        'whatsapp.driver' => 'cloud',
        'whatsapp.cloud.token' => 'token-de-prueba',
        'whatsapp.cloud.phone_number_id' => '1234567890',
        'whatsapp.cloud.version' => 'v21.0',
    ]);

    app(WhatsAppService::class)->enviar('5512345678', 'Hola desde la clínica');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
            && $request->method() === 'POST'
            && $request['to'] === '525512345678'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Hola desde la clínica';
    });
});

test('con plantilla configurada el recordatorio usa la plantilla aprobada', function () {
    Http::fake();

    config([
        'whatsapp.driver' => 'cloud',
        'whatsapp.cloud.token' => 'token-de-prueba',
        'whatsapp.cloud.phone_number_id' => '1234567890',
        'whatsapp.cloud.version' => 'v21.0',
        'whatsapp.cloud.plantilla.nombre' => 'recordatorio_cita',
        'whatsapp.cloud.plantilla.idioma' => 'es_MX',
    ]);

    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    $cita = agendarCita($doctora, $paciente, '2026-11-10 16:00:00');

    (new SendAppointmentReminder($cita->id))->handle(app(WhatsAppService::class));

    Http::assertSent(function ($request) {
        $parametros = $request['template']['components'][0]['parameters'] ?? [];

        return $request['type'] === 'template'
            && $request['template']['name'] === 'recordatorio_cita'
            && $request['template']['language']['code'] === 'es_MX'
            && $request['to'] === '525512345678'
            && ($parametros[1]['text'] ?? null) === '10/11/2026'
            && ($parametros[2]['text'] ?? null) === '10:00';
    });
});

test('el recordatorio marca la cita como enviada', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    $cita = agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString());

    (new SendAppointmentReminder($cita->id))->handle(app(WhatsAppService::class));

    expect($cita->fresh()->recordatorio_enviado_en)->not->toBeNull();
});

test('una cita cancelada o sin teléfono no genera recordatorio', function () {
    $doctora = usuarioConRol('bariatra');

    $cancelada = agendarCita(
        $doctora,
        usuarioConRol('paciente', ['phone' => '5512345678']),
        now()->addDay()->toDateTimeString(),
        estado: 'cancelada',
    );

    $sinTelefono = agendarCita(
        $doctora,
        usuarioConRol('paciente', ['phone' => null]),
        now()->addDays(2)->toDateTimeString(),
    );

    (new SendAppointmentReminder($cancelada->id))->handle(app(WhatsAppService::class));
    (new SendAppointmentReminder($sinTelefono->id))->handle(app(WhatsAppService::class));

    expect($cancelada->fresh()->recordatorio_enviado_en)->toBeNull()
        ->and($sinTelefono->fresh()->recordatorio_enviado_en)->toBeNull();
});

test('el comando encola sólo las citas dentro de la ventana de 24 horas', function () {
    Queue::fake();

    config(['whatsapp.recordatorios.activos' => true]);

    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    $enVentana = agendarCita($doctora, $paciente, now()->addHours(24)->addMinutes(5)->toDateTimeString());
    agendarCita($doctora, $paciente, now()->addHours(30)->toDateTimeString());
    agendarCita($doctora, $paciente, now()->addHours(3)->toDateTimeString());

    $this->artisan('citas:recordatorios')->assertSuccessful();

    Queue::assertPushed(SendAppointmentReminder::class, 1);
    Queue::assertPushed(
        SendAppointmentReminder::class,
        fn (SendAppointmentReminder $job) => $job->appointmentId === $enVentana->id,
    );
});

test('el comando no hace nada si los recordatorios están apagados', function () {
    Queue::fake();

    config(['whatsapp.recordatorios.activos' => false]);

    $doctora = usuarioConRol('bariatra');

    agendarCita($doctora, null, now()->addHours(24)->toDateTimeString());

    $this->artisan('citas:recordatorios')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('el endpoint de recordatorio encola el envío para el dueño de la agenda', function () {
    Queue::fake();

    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    $cita = agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString());

    $this->actingAs($doctora)
        ->post(route('agenda.recordatorio', $cita))
        ->assertRedirect();

    Queue::assertPushed(
        SendAppointmentReminder::class,
        fn (SendAppointmentReminder $job) => $job->appointmentId === $cita->id && $job->forzar === true,
    );
});

test('otro doctor no puede disparar el recordatorio de una cita ajena', function () {
    Queue::fake();

    $doctora = usuarioConRol('bariatra');
    $otroDoctor = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    $cita = agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString());

    $this->actingAs($otroDoctor)
        ->post(route('agenda.recordatorio', $cita))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('la agenda expone el enlace de whatsapp y el modo de envío', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    agendarCita($doctora, $paciente, '2026-11-10 16:00:00');

    $this->actingAs($doctora)
        ->get(route('agenda.index', ['mes' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('whatsapp', 'log')
            ->where('citas.0.whatsapp_url', fn ($url) => str_contains((string) $url, 'https://wa.me/525512345678?text=')));
});

test('la cita cancelada no bloquea el envío manual', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['phone' => '5512345678']);

    $cita = agendarCita($doctora, $paciente, now()->addDay()->toDateTimeString(), estado: 'cancelada');

    $this->actingAs($doctora)
        ->post(route('agenda.recordatorio', $cita))
        ->assertSessionHas('error');

    expect(Appointment::query()->firstOrFail()->recordatorio_enviado_en)->toBeNull();
});
