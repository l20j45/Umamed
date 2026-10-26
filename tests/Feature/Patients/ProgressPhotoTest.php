<?php

use App\Models\ProgressPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Storage::fake('local');
});

/**
 * Ruta absoluta del archivo que respalda a una foto.
 */
function rutaDeFoto(ProgressPhoto $foto): string
{
    return Storage::disk('local')->path($foto->image_path);
}

test('la foto se guarda como JPEG sin metadatos EXIF y con la orientación corregida', function () {
    $paciente = usuarioConRol('paciente');

    $fixture = base_path('tests/Fixtures/fotos/con-exif.jpg');
    $archivo = new UploadedFile($fixture, 'foto.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);

    $this->actingAs($paciente)
        ->post(route('patient.photos.store'), [
            'photo' => $archivo,
            'taken_at' => '2026-10-20',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $foto = $paciente->progressPhotos()->firstOrFail();
    $ruta = rutaDeFoto($foto);

    expect(str_ends_with($foto->image_path, '.jpg'))->toBeTrue()
        ->and(file_exists($ruta))->toBeTrue();

    $info = getimagesize($ruta);

    // Sigue siendo JPEG y quedó rotada 90°: 1200x600 -> 600x1200.
    expect($info[2])->toBe(IMAGETYPE_JPEG)
        ->and($info[0])->toBe(600)
        ->and($info[1])->toBe(1200);

    $exif = @exif_read_data($ruta);

    expect(is_array($exif) ? ($exif['Orientation'] ?? null) : null)->toBeNull()
        ->and(is_array($exif) ? ($exif['Make'] ?? null) : null)->toBeNull()
        ->and(is_array($exif) ? ($exif['Model'] ?? null) : null)->toBeNull();
});

test('una foto grande se reduce al lado máximo configurado', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->post(route('patient.photos.store'), [
            'photo' => UploadedFile::fake()->image('grande.jpg', 2000, 1200),
            'taken_at' => '2026-10-21',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $info = getimagesize(rutaDeFoto($paciente->progressPhotos()->firstOrFail()));

    expect(max($info[0], $info[1]))->toBe(config('clinic.foto_lado_maximo'))
        ->and($info[0])->toBe(1600)
        ->and($info[1])->toBe(960);
});

test('un PNG también se guarda como JPEG', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->post(route('patient.photos.store'), [
            'photo' => UploadedFile::fake()->image('foto.png', 800, 600),
            'taken_at' => '2026-10-22',
        ])
        ->assertSessionHasNoErrors();

    $foto = $paciente->progressPhotos()->firstOrFail();

    expect(str_ends_with($foto->image_path, '.jpg'))->toBeTrue()
        ->and(getimagesize(rutaDeFoto($foto))[2])->toBe(IMAGETYPE_JPEG);
});

test('un archivo que no es imagen se rechaza', function () {
    $paciente = usuarioConRol('paciente');

    $this->actingAs($paciente)
        ->post(route('patient.photos.store'), [
            'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            'taken_at' => '2026-10-23',
        ])
        ->assertSessionHasErrors('photo');

    expect($paciente->progressPhotos()->count())->toBe(0);
});

test('al eliminar la foto también se borra el archivo del disco', function () {
    $paciente = usuarioConRol('paciente');

    Storage::disk('local')->put('private_progress_photos/vieja.jpg', 'contenido');

    $foto = $paciente->progressPhotos()->create([
        'image_path' => 'private_progress_photos/vieja.jpg',
        'taken_at' => now(),
    ]);

    Storage::disk('local')->assertExists('private_progress_photos/vieja.jpg');

    $this->actingAs($paciente)
        ->delete(route('patient.photos.destroy', $foto->id))
        ->assertRedirect();

    Storage::disk('local')->assertMissing('private_progress_photos/vieja.jpg');
    expect($foto->fresh()->trashed())->toBeTrue();
});
