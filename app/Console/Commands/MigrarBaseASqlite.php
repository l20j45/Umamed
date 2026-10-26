<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrarBaseASqlite extends Command
{
    /**
     * @var string
     */
    protected $signature = 'db:migrar-a-sqlite
                            {archivo? : Ruta del archivo destino (por defecto storage/app/umamed.sqlite)}
                            {--desde=mysql : Conexión de origen}
                            {--fresh : Borra el archivo destino antes de copiar}
                            {--sin-datos : Sólo crea el esquema, sin copiar registros}';

    /**
     * @var string
     */
    protected $description = 'Copia los datos de MySQL (u otra conexión) a una base de datos SQLite';

    public function handle(): int
    {
        $origen = (string) $this->option('desde');

        if (config("database.connections.{$origen}") === null) {
            $this->components->error("No existe la conexión [{$origen}] en config/database.php.");

            return self::FAILURE;
        }

        $destino = $this->rutaDestino();

        if (file_exists($destino) && filesize($destino) > 0) {
            if (! $this->option('fresh')) {
                $this->components->error(
                    "El archivo [{$destino}] ya existe. Vuelve a ejecutar con --fresh para reemplazarlo."
                );

                return self::FAILURE;
            }

            $this->eliminarArchivoSqlite($destino);
        }

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        touch($destino);

        config([
            'database.connections.sqlite_destino' => array_merge(
                config('database.connections.sqlite'),
                ['database' => $destino],
            ),
        ]);

        $this->components->info("Creando el esquema en {$destino}…");

        $codigo = Artisan::call('migrate', ['--database' => 'sqlite_destino', '--force' => true]);

        if ($codigo !== 0) {
            $this->components->error('Falló la migración del esquema destino:');
            $this->line(Artisan::output());

            return self::FAILURE;
        }

        if ($this->option('sin-datos')) {
            $this->components->info('Esquema creado (sin datos).');

            return self::SUCCESS;
        }

        $tablas = collect(Schema::connection($origen)->getTables())
            ->pluck('name')
            ->reject(fn (string $tabla): bool => $tabla === 'migrations')
            ->values();

        if ($tablas->isEmpty()) {
            $this->components->warn("La conexión [{$origen}] no tiene tablas que copiar.");

            return self::SUCCESS;
        }

        $destinoDb = DB::connection('sqlite_destino');
        $destinoDb->statement('PRAGMA foreign_keys = OFF');

        $this->components->info("Copiando {$tablas->count()} tablas…");
        $barra = $this->output->createProgressBar($tablas->count());
        $totales = 0;

        foreach ($tablas as $tabla) {
            if (! Schema::connection('sqlite_destino')->hasTable($tabla)) {
                $this->newLine();
                $this->components->warn("La tabla [{$tabla}] no existe en el destino; se omite.");
                $barra->advance();

                continue;
            }

            $destinoDb->table($tabla)->delete();

            $lote = [];

            foreach (DB::connection($origen)->table($tabla)->cursor() as $fila) {
                $lote[] = (array) $fila;

                if (count($lote) >= 500) {
                    $destinoDb->table($tabla)->insert($lote);
                    $totales += count($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                $destinoDb->table($tabla)->insert($lote);
                $totales += count($lote);
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $destinoDb->statement('PRAGMA foreign_keys = ON');

        // Deja SQLite con WAL, igual que la configuración de la aplicación.
        $destinoDb->statement('PRAGMA journal_mode = WAL');
        $destinoDb->statement('PRAGMA wal_checkpoint(TRUNCATE)');

        $this->components->info("Listo: {$totales} registros copiados a {$destino}.");
        $this->line('Ahora apunta el .env a la nueva base:');
        $this->line('  DB_CONNECTION=sqlite');
        $this->line('  DB_DATABASE='.$destino);

        return self::SUCCESS;
    }

    private function rutaDestino(): string
    {
        $destino = (string) ($this->argument('archivo') ?: config('database.connections.sqlite.database'));

        $esAbsoluta = str_starts_with($destino, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $destino) === 1;

        return $esAbsoluta ? $destino : base_path($destino);
    }

    private function eliminarArchivoSqlite(string $destino): void
    {
        foreach (['', '-wal', '-shm'] as $sufijo) {
            if (file_exists($destino.$sufijo)) {
                @unlink($destino.$sufijo);
            }
        }
    }
}
