<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendAppointmentReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $appointmentId, public bool $forzar = false) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $cita = Appointment::query()
            ->with(['paciente', 'bariatra'])
            ->find($this->appointmentId);

        if (! $cita || ! $cita->estaVigente()) {
            return;
        }

        if (! $this->forzar && $cita->recordatorio_enviado_en !== null) {
            return;
        }

        $telefono = $cita->paciente?->phone;

        if (blank($telefono)) {
            return;
        }

        // El paciente puede haber pedido no recibir recordatorios.
        if ($cita->paciente && ! $cita->paciente->recibir_recordatorios) {
            return;
        }

        $whatsapp->enviar($telefono, $cita->mensaje('recordatorio'), $cita->parametrosPlantilla());

        $cita->forceFill(['recordatorio_enviado_en' => now()])->save();
    }

    public function failed(?Throwable $e): void
    {
        report($e);
    }
}
