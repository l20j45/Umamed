<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentCharge;
use App\Models\AppointmentPayment;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngresosController extends Controller
{
    /**
     * Resumen mensual de ingresos, gastos y utilidad.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $datos = $this->datos($request);

        return Inertia::render('Ingresos/Index', [
            'mes' => $datos['mes'],
            'kpis' => $datos['kpis'],
            'porTipo' => $datos['porTipo'],
            'porCategoria' => $datos['porCategoria'],
            'citas' => $datos['citas'],
            'deudaPorPaciente' => $datos['deudaPorPaciente'],
            'resumenTrimestral' => $datos['resumenTrimestral'],
            'bariatras' => User::role('bariatra')->orderBy('name')->get(['id', 'name']),
            'filtros' => [
                'bariatra_id' => $datos['filtro'],
                'estado_pago' => $datos['estadoPago'],
                'paciente' => $datos['paciente'],
            ],
            'esSoloDoctor' => $datos['esSoloDoctor'],
        ]);
    }

    /**
     * Exporta el detalle del mes a CSV (se abre en Excel).
     */
    public function exportar(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $datos = $this->datos($request);

        return response()->streamDownload(function () use ($datos): void {
            $salida = fopen('php://output', 'w');

            // BOM para que Excel respete los acentos.
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'Fecha', 'Hora', 'Paciente', 'Doctor', 'Conceptos',
                'Total', 'Pagado', 'Saldo', 'Estado',
            ], ';');

            foreach ($datos['filas'] as $fila) {
                fputcsv($salida, [
                    $fila['fecha'],
                    $fila['hora'],
                    $fila['paciente'],
                    $fila['bariatra'],
                    $fila['conceptos_detalle'],
                    number_format($fila['total'], 2, '.', ''),
                    number_format($fila['pagado'], 2, '.', ''),
                    number_format($fila['saldo'], 2, '.', ''),
                    $fila['estado_pago'],
                ], ';');
            }

            fclose($salida);
        }, 'ingresos-'.$datos['mes'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Request $request): array
    {
        $zona = Appointment::zonaHoraria();
        $mes = $this->mesValido($request->query('mes'), $zona);
        $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01', $zona)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $usuario = $request->user();
        $esSoloDoctor = $usuario->hasRole('bariatra')
            && ! $usuario->hasAnyRole(['admin', 'recepcionista']);
        $filtro = $request->integer('bariatra_id') ?: null;
        $estadoPago = (string) ($request->query('estado_pago') ?? 'todos');
        $paciente = trim((string) ($request->query('paciente') ?? ''));
        $doctorId = $esSoloDoctor ? $usuario->getKey() : $filtro;

        $citas = Appointment::query()
            ->with([
                'paciente:id,name,last_name',
                'bariatra:id,name',
                'charges:id,appointment_id,tipo,nombre,precio_unitario,cantidad',
                'payments:id,appointment_id,monto,metodo_pago,pagado_en',
            ])
            ->whereBetween('inicia_en', [$inicio->copy()->utc(), $fin->copy()->utc()])
            ->whereHas('charges')
            ->when($doctorId, fn ($query) => $query->where('bariatra_id', $doctorId))
            ->orderBy('inicia_en')
            ->get();

        $terminoPacienteBuscado = mb_strtolower($paciente, 'UTF-8');

        $citasFiltradas = $citas->filter(function (Appointment $cita) use ($estadoPago, $terminoPacienteBuscado): bool {
            $estado = $cita->estadoPago();
            if ($estadoPago !== 'todos' && $estadoPago !== '' && $estado !== $estadoPago) {
                return false;
            }

            if ($terminoPacienteBuscado !== '' && ! str_contains(mb_strtolower($cita->nombrePaciente(), 'UTF-8'), $terminoPacienteBuscado)) {
                return false;
            }

            return true;
        })->values();

        $facturado = round($citasFiltradas->sum(fn (Appointment $cita): float => $cita->total()), 2);
        $cobrado = round($citasFiltradas->sum(fn (Appointment $cita): float => $cita->totalPagado()), 2);

        $gastos = Expense::query()
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($doctorId, fn ($query) => $query->where('user_id', $doctorId))
            ->get();

        $porTipo = $citasFiltradas
            ->flatMap(fn (Appointment $cita) => $cita->charges)
            ->groupBy('tipo')
            ->map(fn ($grupo, $tipo): array => [
                'tipo' => (string) $tipo,
                'total' => round($grupo->sum(fn (AppointmentCharge $cargo): float => $cargo->subtotal), 2),
            ])
            ->values();

        $porCategoria = $gastos
            ->groupBy('categoria')
            ->map(fn ($grupo, $categoria): array => [
                'categoria' => (string) $categoria,
                'total' => round($grupo->sum(fn (Expense $gasto): float => (float) $gasto->monto), 2),
            ])
            ->values();

        $filas = $citasFiltradas->map(fn (Appointment $cita): array => [
            'id' => $cita->id,
            'fecha' => $cita->inicioLocal()->format('d/m/Y'),
            'hora' => $cita->inicioLocal()->format('H:i'),
            'paciente' => $cita->nombrePaciente(),
            'bariatra' => $cita->bariatra?->name,
            'conceptos' => $cita->charges->count(),
            'conceptos_detalle' => $cita->charges
                ->map(fn (AppointmentCharge $cargo): string => $cargo->cantidad > 1
                    ? $cargo->nombre.' x'.$cargo->cantidad
                    : $cargo->nombre)
                ->implode(' | '),
            'total' => $cita->total(),
            'pagado' => $cita->totalPagado(),
            'saldo' => $cita->saldo(),
            'estado_pago' => $cita->estadoPago(),
            'abonos' => $cita->payments->count(),
            'ultimo_metodo' => $cita->payments->last()?->metodo_pago,
        ])->values()->all();

        $deudaPorPaciente = $citasFiltradas
            ->filter(fn (Appointment $cita): bool => $cita->saldo() > 0)
            ->groupBy(fn (Appointment $cita): string => (string) ($cita->paciente?->id ?? $cita->nombrePaciente()))
            ->map(function ($grupo): array {
                $cita = $grupo->first();

                return [
                    'paciente' => $cita?->nombrePaciente(),
                    'total' => round($grupo->sum(fn (Appointment $item): float => $item->saldo()), 2),
                    'citas' => $grupo->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $inicioTrimestre = $inicio->copy()->startOfQuarter();
        $resumenTrimestral = collect(range(0, 2))->map(function (int $offset) use ($inicioTrimestre, $citasFiltradas): array {
            $mes = $inicioTrimestre->copy()->addMonths($offset);
            $citasMes = $citasFiltradas
                ->filter(fn (Appointment $cita): bool => $cita->inicioLocal()->format('Y-m') === $mes->format('Y-m'))
                ->values();

            $facturadoMes = round($citasMes->sum(fn (Appointment $cita): float => $cita->total()), 2);
            $cobradoMes = round($citasMes->sum(fn (Appointment $cita): float => $cita->totalPagado()), 2);

            return [
                'mes' => $mes->format('Y-m'),
                'facturado' => $facturadoMes,
                'cobrado' => $cobradoMes,
                'pendiente' => round($facturadoMes - $cobradoMes, 2),
            ];
        })->values()->all();

        return [
            'mes' => $mes,
            'filtro' => $filtro,
            'estadoPago' => $estadoPago,
            'paciente' => $paciente,
            'esSoloDoctor' => $esSoloDoctor,
            'kpis' => [
                'facturado' => $facturado,
                'cobrado' => $cobrado,
                'pendiente' => round($facturado - $cobrado, 2),
                'gastos' => round($gastos->sum(fn (Expense $gasto): float => (float) $gasto->monto), 2),
                'utilidad' => round($cobrado - $gastos->sum(fn (Expense $gasto): float => (float) $gasto->monto), 2),
                'citas' => $citasFiltradas->count(),
            ],
            'porTipo' => $porTipo->all(),
            'porCategoria' => $porCategoria->all(),
            'citas' => collect($filas)->map(fn (array $fila) => collect($fila)
                ->except(['conceptos_detalle'])
                ->all())->values()->all(),
            'filas' => $filas,
            'deudaPorPaciente' => $deudaPorPaciente,
            'resumenTrimestral' => $resumenTrimestral,
        ];
    }

    private function mesValido(mixed $valor, string $zona): string
    {
        if (is_string($valor) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor) === 1) {
            return $valor;
        }

        return now($zona)->format('Y-m');
    }
}
