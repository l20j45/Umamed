<?php

use App\Models\CatalogItem;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'bariatra', 'recepcionista', 'paciente'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function concepto(User $doctor, ?float $precio = 600.0): CatalogItem
{
    return CatalogItem::create([
        'user_id' => $doctor->id,
        'nombre' => 'Consulta',
        'tipo' => 'consulta',
        'precio' => $precio,
        'activo' => true,
    ]);
}

test('el bariatra agrega conceptos a su cita y se calcula el total', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');
    $cita = agendarCita($doctora, $paciente, '2026-11-10 16:00:00');

    $consulta = concepto($doctora, 900);
    $proteina = concepto($doctora, 850);

    $this->actingAs($doctora)
        ->post(route('agenda.cobro.store', $cita), ['catalog_item_id' => $consulta->id, 'cantidad' => 1])
        ->assertSessionHasNoErrors();

    $this->actingAs($doctora)
        ->post(route('agenda.cobro.store', $cita), ['catalog_item_id' => $proteina->id, 'cantidad' => 2])
        ->assertSessionHasNoErrors();

    expect($cita->fresh()->total())->toBe(2600.0)
        ->and($cita->charges()->count())->toBe(2);

    $this->actingAs($doctora)
        ->get(route('agenda.cobro', $cita))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Agenda/Cobro')
            ->where('cita.total', 2600)
            ->has('cargos', 2));
});

test('no se puede cobrar un concepto del catálogo de otro doctor', function () {
    $doctora = usuarioConRol('bariatra');
    $otro = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-11 16:00:00');

    $ajeno = concepto($otro);

    $this->actingAs($doctora)
        ->post(route('agenda.cobro.store', $cita), ['catalog_item_id' => $ajeno->id, 'cantidad' => 1])
        ->assertSessionHasErrors('catalog_item_id');

    expect($cita->charges()->count())->toBe(0);
});

test('recepción puede cobrar cualquier cita', function () {
    $doctora = usuarioConRol('bariatra');
    $recepcion = usuarioConRol('recepcionista');
    $cita = agendarCita($doctora, null, '2026-11-12 16:00:00');
    $item = concepto($doctora, 500);

    $this->actingAs($recepcion)
        ->post(route('agenda.cobro.store', $cita), ['catalog_item_id' => $item->id, 'cantidad' => 1])
        ->assertSessionHasNoErrors();

    expect($cita->fresh()->total())->toBe(500.0);
});

test('un paciente no puede entrar al cobro de una cita', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente = usuarioConRol('paciente');
    $cita = agendarCita($doctora, $paciente, '2026-11-13 16:00:00');

    $this->actingAs($paciente)
        ->get(route('agenda.cobro', $cita))
        ->assertForbidden();
});

test('no se puede marcar pagada una cita sin conceptos', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-14 16:00:00');

    $this->actingAs($doctora)
        ->post(route('agenda.pago', $cita), ['pagado' => true, 'metodo_pago' => 'efectivo'])
        ->assertSessionHas('error');

    expect($cita->fresh()->estaPagada())->toBeFalse();
});

test('marcar pagada guarda fecha y método de pago', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-15 16:00:00');
    $cita->charges()->create([
        'nombre' => 'Consulta',
        'tipo' => 'consulta',
        'precio_unitario' => 900,
        'cantidad' => 1,
    ]);

    $this->actingAs($doctora)
        ->post(route('agenda.pago', $cita), ['pagado' => true, 'metodo_pago' => 'transferencia'])
        ->assertSessionHas('success');

    $actualizada = $cita->fresh();

    expect($actualizada->estaPagada())->toBeTrue()
        ->and($actualizada->metodo_pago)->toBe('transferencia')
        ->and($actualizada->pagado_en?->format('Y-m-d'))->toBe(now()->toDateString());
});

test('quitar un concepto actualiza el total', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-16 16:00:00');

    $cargo = $cita->charges()->create([
        'nombre' => 'Producto',
        'tipo' => 'producto',
        'precio_unitario' => 400,
        'cantidad' => 2,
    ]);

    expect($cita->fresh()->total())->toBe(800.0);

    $this->actingAs($doctora)
        ->delete(route('agenda.cobro.destroy', ['cita' => $cita->id, 'cargo' => $cargo->id]))
        ->assertSessionHas('success');

    expect($cita->fresh()->total())->toBe(0.0);
});

