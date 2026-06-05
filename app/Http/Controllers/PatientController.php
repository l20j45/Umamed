<?php

namespace App\Http\Controllers;

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        $patient = User::role('paciente')->with('medidas', 'progressPhotos')->findOrFail($id);

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
                'data' => null,
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
                })->reverse()->values(), // <-- Agrega ->reverse()->values() para que la gráfica fluya hacia adelante
            ],
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

    public function create()
    {
        return Inertia::render('Patients/create');
    }

    /**
     * Procesa el almacenamiento y registro del nuevo usuario en el sistema.
     */
    public function store(Request $request)
    {
        // 1. Validamos rigurosamente los datos de entrada
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'auto_password' => 'required|boolean',
            // La contraseña solo es requerida obligatoriamente si auto_password es false
            'password' => 'required_if:auto_password,false|nullable|string|min:8',
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
            'password.required_if' => 'Debes definir una contraseña manual si desactivas la generación automática.',
            'password.min' => 'La contraseña manual debe contener al menos 8 caracteres.',
        ]);

        // 2. Determinamos la procedencia de la contraseña
        if ($request->auto_password) {
            // Genera una clave aleatoria segura de 12 caracteres (letras, números y caracteres especiales)
            $plainPassword = Str::password(12);
        } else {
            $plainPassword = $request->password;
        }

        // 3. Creamos el registro en la base de datos
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
        ]);

        $user->assignRole('paciente');

        // 4. Disparamos el Evento enviando el usuario y la clave en texto plano para el correo electrónico
        event(new UserCreated($user, $plainPassword));

        // 5. Redireccionamos al listado general con un mensaje de éxito contextualizado
        if ($request->auto_password) {
            return redirect()->route('dashboard')->with('success', 'Paciente registrado con éxito. El sistema le ha enviado sus credenciales de acceso por correo.');
        }

        return redirect()->route('dashboard')->with('success', 'Paciente registrado exitosamente con la contraseña manual asignada.');
    }

    public function testJob(Request $request)
    {
        $user = User::first();
        if (! $user) {
            return 'Necesitas al menos un usuario en tu base de datos para correr esta prueba.';
        }
        event(new UserCreated($user, 'PasswordAutogenerado789#'));
        return "Evento UserCreated disparado. Revisa tu consola con 'php artisan queue:work' o tu bandeja de Mailpit/Mailtrap.";

    }
}
