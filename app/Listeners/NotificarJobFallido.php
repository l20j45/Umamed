<?php

namespace App\Listeners;

use App\Mail\JobFallido;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificarJobFallido
{
    public function handle(JobFailed $evento): void
    {
        $job = $evento->job;

        // El evento puede traer el envoltorio de la cola o el objeto del job.
        $trabajo = is_object($job) && method_exists($job, 'resolveName')
            ? (string) $job->resolveName()
            : (is_object($job) ? $job::class : 'desconocido');

        $cola = is_object($job) && method_exists($job, 'getQueue')
            ? (string) $job->getQueue()
            : 'default';

        $mensaje = $evento->exception->getMessage();

        // Siempre queda en el log, aunque no haya correo configurado.
        Log::error('Job fallido', [
            'trabajo' => $trabajo,
            'cola' => $cola,
            'error' => $mensaje,
        ]);

        $correo = config('clinic.correo_admin');

        if (blank($correo)) {
            return;
        }

        try {
            Mail::to($correo)->send(new JobFallido(
                $trabajo,
                $cola,
                $mensaje,
                $evento->exception->getFile().':'.$evento->exception->getLine()."\n\n"
                    .mb_substr($evento->exception->getTraceAsString(), 0, 4000),
            ));
        } catch (Throwable $e) {
            // Si el correo también falla, no queremos tumbar al worker.
            report($e);
        }
    }
}
