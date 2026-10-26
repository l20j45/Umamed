<?php

use App\Models\User;

test('la aplicación está configurada en español', function () {
    expect(app()->getLocale())->toBe('es');
});

test('el mensaje de credenciales incorrectas está traducido', function () {
    $user = User::factory()->create();

    $this->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ])
        ->assertSessionHasErrors([
            'email' => 'Estas credenciales no coinciden con nuestros registros.',
        ]);
});

test('los mensajes de validación del framework salen en español', function () {
    $this->from('/login')
        ->post('/login', [])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))
        ->toBe('El campo correo electrónico es obligatorio.');
});

test('el mensaje de la contraseña incorrecta al confirmar está en español', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'incorrecta',
            'password' => 'nueva-contraseña',
            'password_confirmation' => 'nueva-contraseña',
        ])
        ->assertSessionHasErrors('current_password');

    expect(session('errors')->first('current_password'))
        ->toBe('La contraseña es incorrecta.');
});
