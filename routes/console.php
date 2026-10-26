<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Recordatorios de WhatsApp: revisa cada 15 minutos las citas que empiezan
// dentro de la ventana configurada (24 h por defecto).
Schedule::command('citas:recordatorios')->everyFifteenMinutes()->withoutOverlapping();

// Resumen del día para cada doctor, a la hora configurada en su zona horaria.
Schedule::command('citas:resumen-diario')
    ->dailyAt(config('clinic.hora_resumen', '07:00'))
    ->timezone(config('clinic.zona_horaria', 'America/Mexico_City'))
    ->withoutOverlapping();

// Respaldo diario de la base y las fotos (se conservan los últimos 7).
Schedule::command('respaldo:crear')
    ->dailyAt('02:00')
    ->timezone(config('clinic.zona_horaria', 'America/Mexico_City'))
    ->withoutOverlapping();
