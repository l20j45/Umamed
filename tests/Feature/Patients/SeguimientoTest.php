<?php

use App\Models\DocumentoSeguimiento;
use App\Models\User;
use App\Notifications\PerfilActualizadoPush;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    Storage::fake('local');
});

test('el bariatra sube un pdf de seguimiento al expediente', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => true]);
    $paciente->updatePushSubscription('https://fcm.googleapis.com/fcm/send/seg', 'k', 'a');

    $this->actingAs($bariatra)
        ->post(route('patients.seguimiento.store', $paciente->id), [
            'titulo' => 'Menú de septiembre',
            'categoria' => 'menu',
            'notas' => 'Sigue el plan por 4 semanas.',
            'pdf' => UploadedFile::fake()->create('menu.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $documento = DocumentoSeguimiento::query()->sole();

    expect($documento->titulo)->toBe('Menú de septiembre')
        ->and($documento->uploaded_by)->toBe($bariatra->id)
        ->and(Storage::disk('local')->exists($documento->archivo_path))->toBeTrue()
        ->and((int) $documento->user_id)->toBe($paciente->id);

    Notification::assertSentTo($paciente, PerfilActualizadoPush::class);
});

test('la recepcionista no puede subir documentos', function () {
    $recepcionista = usuarioConRol('recepcionista');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($recepcionista)
        ->post(route('patients.seguimiento.store', $paciente->id), [
            'titulo' => 'Menú',
            'categoria' => 'menu',
            'pdf' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
});

test('sólo se aceptan archivos pdf', function () {
    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($bariatra)
        ->post(route('patients.seguimiento.store', $paciente->id), [
            'titulo' => 'No es pdf',
            'categoria' => 'menu',
            'pdf' => UploadedFile::fake()->create('trampa.docx', 10),
        ])->assertSessionHasErrors(['pdf']);
});

test('el menú exige pdf', function () {
    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($bariatra)
        ->post(route('patients.seguimiento.store', $paciente->id), [
            'titulo' => 'Menú sin archivo',
            'categoria' => 'menu',
            'notas' => 'Sin archivo no hay menú.',
        ])->assertSessionHasErrors(['pdf']);
});

test('pedimentos y notas van sin archivo: sólo comentario', function () {
    Notification::fake();

    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente', ['recibir_recordatorios' => true]);
    $paciente->updatePushSubscription('https://fcm.googleapis.com/fcm/send/seg', 'k', 'a');

    $this->actingAs($bariatra)
        ->post(route('patients.seguimiento.store', $paciente->id), [
            'titulo' => 'Repetir biometría en 15 días',
            'categoria' => 'pedimento',
            'notas' => 'Lleva este comprobante al laboratorio.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $documento = DocumentoSeguimiento::query()->sole();

    expect($documento->archivo_path)->toBeNull()
        ->and($documento->tiene_pdf)->toBeFalse();

    Notification::assertSentTo($paciente, PerfilActualizadoPush::class);
});

test('una nota sin comentario no se acepta', function () {
    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $this->actingAs($bariatra)
        ->post(route('patients.seguimiento.store', $paciente->id), [
            'titulo' => 'Nota vacía',
            'categoria' => 'nota',
        ])->assertSessionHasErrors(['notas']);
});

test('el paciente descarga su documento y no el de otros', function () {
    $paciente = usuarioConRol('paciente');
    $otro = usuarioConRol('paciente');

    $paciente->documentosSeguimiento()->create([
        'titulo' => 'Pedimento de sangre',
        'categoria' => 'pedimento',
        'archivo_path' => 'seguimiento/prueba-1.pdf',
    ]);

    Storage::disk('local')->put('seguimiento/prueba-1.pdf', 'contenido pdf');

    $id = DocumentoSeguimiento::first()->id;

    $this->actingAs($paciente)
        ->get(route('seguimiento.descargar', $id))
        ->assertOk();

    // Otro paciente no puede descargar el documento ajeno.
    $this->actingAs($otro)
        ->get(route('seguimiento.descargar', $id))
        ->assertForbidden();
});

test('el staff que no es dueño también puede descargar (recepcionista consulta)', function () {
    $recepcionista = usuarioConRol('recepcionista');
    $paciente = usuarioConRol('paciente');

    $paciente->documentosSeguimiento()->create([
        'titulo' => 'Nota',
        'categoria' => 'nota',
        'archivo_path' => 'seguimiento/prueba-2.pdf',
    ]);

    Storage::disk('local')->put('seguimiento/prueba-2.pdf', 'contenido pdf');

    $id = DocumentoSeguimiento::first()->id;

    $this->actingAs($recepcionista)
        ->get(route('seguimiento.descargar', $id))
        ->assertOk();
});

test('eliminar el documento borra también el archivo del disco', function () {
    $bariatra = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');

    $documento = $paciente->documentosSeguimiento()->create([
        'titulo' => 'Nota temporal',
        'categoria' => 'nota',
        'archivo_path' => 'seguimiento/prueba-3.pdf',
    ]);

    Storage::disk('local')->put('seguimiento/prueba-3.pdf', 'contenido pdf');

    $this->actingAs($bariatra)
        ->delete(route('patients.seguimiento.destroy', [
            'id' => $paciente->id,
            'documento' => $documento->id,
        ]))->assertRedirect();

    expect(User::find($paciente->id)->documentosSeguimiento()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('seguimiento/prueba-3.pdf'))->toBeFalse();
});

test('el portal del paciente lista sus documentos', function () {
    $paciente = usuarioConRol('paciente');

    $paciente->documentosSeguimiento()->create([
        'titulo' => 'Menú de octubre',
        'categoria' => 'menu',
        'archivo_path' => 'seguimiento/prueba-4.pdf',
    ]);

    $this->actingAs($paciente)
        ->get(route('patient.portal'))
        ->assertInertia(
            fn ($inertia) => $inertia
                ->has('documentos.0', fn ($expect) => $expect
                    ->where('titulo', 'Menú de octubre')
                    ->where('categoria_legible', 'Menú / plan alimenticio')
                    ->etc()),
        );
});
