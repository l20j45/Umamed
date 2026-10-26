<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'patients/*'],

    'allowed_methods' => ['*'],

    // 👇 Permite tu URL del .env (donde pondrás el de ngrok) y tus entornos locales
    'allowed_origins' => [
        '*',

    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // ⚠️ OBLIGATORIO: En true para que Inertia pueda compartir las cookies de sesión
    'supports_credentials' => false,

];