test('la cantidad de un concepto se puede corregir', function () {
    $doctora = usuarioConRol('bariatra');
    $cita = agendarCita($doctora, null, '2026-11-17 16:00:00');

    $cargo = $cita->charges()->create([
        'nombre' => 'Proteína',
        'tipo' => 'producto',
        'precio_unitario' => 850,
        'cantidad' => 1,
    ]);

    $this->actingAs($doctora)
        ->patch(route('agenda.cobro.update', ['cita' => $cita->id, 'cargo' => $cargo->id]), ['cantidad' => 3])
        ->assertSessionHasNoErrors();

    expect($cargo->fresh()->subtotal)->toBe(2550.0);
});

test('el resumen de ingresos suma facturado, cobrado y pendiente', function () {
    $doctora = usuarioConRol('bariatra');

    $pagada = agendarCita($doctora, null, '2026-11-10 16:00:00');
    $pagada->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 900, 'cantidad' => 1]);
    $pagada->update(['pagado' => true, 'pagado_en' => now()->toDateString(), 'metodo_pago' => 'efectivo']);

    $pendiente = agendarCita($doctora, null, '2026-11-12 16:00:00');
    $pendiente->charges()->create(['nombre' => 'Producto', 'tipo' => 'producto', 'precio_unitario' => 600, 'cantidad' => 1]);

    $this->actingAs($doctora)
        ->get(route('ingresos.index', ['mes' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Ingresos/Index')
            ->where('kpis.facturado', 1500)
            ->where('kpis.cobrado', 900)
            ->where('kpis.pendiente', 600)
            ->has('citas', 2));
});

test('cada doctor ve solamente sus propios ingresos', function () {
    $doctora = usuarioConRol('bariatra');
    $otro = usuarioConRol('bariatra');

    $propia = agendarCita($doctora, null, '2026-11-10 16:00:00');
    $propia->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 900, 'cantidad' => 1]);

    $ajena = agendarCita($otro, null, '2026-11-11 16:00:00');
    $ajena->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 500, 'cantidad' => 1]);

    $this->actingAs($doctora)
        ->get(route('ingresos.index', ['mes' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.facturado', 900)
            ->has('citas', 1));

    $this->actingAs(usuarioConRol('admin'))
        ->get(route('ingresos.index', ['mes' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kpis.facturado', 1400));
});

test('el resumen de ingresos expone deuda por paciente y filtros por estado', function () {
    $doctora = usuarioConRol('bariatra');
    $paciente1 = usuarioConRol('paciente');
    $paciente2 = usuarioConRol('paciente');

    $pendiente = agendarCita($doctora, $paciente1, '2026-11-10 16:00:00');
    $pendiente->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 700, 'cantidad' => 1]);

    $parcial = agendarCita($doctora, $paciente2, '2026-11-12 16:00:00');
    $parcial->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 600, 'cantidad' => 1]);
    $parcial->payments()->create(['monto' => 300, 'metodo_pago' => 'efectivo', 'pagado_en' => '2026-11-12']);

    $this->actingAs($doctora)
        ->get(route('ingresos.index', ['mes' => '2026-11', 'estado_pago' => 'pendiente']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.pendiente', 700)
            ->has('citas', 1)
            ->where('deudaPorPaciente.0.total', 700)
            ->where('deudaPorPaciente.0.citas', 1));
});

test('el resumen trimestral agrupa los ingresos del trimestre activo', function () {
    $doctora = usuarioConRol('bariatra');

    $octubre = agendarCita($doctora, null, '2026-10-05 16:00:00');
    $octubre->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 1000, 'cantidad' => 1]);

    $noviembre = agendarCita($doctora, null, '2026-11-06 16:00:00');
    $noviembre->charges()->create(['nombre' => 'Consulta', 'tipo' => 'consulta', 'precio_unitario' => 1200, 'cantidad' => 1]);

    $this->actingAs($doctora)
        ->get(route('ingresos.index', ['mes' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resumenTrimestral.0.mes', '2026-10')
            ->where('resumenTrimestral.1.mes', '2026-11')
            ->where('resumenTrimestral.2.mes', '2026-12')
            ->where('resumenTrimestral.1.facturado', 1200));
});
