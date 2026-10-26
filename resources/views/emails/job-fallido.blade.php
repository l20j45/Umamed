@component('mail::message')
# Falló un proceso en segundo plano

Un trabajo de la cola terminó en error después de agotar sus reintentos.

@component('mail::table')
| Dato | Valor |
| :--- | :---- |
| Trabajo | `{{ $trabajo }}` |
| Cola | {{ $cola }} |
| Error | {{ $mensaje }} |
@endcomponent

@component('mail::panel')
{{ $traza }}
@endcomponent

Revisa `/log-viewer` para ver el detalle completo.

{{ config('app.name') }}
@endcomponent
