<?php

namespace App\Http\Controllers;

use App\Models\ProgressPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProgressPhotoController extends Controller
{
    // Extendemos el método de tu Portal para incluir las fotos
    public function index()
    {
        $user = auth()->user();

        // 1. Jalamos las medidas biométricas que ya tenías
        $measures = $user->medidas()->orderBy('created_at', 'desc')->get();

        // 2. Jalamos sus fotos de progreso reales ordenadas por fecha
        $photos = $user->progressPhotos()->orderBy('taken_at', 'desc')->get();

        // (Conservamos tu lógica de estructuración de medidas anteriores...)
        $hasData = !$measures->isEmpty();
        $medidasData = null;

        if ($hasData) {
            $actual = $measures->first();
            $inicial = $measures->last();
            $medidasData = [
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
                'mejoras' => [
                    'peso' => (float) ($inicial->peso - $actual->peso),
                    'imc' => (float) ($inicial->imc - $actual->imc),
                    'tSuperior' => (float) ($inicial->tSuperior - $actual->tSuperior),
                    'abdomenSuperior' => (float) ($inicial->abdomenSuperior - $actual->abdomenSuperior),
                    'abdomenMedio' => (float) ($inicial->abdomenMedio - $actual->abdomenMedio),
                    'abdomenInferior' => (float) ($inicial->abdomenInferior - $actual->abdomenInferior),
                    'exSuperior' => (float) ($inicial->exSuperior - $actual->exSuperior),
                    'exInferior' => (float) ($inicial->exInferior - $actual->exInferior),
                ],
                'historial' => $measures->map(fn($item) => [
                    'fecha' => $item->created_at->format('d/m/Y'),
                    'peso' => (float) $item->peso,
                    'imc' => (float) $item->imc,
                    'tSuperior' => (float) $item->tSuperior,
                    'abdomenSuperior' => (float) $item->abdomenSuperior,
                    'abdomenMedio' => (float) $item->abdomenMedio,
                    'abdomenInferior' => (float) $item->abdomenInferior,
                    'exSuperior' => (float) $item->exSuperior,
                    'exInferior' => (float) $item->exInferior,
                ])->reverse()->values()
            ];

            Log::info($photos);
        }

        return Inertia::render('Patients/portal', [
            'has_data' => $hasData,
            'data' => $medidasData,
            'photos' => $photos // Enviamos el array de fotos con sus URLs autohosteadas
        ]);
    }

    // Procesa el formulario de subida (POST)
    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:12288', // Subimos a 12MB por fotos de alta resolución
            'taken_at' => 'required|date',
            'notes' => 'nullable|string|max:150'
        ]);

        // 🔒 CAMBIO: Se guarda en el disco 'local' (fuera del alcance del navegador)
        $path = $request->file('photo')->store('private_progress_photos', 'local');

        auth()->user()->progressPhotos()->create([
            'image_path' => $path,
            'taken_at' => $request->taken_at,
            'notes' => $request->notes
        ]);

        return redirect()->back();
    }

    public function show($id) // <-- Mismo nombre de variable
    {
        $photo = ProgressPhoto::findOrFail($id);

        if ($photo->user_id !== auth()->id()) {
            abort(403, 'No estás autorizado para ver este recurso.');
        }

        if (!Storage::disk('local')->exists($photo->image_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($photo->image_path));
    }

public function destroy(ProgressPhoto $photo)
    {
        // 1. Validar que la foto pertenezca al usuario autenticado (Seguridad)
        if ($photo->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para eliminar esta foto.');
        }

        // 2. Ejecutar el borrado (Laravel aplicará Soft Delete automáticamente debido al Trait)
        $photo->delete();

        // 3. Redireccionar hacia atrás para que Inertia actualice el estado del componente React
        return back()->with('success', 'Foto movida a la papelera correctamente.');
    }

}
