<?php

namespace App\Http\Controllers;

use App\Models\DocumentoSeguimiento;
use App\Models\User;
use App\Support\NotificadorDePerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documentos de seguimiento: menús, pedimentos de estudios y notas que el
 * staff sube al expediente del paciente como PDF, normalmente entre
 * consultas.
 */
class SeguimientoController extends Controller
{
    /**
     * Sube un PDF o registra una nota de seguimiento para el paciente.
     *
     * El PDF sólo es obligatorio para los menús (plan alimenticio); pedimentos,
     * notas y otros pueden ser comentario sin archivo.
     */
    public function store(Request $request, int $id): RedirectResponse
    {
        $paciente = User::role('paciente')->findOrFail($id);

        $exigePdf = $request->input('categoria') === 'menu';

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'in:'.implode(',', DocumentoSeguimiento::CATEGORIAS)],
            'notas' => [$exigePdf ? 'nullable' : 'required', 'string', 'max:500'],
            'pdf' => [$exigePdf ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $ruta = null;

        if ($request->hasFile('pdf')) {
            $disco = Storage::disk('local');
            $nombre = Str::uuid()->toString().'.pdf';
            $disco->makeDirectory('seguimiento');
            $disco->putFileAs('seguimiento', $request->file('pdf'), $nombre);
            $ruta = 'seguimiento/'.$nombre;
        }

        $paciente->documentosSeguimiento()->create([
            'uploaded_by' => $request->user()?->getKey(),
            'titulo' => $validated['titulo'],
            'categoria' => $validated['categoria'],
            'notas' => $validated['notas'] ?? null,
            'archivo_path' => $ruta,
        ]);

        // Aviso push al paciente: algo nuevo llegó a su seguimiento.
        NotificadorDePerfil::enviar(
            $paciente,
            $ruta === null ? 'Nueva nota de la clínica' : 'Nuevo documento en tu expediente',
            $validated['titulo'],
            '/mi-progreso',
        );

        return back()->with('success', 'Documento agregado al expediente.');
    }

    /**
     * Descarga un documento: staff de la clínica o el propio paciente dueño.
     */
    public function descargar(Request $request, DocumentoSeguimiento $documento): BinaryFileResponse
    {
        $usuario = $request->user();

        $esStaff = $usuario?->hasAnyRole(['admin', 'bariatra', 'recepcionista']);
        $esDueno = (int) $documento->user_id === (int) $usuario?->getAuthIdentifier();

        if (! ($esStaff || $esDueno)) {
            abort(403, 'No estás autorizado para ver este documento.');
        }

        $disco = Storage::disk('local');

        if (blank($documento->archivo_path) || ! $disco->exists($documento->archivo_path)) {
            abort(404);
        }

        // Nombre de archivo legible: "menu-septiembre-68.pdf".
        $nombre = Str::slug($documento->titulo).'-'.$documento->id.'.pdf';

        return response()->download($disco->path($documento->archivo_path), $nombre);
    }

    /**
     * Elimina el documento y su archivo del disco (queda en la auditoría).
     */
    public function destroy(int $id, DocumentoSeguimiento $documento): RedirectResponse
    {
        if ((int) $documento->user_id !== $id) {
            abort(404);
        }

        $documento->delete();

        return back()->with('success', 'Documento eliminado del expediente.');
    }
}
