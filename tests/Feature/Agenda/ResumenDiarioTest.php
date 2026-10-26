<?php

use App\Mail\ResumenDiarioDeCitas;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

test('envía el resumen sólo a los doctores que tienen citas ese día', function () {
    Mail::fake();

    $doctora = usuarioConRol('bariatra');
    $otroDoctor = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    agendarCita($doctora, $paciente, '2026-11-10 16:00:00');
    agendarCita($doctora, $paciente, '2026-11-10 18:00:00');
    agendarCita($otroDoctor, $paciente, '2026-11-12 16:00:00');

    $this->artisan('citas:resumen-diario', ['--fecha' => '2026-11-10'])->assertSuccessful();

    Mail::assertSent(ResumenDiarioDeCitas::class, function (ResumenDiarioDeCitas $mail) use ($doctora) {
        return $mail->hasTo($doctora->email)
            && count($mail->citas) === 2
            && $mail->citas[0]['hora'] === '10:00'
            && $mail->citas[1]['hora'] === '12:00';
    });

    Mail::assertNotSent(
        ResumenDiarioDeCitas::class,
        fn (ResumenDiarioDeCitas $mail) => $mail->hasTo($otroDoctor->email),
    );

    Mail::assertOutgoingCount(1);
});

test('las citas canceladas no aparecen en el resumen', function () {
    Mail::fake();

    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    agendarCita($doctora, $paciente, '2026-11-10 16:00:00');
    agendarCita($doctora, $paciente, '2026-11-10 18:00:00', estado: 'cancelada');

    $this->artisan('citas:resumen-diario', ['--fecha' => '2026-11-10'])->assertSuccessful();

    Mail::assertSent(
        ResumenDiarioDeCitas::class,
        fn (ResumenDiarioDeCitas $mail) => count($mail->citas) === 1,
    );
});

test('no envía nada si nadie tiene citas', function () {
    Mail::fake();

    usuarioConRol('bariatra');

    $this->artisan('citas:resumen-diario', ['--fecha' => '2026-11-10'])->assertSuccessful();

    Mail::assertNothingSent();
});
