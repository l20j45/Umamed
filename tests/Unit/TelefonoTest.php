<?php

use App\Support\Telefono;

test('normaliza teléfonos locales, con lada y con formato internacional', function () {
    expect(Telefono::normalizar('55 1234 5678'))->toBe('525512345678')
        ->and(Telefono::normalizar('+52 55 1234 5678'))->toBe('525512345678')
        ->and(Telefono::normalizar('(55) 1234-5678'))->toBe('525512345678')
        // El "1" que México usaba para celulares antes de 2019.
        ->and(Telefono::normalizar('521 55 1234 5678'))->toBe('525512345678')
        // Prefijo internacional marcado con 00.
        ->and(Telefono::normalizar('00525512345678'))->toBe('525512345678')
        ->and(Telefono::normalizar(''))->toBeNull()
        ->and(Telefono::normalizar(null))->toBeNull()
        ->and(Telefono::normalizar('abc'))->toBeNull();
});

test('formatea el número para mostrarlo en pantalla', function () {
    expect(Telefono::formatear('5512345678'))->toBe('+52 55 1234 5678')
        ->and(Telefono::formatear(''))->toBeNull();
});
