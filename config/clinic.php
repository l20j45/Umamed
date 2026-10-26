<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Estatura por defecto
    |--------------------------------------------------------------------------
    |
    | Estatura (en metros) que se usa para calcular el IMC cuando el paciente
    | todavía no tiene su estatura capturada en el expediente. Capturarla al
    | dar de alta al paciente (campo "estatura") es lo recomendable, porque
    | el IMC sólo es clínicamente válido con la estatura real.
    |
    */

    'estatura_por_defecto' => (float) env('CLINIC_ESTATURA_POR_DEFECTO', 1.70),

    /*
    |--------------------------------------------------------------------------
    | Contraseña de los usuarios de ejemplo
    |--------------------------------------------------------------------------
    |
    | Contraseña (en texto plano) que usan los usuarios creados por el seeder
    | RolesAndUsersSeeder. Cámbiala con la variable SEEDER_PASSWORD si vas a
    | levantar datos de demostración en un servidor accesible.
    |
    */

    'seeder_password' => env('SEEDER_PASSWORD', 'password123'),

    /*
    |--------------------------------------------------------------------------
    | Fotos de progreso
    |--------------------------------------------------------------------------
    |
    | Las fotos se reescriben como JPEG para quitar los metadatos EXIF (GPS y
    | modelo del teléfono incluidos). "foto_lado_maximo" limita el lado mayor
    | en píxeles y "foto_calidad_jpeg" es la calidad de compresión (0-100).
    |
    */

    'foto_lado_maximo' => (int) env('CLINIC_FOTO_LADO_MAXIMO', 1600),

    'foto_calidad_jpeg' => (int) env('CLINIC_FOTO_CALIDAD_JPEG', 85),

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de la clínica
    |--------------------------------------------------------------------------
    |
    | Las fechas se guardan en UTC, pero la agenda captura y muestra las horas
    | en la zona del consultorio. Si la clínica está en otro estado o país,
    | cámbiala aquí (o con CLINIC_TIMEZONE en el .env).
    |
    */

    'zona_horaria' => env('CLINIC_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Hora del resumen diario
    |--------------------------------------------------------------------------
    |
    | Hora local (formato H:i) a la que cada doctor recibe por correo el
    | resumen de sus citas del día.
    |
    */

    'hora_resumen' => env('CLINIC_HORA_RESUMEN', '07:00'),

    /*
    |--------------------------------------------------------------------------
    | Correo del administrador
    |--------------------------------------------------------------------------
    |
    | Recibe el aviso cuando un proceso en segundo plano falla (por ejemplo un
    | recordatorio que WhatsApp rechazó). Si se deja vacío, el error sólo queda
    | en el log.
    |
    */

    'correo_admin' => env('ADMIN_EMAIL'),

];
