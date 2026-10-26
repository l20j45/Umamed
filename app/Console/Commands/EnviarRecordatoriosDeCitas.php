<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;

class EnviarRecordatoriosDeCitas extends Command
{
    /**
     * @var string
     */
    protected $signature = 'citas:recordatorios
                            {--horas= : Horas de anticipación (por defecto la configuración)}
                            {--probar : Encola los recordatorios sin importar la ventana de tiempo}';

    /**
     * @var string
     */
    protected $description = 'Encola los recordatorios de WhatsApp de las citas próximas';

    public function handle(): int
    {
        if (! config('whatsapp.recordatorios.activos')) {
            $this->components->warn(
                'Los recordatorios automáticos están apagados. Configura WHATSAPP_DRIVER=cloud con sus credenciales o pon WHATSAPP_RECORDATORIOS=true.'
            );

            return self::SUCCESS;
        }

        $horas = (int) ($this->option('horas') ?: config('whatsapp.recordatorios.horas_antes', 24));
        $ventana = (int) config('whatsapp.recordatorios.ventana_minutos', 15);

        $desde = now()->addHours($horas);
        $hasta = $desde->copy()->addMinutes($ventana);

        $consulta = Appointment::query()
            ->ocupanAgenda()
            ->whereNull('recordatorio_enviado_en')
            ->whereNotNull('user_id')
            ->whereHas('paciente', fn ($query) => $query->where('recibir_recordatorios', true));

        if (! $this->option('probar')) {
            $consulta->whereBetween('inicia_en', [$desde, $hasta]);
        } else {
            $consulta->where('inicia_en', '>', now());
        }

        $ids = $consulta->pluck('id');

        $ids->each(fn (int $id) => SendAppointmentReminder::dispatch($id));

        $this->components->info("Recordatorios en cola: {$ids->count()}");

        return self::SUCCESS;
    }
}
