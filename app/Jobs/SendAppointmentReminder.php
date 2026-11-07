<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Notifications\RecordatorioCitaPush;
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

        $paciente = $cita->paciente;

        if (! $paciente || ! $paciente->recibir_recordatorios) {
            return;
        }

        $enviado = false;

        // WhatsApp: sólo si el expediente tiene teléfono registrado.
        if (filled($paciente->phone)) {
            $whatsapp->enviar($paciente->phone, $cita->mensaje('recordatorio'), $cita->parametrosPlantilla());
            $enviado = true;
        }

        // Web Push: avisa en el teléfono aunque no haya número en el expediente
        // (una suscripción viva del navegador es suficiente).
        if ($paciente->pushSubscriptions()->exists()) {
            $paciente->notify(new RecordatorioCitaPush($cita));
            $enviado = true;
        }

        if ($enviado) {
            $cita->forceFill(['recordatorio_enviado_en' => now()])->save();
        }
    }

    public function failed(?Throwable $e): void
    {
        report($e);
    }
}
