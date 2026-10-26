<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentCharge;
use App\Models\AppointmentPayment;
use App\Models\CatalogItem;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentChargeController extends Controller
{
    private const METODOS = ['efectivo', 'transferencia', 'tarjeta', 'otro'];

    /**
     * Cobro de una cita: conceptos, abonos y saldo.
     */
    public function show(Appointment $cita): Response
    {
        $this->authorize('cobrar', $cita);

        $cita->load([
            'paciente:id,name,last_name,phone',
            'bariatra:id,name',
            'charges' => fn ($query) => $query->orderBy('id'),
            'payments' => fn ($query) => $query->orderBy('pagado_en')->orderBy('id'),
        ]);

        $catalogo = CatalogItem::query()
            ->where('user_id', $cita->bariatra_id)
            ->activos()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'precio']);

        return Inertia::render('Agenda/Cobro', [
            'cita' => [
                'id' => $cita->id,
                'fecha' => $cita->inicioLocal()->format('d/m/Y'),
                'hora' => $cita->inicioLocal()->format('H:i'),
                'estado' => $cita->estado,
                'paciente' => $cita->paciente ? [
                    'id' => $cita->paciente->id,
                    'nombre' => $cita->nombrePaciente(),
                    'telefono' => $cita->paciente->phone,
                ] : null,
                'bariatra' => $cita->bariatra?->name,
                'total' => $cita->total(),
                'pagado' => $cita->totalPagado(),
                'saldo' => $cita->saldo(),
                'estado_pago' => $cita->estadoPago(),
            ],
            'cargos' => $cita->charges->map(fn (AppointmentCharge $cargo): array => [
                'id' => $cargo->id,
                'nombre' => $cargo->nombre,
                'tipo' => $cargo->tipo,
                'precio_unitario' => (float) $cargo->precio_unitario,
                'cantidad' => (int) $cargo->cantidad,
                'subtotal' => $cargo->subtotal,
            ])->values(),
            'abonos' => $cita->payments->map(fn (AppointmentPayment $abono): array => [
                'id' => $abono->id,
                'monto' => (float) $abono->monto,
                'metodo_pago' => $abono->metodo_pago,
                'pagado_en' => $abono->pagado_en?->format('d/m/Y'),
                'nota' => $abono->nota,
            ])->values(),
            'catalogo' => $catalogo->map(fn (CatalogItem $item): array => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'tipo' => $item->tipo,
                'precio' => (float) $item->precio,
            ])->values(),
            'metodosPago' => self::METODOS,
            'puedeDescargarRecibo' => true,
        ]);
    }

    /**
     * Agrega un concepto del catálogo a la cita.
     */
    public function store(Request $request, Appointment $cita): RedirectResponse
    {
        $this->authorize('cobrar', $cita);

        $validated = $request->validate([
            'catalog_item_id' => ['required', 'integer', Rule::exists('catalog_items', 'id')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $item = CatalogItem::query()->findOrFail($validated['catalog_item_id']);

        if ((int) $item->user_id !== (int) $cita->bariatra_id) {
            throw ValidationException::withMessages([
                'catalog_item_id' => 'Ese concepto pertenece al catálogo de otro doctor.',
            ]);
        }

        if (! $item->activo) {
            throw ValidationException::withMessages([
                'catalog_item_id' => 'Ese concepto está desactivado en el catálogo.',
            ]);
        }

        $cita->charges()->create([
            'catalog_item_id' => $item->id,
            'nombre' => $item->nombre,
            'tipo' => $item->tipo,
            'precio_unitario' => $item->precio,
            'cantidad' => $validated['cantidad'],
        ]);

        return back()->with('success', 'Concepto agregado a la cita.');
    }

    public function update(Request $request, Appointment $cita, AppointmentCharge $cargo): RedirectResponse
    {
        $this->authorize('cobrar', $cita);
        $this->verificarCargo($cita, $cargo);

        $validated = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cargo->update(['cantidad' => $validated['cantidad']]);

        return back()->with('success', 'Cantidad actualizada.');
    }

    public function destroy(Appointment $cita, AppointmentCharge $cargo): RedirectResponse
    {
        $this->authorize('cobrar', $cita);
        $this->verificarCargo($cita, $cargo);

        $cargo->delete();

        return back()->with('success', 'Concepto quitado de la cita.');
    }

    /**
     * Registra un abono (pago parcial o total).
     */
    public function registrarPago(Request $request, Appointment $cita): RedirectResponse
    {
        $this->authorize('cobrar', $cita);

        $cita->load(['charges', 'payments']);
        $saldo = $cita->saldo();

        if ($cita->total() <= 0) {
            return back()->with('error', 'Agrega al menos un concepto antes de registrar el pago.');
        }

        if ($saldo <= 0) {
            return back()->with('error', 'La cita ya está pagada por completo.');
        }

        $esPagoLegacy = $request->boolean('pagado', false);
        $montoLegacy = $request->filled('monto') ? (float) $request->input('monto') : null;

        if ($esPagoLegacy && is_null($montoLegacy)) {
            $montoLegacy = round($saldo, 2);
        }

        $validated = $request->validate([
            'monto' => ['nullable', 'numeric', 'min:0.01', 'max:'.$saldo],
            'metodo_pago' => ['nullable', Rule::in(self::METODOS)],
            'pagado_en' => ['nullable', 'date'],
            'nota' => ['nullable', 'string', 'max:255'],
            'pagado' => ['nullable', 'boolean'],
        ], [
            'monto.max' => 'El abono no puede ser mayor que el saldo pendiente.',
        ]);

        $monto = $montoLegacy ?? (float) ($validated['monto'] ?? 0);
        if ($monto <= 0) {
            $monto = round($saldo, 2);
        }

        $monto = min($monto, round($saldo, 2));

        $cita->payments()->create([
            'monto' => round($monto, 2),
            'metodo_pago' => $validated['metodo_pago'] ?? ($request->input('metodo_pago') ?? 'efectivo'),
            'pagado_en' => $validated['pagado_en'] ?? now()->toDateString(),
            'nota' => $validated['nota'] ?? null,
            'registrado_por' => $request->user()?->getKey(),
        ]);

        return back()->with('success', $esPagoLegacy ? 'Pago registrado.' : 'Abono registrado.');
    }

    /**
     * Quita un abono registrado por error.
     */
    public function eliminarPago(Appointment $cita, AppointmentPayment $pago): RedirectResponse
    {
        $this->authorize('cobrar', $cita);

        if ((int) $pago->appointment_id !== (int) $cita->getKey()) {
            abort(404);
        }

        $pago->delete();

        return back()->with('success', 'Abono eliminado.');
    }

    /**
     * Recibo de la cita en PDF (no es comprobante fiscal).
     */
    public function recibo(Appointment $cita): HttpResponse
    {
        $this->authorize('cobrar', $cita);

        $cita->load([
            'paciente:id,name,last_name,phone',
            'bariatra:id,name',
            'charges' => fn ($query) => $query->orderBy('id'),
            'payments' => fn ($query) => $query->orderBy('pagado_en')->orderBy('id'),
        ]);

        $html = view('pdf.recibo', [
            'cita' => $cita,
            'paciente' => $cita->paciente,
            'bariatra' => $cita->bariatra,
            'cargos' => $cita->charges,
            'abonos' => $cita->payments,
            'total' => $cita->total(),
            'pagado' => $cita->totalPagado(),
            'saldo' => $cita->saldo(),
            'estadoPago' => $cita->estadoPago(),
            'generado' => now(config('clinic.zona_horaria', 'UTC'))->format('d/m/Y H:i'),
            'usuario' => auth()->user()?->name,
        ])->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'Helvetica']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombre = 'recibo-'.str_pad((string) $cita->id, 5, '0', STR_PAD_LEFT).'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * El cargo debe pertenecer a la cita de la URL.
     */
    private function verificarCargo(Appointment $cita, AppointmentCharge $cargo): void
    {
        if ((int) $cargo->appointment_id !== (int) $cita->getKey()) {
            abort(404);
        }
    }
}
