<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CrearRespaldo extends Command
{
    /**
     * @var string
     */
    protected $signature = 'respaldo:crear
                            {--conservar=7 : Cuántos respaldos conservar}
                            {--sin-fotos : No copiar las fotos, sólo la base}';

    /**
     * @var string
     */
    protected $description = 'Respalda la base de datos y las fotos en storage/app/backups';

    public function handle(): int
    {
        $marca = now()->format('Y-m-d_His');
        $destino = storage_path('app/backups/'.$marca);

        File::ensureDirectoryExists($destino);

        // 1. Base de datos
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->respaldarSqlite($destino);
        } else {
            $this->components->warn('La base no es SQLite: respalda la base con mysqldump (ver README).');
        }

        // 2. Fotos y avatares
        if (! $this->option('sin-fotos')) {
            $this->copiarCarpeta(storage_path('app/private_progress_photos'), $destino.'/private_progress_photos');
            $this->copiarCarpeta(storage_path('app/avatars'), $destino.'/avatars');
        }

        $this->components->info("Respaldo creado en {$destino}");

        $this->purgarAntiguos((int) $this->option('conservar'));

        return self::SUCCESS;
    }

    private function respaldarSqlite(string $destino): void
    {
        $ruta = (string) DB::connection()->getDatabaseName();

        if ($ruta === ':memory:' || ! file_exists($ruta)) {
            $this->components->warn('La base SQLite vive en memoria o no existe como archivo; no hay qué copiar.');

            return;
        }

        // Vuelca el WAL al archivo principal para que la copia quede completa.
        try {
            DB::connection()->statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable) {
            // Si falla el checkpoint se copia igual: el -wal se copia aparte.
        }

        File::copy($ruta, $destino.'/umamed.sqlite');
        $this->components->info('Base SQLite copiada.');
    }

    private function copiarCarpeta(string $origen, string $destino): void
    {
        if (! is_dir($origen)) {
            return;
        }

        File::copyDirectory($origen, $destino);
    }

    private function purgarAntiguos(int $conservar): void
    {
        $conservar = max(1, $conservar);

        $respaldos = collect(File::directories(storage_path('app/backups')))
            ->sortDesc()
            ->values();

        $respaldos->slice($conservar)->each(function (string $ruta): void {
            File::deleteDirectory($ruta);
            $this->components->info('Respaldo antiguo eliminado: '.basename($ruta));
        });
    }
}
