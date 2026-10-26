<?php

use App\Models\CatalogItem;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function conceptoDe(User $doctor, array $datos = []): CatalogItem
{
    return CatalogItem::create(array_merge([
        'user_id' => $doctor->id,
        'nombre' => 'Consulta de seguimiento',
        'tipo' => 'consulta',
        'precio' => 600,
        'activo' => true,
    ], $datos));
}

test('el bariatra agrega conceptos a su propio catálogo', function () {
    $doctora = usuarioConRol('bariatra');

    $this->actingAs($doctora)
        ->post(route('catalogo.store'), [
            'nombre' => 'Consulta de primera vez',
            'descripcion' => 'Valoración inicial',
            'tipo' => 'consulta',
            'precio' => 900,
            'activo' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $item = CatalogItem::query()->firstOrFail();

    expect($item->nombre)->toBe('Consulta de primera vez')
        ->and((int) $item->user_id)->toBe($doctora->id)
        ->and((float) $item->precio)->toBe(900.0);
});

test('el catálogo exige nombre, tipo y precio', function () {
    $doctora = usuarioConRol('bariatra');

    $this->actingAs($doctora)
        ->post(route('catalogo.store'), [])
        ->assertSessionHasErrors(['nombre', 'tipo', 'precio']);
});

test('un bariatra no puede editar el catálogo de otro', function () {
    $doctora = usuarioConRol('bariatra');
    $otro = usuarioConRol('bariatra');

    $item = conceptoDe($otro);

    $this->actingAs($doctora)
        ->patch(route('catalogo.update', $item), [
            'nombre' => 'Cambiado',
            'tipo' => 'consulta',
            'precio' => 100,
        ])
        ->assertForbidden();

    expect($item->fresh()->nombre)->toBe('Consulta de seguimiento');
});

test('recepción puede ver el catálogo pero no editarlo', function () {
    $doctora = usuarioConRol('bariatra');
    $recepcion = usuarioConRol('recepcionista');

    conceptoDe($doctora);

    $this->actingAs($recepcion)
        ->get(route('catalogo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalogo/Index')
            ->has('items', 1)
            ->where('puedeEditar', false));

    $this->actingAs($recepcion)
        ->post(route('catalogo.store'), [
            'nombre' => 'Intento',
            'tipo' => 'producto',
            'precio' => 10,
        ])
        ->assertForbidden();
});

test('el índice muestra sólo el catálogo del doctor que consulta', function () {
    $doctora = usuarioConRol('bariatra');
    $otro = usuarioConRol('bariatra');

    conceptoDe($doctora);
    conceptoDe($otro, ['nombre' => 'Proteína']);

    $this->actingAs($doctora)
        ->get(route('catalogo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('items', 1));

    $this->actingAs(usuarioConRol('admin'))
        ->get(route('catalogo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('items', 2));
});

test('el administrador puede crear un concepto para un doctor específico', function () {
    $admin = usuarioConRol('admin');
    $doctora = usuarioConRol('bariatra');

    $this->actingAs($admin)
        ->post(route('catalogo.store'), [
            'user_id' => $doctora->id,
            'nombre' => 'Paquete de 3 consultas',
            'tipo' => 'tratamiento',
            'precio' => 1500,
        ])
        ->assertSessionHasNoErrors();

    expect((int) CatalogItem::query()->firstOrFail()->user_id)->toBe($doctora->id);
});

test('el administrador debe elegir el doctor al crear', function () {
    $admin = usuarioConRol('admin');

    $this->actingAs($admin)
        ->post(route('catalogo.store'), [
            'nombre' => 'Sin dueño',
            'tipo' => 'consulta',
            'precio' => 500,
        ])
        ->assertSessionHasErrors('user_id');
});

test('un concepto desactivado deja de ofrecerse pero sigue en el catálogo', function () {
    $doctora = usuarioConRol('bariatra');
    $item = conceptoDe($doctora);

    $this->actingAs($doctora)
        ->patch(route('catalogo.update', $item), [
            'nombre' => $item->nombre,
            'tipo' => $item->tipo,
            'precio' => 700,
            'activo' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->activo)->toBeFalse()
        ->and((float) $item->fresh()->precio)->toBe(700.0);
});
