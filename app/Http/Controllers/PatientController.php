<?php

namespace App\Http\Controllers;

use App\Models\User;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PatientController extends Controller
{
    // 1. Listar todos los pacientes en el Dashboard
    public function index()
    {
        // Traemos solo los usuarios que tienen el rol de 'paciente'
        $patients = User::role('paciente')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Dashboard', [
            'patients' => $patients,
        ]);
    }

    // 2. Ver expediente de un paciente específico + su historial de medidas
    public function show($id)
    {
        $patient = User::role('paciente')->with('medidas')->findOrFail($id);

        return Inertia::render('Patients/Show', [
            'patient' => $patient,
        ]);
    }

    public function showUserView()
    {
        $user = auth()->user();


        $measures = $user->medidas()->orderBy('created_at', 'desc')->get();

        if ($measures->isEmpty()) {
            return Inertia::render('Patients/Portal', [
                'has_data' => false,
                'data' => null
            ]);
        }

        $actual = $measures->first();
        $inicial = $measures->last();

        $mejoras = [
            'peso' => (float) ($inicial->peso - $actual->peso),
            'imc' => (float) ($inicial->imc - $actual->imc),
            'tSuperior' => (float) ($inicial->tSuperior - $actual->tSuperior),
            'abdomenSuperior' => (float) ($inicial->abdomenSuperior - $actual->abdomenSuperior),
            'abdomenMedio' => (float) ($inicial->abdomenMedio - $actual->abdomenMedio),
            'abdomenInferior' => (float) ($inicial->abdomenInferior - $actual->abdomenInferior),
            'exSuperior' => (float) ($inicial->exSuperior - $actual->exSuperior),
            'exInferior' => (float) ($inicial->exInferior - $actual->exInferior),
        ];

        return Inertia::render('Patients/portal', [
            'has_data' => true,
            'data' => [
                'ultima' => [
                    'fecha' => $actual->created_at->format('d/m/Y'),
                    'peso' => (float) $actual->peso,
                    'imc' => (float) $actual->imc,
                    'tSuperior' => (float) $actual->tSuperior,
                    'abdomenSuperior' => (float) $actual->abdomenSuperior,
                    'abdomenMedio' => (float) $actual->abdomenMedio,
                    'abdomenInferior' => (float) $actual->abdomenInferior,
                    'exSuperior' => (float) $actual->exSuperior,
                    'exInferior' => (float) $actual->exInferior,
                    'notes' => $actual->notes,
                ],
                'mejoras' => $mejoras,
                'historial' => $measures->map(function ($item) {
                    return [
                        'fecha' => $item->created_at->format('d/m/Y'),
                        'peso' => (float) $item->peso,
                        'imc' => (float) $item->imc,
                        'tSuperior' => (float) $item->tSuperior,
                        'abdomenSuperior' => (float) $item->abdomenSuperior,
                        'abdomenMedio' => (float) $item->abdomenMedio,
                        'abdomenInferior' => (float) $item->abdomenInferior,
                        'exSuperior' => (float) $item->exSuperior,
                        'exInferior' => (float) $item->exInferior,
                    ];
                })->reverse()->values() // <-- Agrega ->reverse()->values() para que la gráfica fluya hacia adelante
            ]
        ]);
    }


    // 3. Procesar el formulario de una nueva medición
    public function storeMeasure(Request $request, $id)
    {
        $patient = User::role('paciente')->findOrFail($id);

        $validated = $request->validate([
            'peso' => 'required|numeric|min:10|max:300',
            'tSuperior' => 'nullable|numeric|min:10|max:200',
            'abdomenSuperior' => 'nullable|numeric|min:10|max:200',
            'abdomenMedio' => 'nullable|numeric|min:10|max:200',
            'abdomenInferior' => 'nullable|numeric|min:10|max:200',
            'exSuperior' => 'nullable|numeric|min:10|max:200',
            'exInferior' => 'nullable|numeric|min:10|max:200',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Cálculo opcional del IMC si tuvieras la estatura del paciente (ej. 1.70)
        // Por ahora, si no la calculamos aquí, podemos dejar que la envíen o calcularla así:
        $estaturaFija = 1.7;
        $validated['imc'] = $validated['peso'] / ($estaturaFija * $estaturaFija);

        $patient->medidas()->create($validated);

        return redirect()->route('patients.show', $id)->with('success', 'Medición registrada.');
    }
}
