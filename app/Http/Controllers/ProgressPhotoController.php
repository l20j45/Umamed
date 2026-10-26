<?php

namespace App\Http\Controllers;

use App\Models\ProgressPhoto;
use App\Support\OptimizadorDeFotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProgressPhotoController extends Controller
{
    /**
     * Portal de progreso del paciente autenticado.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $measures = $user->medidas()->orderByDesc('created_at')->get();
        $photos = $user->progressPhotos()
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProgressPhoto $photo): array => [
                'id' => $photo->id,
                'photo_url' => $photo->photo_url,
                'taken_at' => $photo->taken_at?->format('Y-m-d'),
                'notes' => $photo->notes,
                'categoria' => $photo->categoria ?? 'frente',
                'categoria_legible' => $photo->categoriaLegible(),
            ])
            ->values();

        return Inertia::render('Patients/portal', [
            'has_data' => $measures->isNotEmpty(),
            'data' => $this->buildMeasuresPayload($measures),
            'photos' => $photos,
        ]);
    }

    /**
     * Procesa la subida de una foto de progreso.
     */
    public function store(Request $request, OptimizadorDeFotos $optimizador): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:12288'],
            'taken_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:150'],
            'categoria' => ['required', Rule::in(ProgressPhoto::CATEGORIAS)],
        ]);

        // Se guarda en el disco 'local' (fuera del alcance del navegador) y
        // siempre como JPEG limpio: sin EXIF/GPS y con la orientación corregida.
        $disco = Storage::disk('local');
        $disco->makeDirectory('private_progress_photos');

        $carpeta = 'private_progress_photos';
        $ruta = $carpeta.'/'.Str::uuid()->toString().'.jpg';
        $original = $request->file('photo');

        if (! $optimizador->procesar($original->getRealPath(), $disco->path($ruta))) {
            // Si no se pudo sanear la imagen no se guarda tal cual: hacerlo
            // dejaría las fotos con GPS y demás metadatos dentro del sistema.
            $disco->delete($ruta);

            throw ValidationException::withMessages([
                'photo' => 'No pudimos procesar la imagen. Intenta con un archivo JPG o PNG.',
            ]);
        }

        $request->user()->progressPhotos()->create([
            'image_path' => $ruta,
            'taken_at' => $validated['taken_at'],
            'notes' => $validated['notes'] ?? null,
            'categoria' => $validated['categoria'],
        ]);

        return back()->with('success', 'Foto de progreso registrada.');
    }

    /**
     * Sirve la imagen validando que pertenezca al paciente autenticado.
     */
    public function show(int $id): BinaryFileResponse
    {
        $photo = ProgressPhoto::findOrFail($id);

        $this->authorizeOwnership($photo);

        if (! Storage::disk('local')->exists($photo->image_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($photo->image_path));
    }

    /**
     * Elimina (soft delete) una foto del paciente autenticado.
     */
    public function destroy(Request $request, ProgressPhoto $photo): RedirectResponse
    {
        $this->authorizeOwnership($photo, $request);

        $photo->delete();

        return back()->with('success', 'Foto movida a la papelera correctamente.');
    }

    /**
     * Evita que un paciente acceda a las fotos de otro.
     */
    private function authorizeOwnership(ProgressPhoto $photo, ?Request $request = null): void
    {
        $userId = (int) ($request?->user()?->getAuthIdentifier() ?? auth()->id());

        if ((int) $photo->user_id !== $userId) {
            abort(403, 'No estás autorizado para ver este recurso.');
        }
    }

    /**
     * Arma el payload de medidas que consume el portal.
     */
    private function buildMeasuresPayload($measures): ?array
    {
        if ($measures->isEmpty()) {
            return null;
        }

        $actual = $measures->first();
        $inicial = $measures->last();

        // Las fechas se guardan en UTC y se muestran en la zona de la clínica:
        // si no, una medición de las 19:00 aparecía con la fecha del día siguiente.
        $zona = (string) config('clinic.zona_horaria', 'UTC');

        $campos = [
            'peso', 'imc', 'tSuperior', 'abdomenSuperior',
            'abdomenMedio', 'abdomenInferior', 'exSuperior', 'exInferior',
        ];

        $mapear = static fn ($item): array => [
            'fecha' => $item->created_at->copy()->timezone($zona)->format('d/m/Y'),
            'peso' => (float) $item->peso,
            'imc' => (float) $item->imc,
            'tSuperior' => (float) $item->tSuperior,
            'abdomenSuperior' => (float) $item->abdomenSuperior,
            'abdomenMedio' => (float) $item->abdomenMedio,
            'abdomenInferior' => (float) $item->abdomenInferior,
            'exSuperior' => (float) $item->exSuperior,
            'exInferior' => (float) $item->exInferior,
            'notes' => $item->notes,
        ];

        $mejoras = [];
        foreach ($campos as $campo) {
            $mejoras[$campo] = round((float) $inicial->{$campo} - (float) $actual->{$campo}, 2);
        }

        return [
            'ultima' => $mapear($actual),
            'mejoras' => $mejoras,
            // El historial se ordena del más antiguo al más reciente para la gráfica.
            'historial' => $measures->map($mapear)->reverse()->values(),
        ];
    }
}
