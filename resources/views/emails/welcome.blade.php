@component('mail::message')
# Hola, {{ $name }}

Te damos la bienvenida a **{{ config('app.name') }}**. Tu cuenta ha sido creada exitosamente por tu especialista.

A continuación, tus credenciales de acceso:

@component('mail::panel')
**Usuario:** {{ $email }}  
**Contraseña:** {{ $password }}
@endcomponent

@component('mail::button', ['url' => config('app.url') . '/login'])
Acceder a mi Portal
@endcomponent

Si tienes alguna duda sobre tu tratamiento o acceso, por favor contacta a tu clínica.

Gracias,<br>
El equipo de {{ config('app.name') }}
@endcomponent