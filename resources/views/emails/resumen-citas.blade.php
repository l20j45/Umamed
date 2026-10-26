@component('mail::message')
# Hola, {{ $doctor }}

Este es tu resumen de citas para el **{{ $fecha }}**.

@if (count($citas) === 0)
No tienes citas agendadas para hoy.
@else
@component('mail::table')
| Hora | Paciente | Tipo | Estado |
| :--- | :------- | :--- | :----- |
@foreach ($citas as $cita)
| {{ $cita['hora'] }} | {{ $cita['paciente'] }} | {{ $cita['tipo'] }} | {{ $cita['estado'] }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => rtrim(config('app.url'), '/').'/agenda'])
Ver la agenda completa
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
