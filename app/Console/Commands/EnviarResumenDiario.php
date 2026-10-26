<?php

namespace App\Console\Commands;

use App\Mail\ResumenDiarioDeCitas;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class EnviarResumenDiario extends Command
{
    /**
     * @var string
     */
    protected $signature = 'citas:resumen-diario
                            {--fecha= : Fecha local a resumir (Y-m-d), por defecto hoy}';

    /**
     * @var string
     */
    protected $description = 'Envía a cada doctor el resumen de sus citas del día';

    public function handle(): int
    {
        $zona = Appointment::zonaHoraria();
        $fecha = (string) ($this->option('fecha') ?: now($zona)->format('Y-m-d'));

        $inicio = Carbon::createFromFormat('Y-m-d H:i:s', $fecha.' 00:00:00', $zona)->utc();
        $fin = $inicio->copy()->addDay()->subSecond();

        $grupos = Appointment::query()
            ->with(['paciente:id,name,last_name', 'bariatra:id,name,email'])
            ->ocupanAgenda()
            ->whereBetween('inicia_en', [$inicio, $fin])
            ->orderBy('inicia_en')
            ->get()
            ->groupBy('bariatra_id');

        $enviados = 0;

        foreach ($grupos as $grupo) {
            $bariatra = $grupo->first()->bariatra;

            if (! $bariatra?->email) {
                continue;
            }

            $citas = $grupo->map(static fn (Appointment $cita): array => [
                'hora' => $cita->inicioLocal()->format('H:i'),
                'paciente' => $cita->nombrePaciente(),
                'tipo' => $cita->tipo,
                'estado' => $cita->estado,
            ])->values()->all();

            Mail::to($bariatra->email)->send(
                new ResumenDiarioDeCitas(
                    $bariatra,
                    $citas,
                    Carbon::createFromFormat('Y-m-d', $fecha, $zona)->translatedFormat('l j \d\e F'),
                )
            );

            $enviados++;
        }

        $this->components->info("Resúmenes enviados: {$enviados}");

        return self::SUCCESS;
    }
}
