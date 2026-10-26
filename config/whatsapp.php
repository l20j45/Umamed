<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo de envío
    |--------------------------------------------------------------------------
    |
    | "cloud" envía los mensajes desde el servidor con la API oficial de
    | WhatsApp Cloud (Meta). "log" sólo los escribe en el log: sirve para
    | desarrollo o mientras no haya credenciales. En ambos casos la agenda
    | muestra un botón wa.me para enviarlos a mano.
    |
    | Supported: "cloud", "log"
    |
    */

    'driver' => env('WHATSAPP_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Código de país
    |--------------------------------------------------------------------------
    |
    | Se antepone a los teléfonos locales de 10 dígitos (México = 52).
    |
    */

    'pais' => env('WHATSAPP_PAIS', '52'),

    /*
    |--------------------------------------------------------------------------
    | Credenciales de WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Se obtienen en https://developers.facebook.com/apps dentro del producto
    | "WhatsApp". El token permanente se genera con un usuario de sistema.
    |
    */

    'cloud' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        // WhatsApp Business Account ID: hace falta para dar de alta la plantilla.
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),

        /*
        | Plantilla aprobada para los recordatorios. Meta sólo permite texto
        | libre dentro de las 24 h posteriores al último mensaje del paciente;
        | fuera de esa ventana los mensajes iniciados por el negocio deben usar
        | una plantilla registrada y aprobada.
        |
        | El cuerpo debe tener 3 variables y en este orden:
        | {{1}} nombre del paciente, {{2}} fecha, {{3}} hora.
        |
        | `php artisan whatsapp:plantilla --crear` envía esta definición a Meta
        | para su aprobación, y `php artisan whatsapp:plantilla` consulta en qué
        | estado va. Si `nombre` queda vacío, la app manda texto libre (sólo
        | funciona dentro de la ventana de 24 h).
        |
        */

        'plantilla' => [
            'nombre' => env('WHATSAPP_PLANTILLA'),
            'idioma' => env('WHATSAPP_PLANTILLA_IDIOMA', 'es_MX'),
            'categoria' => env('WHATSAPP_PLANTILLA_CATEGORIA', 'UTILITY'),
            'cuerpo' => 'Hola {{1}}, te recordamos tu cita el {{2}} a las {{3}}. Si necesitas cambiarla, respóndenos por este medio.',
            // Valores de muestra que Meta exige para revisar la plantilla.
            'ejemplo' => ['María', '10/11/2026', '10:00'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recordatorios automáticos
    |--------------------------------------------------------------------------
    |
    | El comando `citas:recordatorios` corre cada 15 minutos y encola un aviso
    | para las citas que empiezan dentro de "horas_antes". Se activa solo si
    | el driver es "cloud", salvo que se indique lo contrario con
    | WHATSAPP_RECORDATORIOS.
    |
    */

    'recordatorios' => [
        'activos' => (bool) env('WHATSAPP_RECORDATORIOS', env('WHATSAPP_DRIVER', 'log') === 'cloud'),
        'horas_antes' => (int) env('WHATSAPP_HORAS_ANTES', 24),
        'ventana_minutos' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plantillas de mensaje
    |--------------------------------------------------------------------------
    |
    | Marcadores disponibles: :paciente, :doctor, :clinica, :fecha, :hora.
    |
    */

    'plantillas' => [
        'recordatorio' => 'Hola :paciente 👋, te recordamos tu cita en :clinica con :doctor el :fecha a las :hora. Si necesitas cambiarla, respóndenos por este medio.',
        'confirmacion' => 'Hola :paciente 👋, tu cita en :clinica quedó confirmada para el :fecha a las :hora con :doctor. ¡Te esperamos!',
        'cancelacion' => 'Hola :paciente, tu cita del :fecha a las :hora en :clinica fue cancelada. Escríbenos para reagendar.',
    ],

];
