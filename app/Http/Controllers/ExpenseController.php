<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveExpenseRequest;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Gastos del consultorio por mes.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $zona = (string) config('clinic.zona_horaria', 'UTC');
        $mes = $this->mesValido($request->query('mes'), $zona);
        $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01', $zona)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $usuario = $request->user();
        $esSoloDoctor = $usuario->hasRole('bariatra') && ! $usuario->hasRole('admin');
        $filtro = $request->integer('bariatra_id') ?: null;

        $gastos = Expense::query()
            ->with('bariatra:id,name')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->when($esSoloDoctor, fn ($query) => $query->where('user_id', $usuario->getKey()))
            ->when(! $esSoloDoctor && $filtro, fn ($query) => $query->where('user_id', $filtro))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Gastos/Index', [
            'mes' => $inicio->format('Y-m'),
            'gastos' => $gastos->map(fn (Expense $gasto): array => [
                'id' => $gasto->id,
                'user_id' => $gasto->user_id,
                'bariatra' => $gasto->bariatra?->name,
                'concepto' => $gasto->concepto,
                'categoria' => $gasto->categoria,
                'categoria_legible' => $gasto->categoriaLegible(),
                'monto' => (float) $gasto->monto,
                'fecha' => $gasto->fecha?->format('Y-m-d'),
                'notas' => $gasto->notas,
            ])->values(),
            'total' => round($gastos->sum(fn (Expense $gasto): float => (float) $gasto->monto), 2),
            'categorias' => Expense::CATEGORIAS,
            'bariatras' => User::role('bariatra')->orderBy('name')->get(['id', 'name']),
            'filtros' => ['bariatra_id' => $filtro],
            'esSoloDoctor' => $esSoloDoctor,
        ]);
    }

    public function store(SaveExpenseRequest $request): RedirectResponse
    {
        Expense::create($request->datosDelGasto());

        return back()->with('success', 'Gasto registrado.');
    }

    public function update(SaveExpenseRequest $request, Expense $gasto): RedirectResponse
    {
        $datos = $request->datosDelGasto();
        unset($datos['user_id']);

        $gasto->update($datos);

        return back()->with('success', 'Gasto actualizado.');
    }

    public function destroy(Expense $gasto): RedirectResponse
    {
        $this->authorize('delete', $gasto);

        $gasto->delete();

        return back()->with('success', 'Gasto eliminado.');
    }

    private function mesValido(mixed $valor, string $zona): string
    {
        if (is_string($valor) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor) === 1) {
            return $valor;
        }

        return now($zona)->format('Y-m');
    }
}
