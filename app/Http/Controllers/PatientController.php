<?php

namespace App\Http\Controllers;

use App\Events\UserCreated;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PatientController extends Controller
{
    /**
     * Listado de pacientes en el dashboard del bariatra.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        // Un paciente no debe ver el expediente de los demás: lo mandamos a su portal.
        if ($request->user()->hasRole('paciente')) {
            return redirect()->route('patient.portal');
        }

        $patients = User::role('paciente')->orderByDesc('created_at')->get();

        return Inertia::render('Dashboard', [
            'patients' => $patients,
        ]);
    }

    /**
     * Genera el expediente en PDF para imprimir o compartir.
     */
    public function exportarExpediente(int $id): HttpResponse
    {
        $patient = User::role('paciente')
            ->with([
                'medidas' => fn ($query) => $query->orderBy('created_at'),
                'progressPhotos' => fn ($query) => $query->orderByDesc('taken_at'),
            ])
            ->findOrFail($id);

        $medidas = $patient->medidas;
        $inicial = $medidas->first();
        $actual = $medidas->last();

        $html = view('pdf.expediente', [
            'patient' => $patient,
            'medidas' => $medidas,
            'inicial' => $inicial,
            'actual' => $actual,
            'perdida' => $inicial && $actual
                ? (float) $inicial->peso - (float) $actual->peso
                : null,
            'fotos' => $this->fotosParaPdf($patient),
            'generado' => now((string) config('clinic.zona_horaria', 'UTC'))->format('d/m/Y H:i'),
            'usuario' => auth()->user()?->name,
        ])->render();

        $dompdf = new Dompdf([
            'isRemoteEnabled' => false,
            'defaultFont' => 'Helvetica',
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombre = 'expediente-'.Str::slug($patient->name.' '.$patient->last_name).'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * Miniaturas de las últimas fotos para incrustar en el PDF.
     *
     * @return list<array{fecha: string|null, src: string}>
     */
    private function fotosParaPdf(User $patient): array
    {
        $fotos = [];

        foreach ($patient->progressPhotos->take(6) as $foto) {
            $ruta = Storage::disk('local')->path((string) $foto->image_path);

            if (! is_file($ruta)) {
                continue;
            }

            $datos = @file_get_contents($ruta);

            if ($datos === false) {
                continue;
            }

            // Se reduce a 320 px para que el PDF no pese de más.
            if (function_exists('imagecreatefromstring')) {
                $imagen = @imagecreatefromstring($datos);

                if ($imagen !== false) {
                    $ancho = max(1, imagesx($imagen));
                    $alto = imagesy($imagen);
                    $nuevoAlto = (int) round($alto * (320 / $ancho));
                    $mini = imagescale($imagen, 320, max(1, $nuevoAlto), IMG_BICUBIC);

                    if ($mini !== false) {
                        ob_start();
                        imagejpeg($mini, null, 70);
                        $datos = (string) ob_get_clean();
                        imagedestroy($mini);
                    }

                    imagedestroy($imagen);
                }
            }

            $fotos[] = [
                'fecha' => $foto->taken_at?->format('d/m/Y'),
                'categoria' => $foto->categoria ?? 'frente',
                'categoria_legible' => $foto->categoriaLegible(),
                'src' => 'data:image/jpeg;base64,'.base64_encode($datos),
            ];
        }

        return $fotos;
    }

    /**
     * Expediente de un paciente con su historial de medidas y fotos.
     */
    public function show(int $id): Response
    {
        $patient = User::role('paciente')
            ->with([
                'medidas' => fn ($query) => $query->orderBy('created_at'),
                'progressPhotos' => fn ($query) => $query->orderByDesc('taken_at'),
            ])
            ->findOrFail($id);

        return Inertia::render('Patients/Show', [
            'patient' => $patient,
        ]);
    }

    /**
     * Registra una nueva medición para el paciente indicado.
     */
    public function storeMeasure(Request $request, int $id): RedirectResponse
    {
        $patient = User::role('paciente')->findOrFail($id);

        $validated = $request->validate($this->reglasDeMedida());

        // El IMC se calcula en el servidor con la estatura real del paciente.
        $estatura = $patient->estaturaParaImc();
        $validated['imc'] = round($validated['peso'] / ($estatura ** 2), 2);

        $patient->medidas()->create($validated);

        return redirect()
            ->route('patients.show', $patient->id)
            ->with('success', 'Medición registrada correctamente.');
    }

    /**
     * Corrige una medición ya capturada.
     */
    public function updateMeasure(Request $request, int $id, int $medida): RedirectResponse
    {
        $patient = User::role('paciente')->findOrFail($id);
        $registro = $patient->medidas()->findOrFail($medida);

        $validated = $request->validate($this->reglasDeMedida());

        $estatura = $patient->estaturaParaImc();
        $validated['imc'] = round($validated['peso'] / ($estatura ** 2), 2);

        $registro->update($validated);

        return redirect()
            ->route('patients.show', $patient->id)
            ->with('success', 'Medición actualizada.');
    }

    /**
     * Elimina una medición capturada por error.
     */
    public function destroyMeasure(int $id, int $medida): RedirectResponse
    {
        $patient = User::role('paciente')->findOrFail($id);

        $patient->medidas()->findOrFail($medida)->delete();

        return redirect()
            ->route('patients.show', $patient->id)
            ->with('success', 'Medición eliminada.');
    }

    /**
     * Actualiza los datos de contacto y la estatura del paciente.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $patient = User::role('paciente')->findOrFail($id);

        $validated = $request->validate([
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'estatura' => ['nullable', 'numeric', 'min:0.50', 'max:2.50'],
        ], [], [
            'last_name' => 'apellidos',
            'phone' => 'teléfono',
        ]);

        $patient->update($validated);

        return redirect()
            ->route('patients.show', $patient->id)
            ->with('success', 'Datos del paciente actualizados.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasDeMedida(): array
    {
        return [
            'peso' => ['required', 'numeric', 'min:10', 'max:300'],
            'tSuperior' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'abdomenSuperior' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'abdomenMedio' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'abdomenInferior' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'exSuperior' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'exInferior' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Formulario de alta de paciente (solo administradores).
     */
    public function create(): Response
    {
        return Inertia::render('Patients/create');
    }

    /**
     * Procesa el alta de un nuevo paciente.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'estatura' => ['nullable', 'numeric', 'min:0.50', 'max:2.50'],
            'auto_password' => ['required', 'boolean'],
            'password' => ['required_if:auto_password,false', 'nullable', 'string', 'min:8'],
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
            'password.required_if' => 'Debes definir una contraseña manual si desactivas la generación automática.',
            'password.min' => 'La contraseña manual debe contener al menos 8 caracteres.',
        ]);

        $plainPassword = $request->boolean('auto_password')
            ? Str::password(12)
            : $validated['password'];

        $user = DB::transaction(function () use ($validated, $plainPassword) {
            $user = User::create([
                'name' => $validated['name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'estatura' => $validated['estatura'] ?? null,
                'password' => $plainPassword,
            ]);

            $user->assignRole('paciente');

            return $user;
        });

        // El listener de este evento envía las credenciales por correo.
        try {
            event(new UserCreated($user, $plainPassword));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('dashboard')->with(
                'error',
                'El paciente se registró, pero no se pudo enviar el correo con sus credenciales. Pídele que use "¿Olvidaste tu contraseña?" o define una contraseña manual.'
            );
        }

        $mensaje = $request->boolean('auto_password')
            ? 'Paciente registrado con éxito. El sistema le ha enviado sus credenciales de acceso por correo.'
            : 'Paciente registrado exitosamente con la contraseña manual asignada.';

        return redirect()->route('dashboard')->with('success', $mensaje);
    }
}
