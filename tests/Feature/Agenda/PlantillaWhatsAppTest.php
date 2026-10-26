<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'whatsapp.driver' => 'cloud',
        'whatsapp.cloud.token' => 'token-de-prueba',
        'whatsapp.cloud.phone_number_id' => '1234567890',
        'whatsapp.cloud.waba_id' => '999888777',
        'whatsapp.cloud.version' => 'v21.0',
        'whatsapp.cloud.plantilla.nombre' => 'recordatorio_cita',
        'whatsapp.cloud.plantilla.idioma' => 'es_MX',
        'whatsapp.cloud.plantilla.categoria' => 'UTILITY',
        'whatsapp.cloud.plantilla.cuerpo' => 'Hola {{1}}, te recordamos tu cita el {{2}} a las {{3}}.',
        'whatsapp.cloud.plantilla.ejemplo' => ['María', '10/11/2026', '10:00'],
    ]);
});

test('envía la plantilla a Meta para aprobación', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'plantilla-1', 'status' => 'PENDING'], 200),
    ]);

    $this->artisan('whatsapp:plantilla', ['--crear' => true])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/999888777/message_templates')
            && $request['name'] === 'recordatorio_cita'
            && $request['language'] === 'es_MX'
            && $request['category'] === 'UTILITY'
            && $request['components'][0]['type'] === 'BODY'
            && $request['components'][0]['text'] === 'Hola {{1}}, te recordamos tu cita el {{2}} a las {{3}}.'
            && $request['components'][0]['example']['body_text'][0] === ['María', '10/11/2026', '10:00'];
    });
});

test('muestra el estado de la plantilla', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [[
                'name' => 'recordatorio_cita',
                'status' => 'APPROVED',
                'language' => 'es_MX',
                'category' => 'UTILITY',
            ]],
        ], 200),
    ]);

    $this->artisan('whatsapp:plantilla')
        ->expectsOutputToContain('APPROVED')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), '/999888777/message_templates'));
});

test('avisa cuando la plantilla todavía no existe', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => []], 200),
    ]);

    $this->artisan('whatsapp:plantilla')
        ->expectsOutputToContain('no existe')
        ->assertSuccessful();
});

test('falla si no hay credenciales de WhatsApp', function () {
    config(['whatsapp.driver' => 'log']);

    $this->artisan('whatsapp:plantilla')->assertExitCode(1);
});

test('rechaza un cuerpo que no tenga tres variables', function () {
    config(['whatsapp.cloud.plantilla.cuerpo' => 'Hola {{1}}, nos vemos pronto.']);

    $this->artisan('whatsapp:plantilla', ['--crear' => true])
        ->expectsOutputToContain('exactamente 3 variables')
        ->assertExitCode(1);
});

test('informa el error de Meta cuando rechaza la solicitud', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400),
    ]);

    $this->artisan('whatsapp:plantilla', ['--crear' => true])->assertExitCode(1);
});
