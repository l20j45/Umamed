<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="theme-color" content="#243830">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (config('webpush.vapid.public_key'))
            <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        @endif

        <title inertia>{{ config('app.name', 'Umamed | Bariatría y Nutrición') }}</title>

        <link rel="icon" type="image/svg+xml" href="/icons/health-mark.svg">
        <link rel="shortcut icon" href="/icons/health-mark.svg">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600|lora:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])

        @inertiaHead
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        @inertia
    </body>
</html>
