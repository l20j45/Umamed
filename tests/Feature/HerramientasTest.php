<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

test('migra los datos de una conexión a un archivo sqlite nuevo', function () {
    $origen = storage_path('app/prueba-origen.sqlite');
    $destino = storage_path('app/prueba-destino.sqlite');

    foreach ([$origen, $destino] as $archivo) {
        foreach (['', '-wal', '-shm'] as $sufijo) {
            @unlink($archivo.$sufijo);
        }
    }

    touch($origen);

    config(['database.connections.origen' => array_merge(
        config('database.connections.sqlite'),
        ['database' => $origen],
    )]);

    Artisan::call('migrate', ['--database' => 'origen', '--force' => true]);

    DB::connection('origen')->table('users')->insert([
        'name' => 'Paciente Origen',
        'email' => 'origen@example.com',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('db:migrar-a-sqlite', [
        'archivo' => $destino,
        '--desde' => 'origen',
    ])->assertSuccessful();

    $pdo = new PDO('sqlite:'.$destino);
    $total = (int) $pdo->query('select count(*) from users')->fetchColumn();
    $correo = $pdo->query('select email from users limit 1')->fetchColumn();

    expect($total)->toBe(1)
        ->and($correo)->toBe('origen@example.com');

    foreach ([$origen, $destino] as $archivo) {
        foreach (['', '-wal', '-shm'] as $sufijo) {
            @unlink($archivo.$sufijo);
        }
    }
});

test('no sobrescribe un archivo destino existente sin --fresh', function () {
    $destino = storage_path('app/prueba-destino2.sqlite');
    @unlink($destino);
    file_put_contents($destino, 'contenido-previo');

    $this->artisan('db:migrar-a-sqlite', ['archivo' => $destino])
        ->assertExitCode(1);

    expect(file_get_contents($destino))->toBe('contenido-previo');

    @unlink($destino);
});

test('el respaldo copia las fotos y purga los respaldos viejos', function () {
    $fotos = storage_path('app/private_progress_photos');
    File::ensureDirectoryExists($fotos);
    File::put($fotos.'/foto-de-prueba.jpg', 'contenido');

    $viejo = storage_path('app/backups/2020-01-01_000000');
    File::ensureDirectoryExists($viejo);

    $this->artisan('respaldo:crear', ['--conservar' => 1])->assertSuccessful();

    $respaldos = collect(File::directories(storage_path('app/backups')));

    expect($respaldos)->toHaveCount(1);

    $nuevo = (string) $respaldos->first();

    expect(file_exists($nuevo.'/private_progress_photos/foto-de-prueba.jpg'))->toBeTrue();

    File::deleteDirectory(storage_path('app/backups'));
    File::deleteDirectory($fotos);
});
