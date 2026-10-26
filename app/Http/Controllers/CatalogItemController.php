<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCatalogItemRequest;
use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogItemController extends Controller
{
    /**
     * Catálogo de conceptos del consultorio.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CatalogItem::class);

        $usuario = $request->user();
        $esSoloDoctor = $usuario->hasRole('bariatra')
            && ! $usuario->hasAnyRole(['admin', 'recepcionista']);
        $filtro = $request->integer('bariatra_id') ?: null;

        $items = CatalogItem::query()
            ->with('bariatra:id,name')
            ->when($esSoloDoctor, fn ($query) => $query->where('user_id', $usuario->getKey()))
            ->when(! $esSoloDoctor && $filtro, fn ($query) => $query->where('user_id', $filtro))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (CatalogItem $item): array => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'bariatra' => $item->bariatra?->name,
                'nombre' => $item->nombre,
                'descripcion' => $item->descripcion,
                'tipo' => $item->tipo,
                'tipo_legible' => $item->tipoLegible(),
                'precio' => (float) $item->precio,
                'activo' => (bool) $item->activo,
            ])
            ->values();

        return Inertia::render('Catalogo/Index', [
            'items' => $items,
            'bariatras' => User::role('bariatra')->orderBy('name')->get(['id', 'name']),
            'tipos' => CatalogItem::TIPOS,
            'filtros' => ['bariatra_id' => $filtro],
            'puedeEditar' => $usuario->can('create', CatalogItem::class),
            'esSoloDoctor' => $esSoloDoctor,
        ]);
    }

    public function store(SaveCatalogItemRequest $request): RedirectResponse
    {
        CatalogItem::create($request->datosDelConcepto());

        return back()->with('success', 'Concepto agregado al catálogo.');
    }

    public function update(SaveCatalogItemRequest $request, CatalogItem $item): RedirectResponse
    {
        $datos = $request->datosDelConcepto();
        unset($datos['user_id']); // el dueño del catálogo no cambia

        $item->update($datos);

        return back()->with('success', 'Concepto actualizado.');
    }

    public function destroy(CatalogItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return back()->with('success', 'Concepto eliminado del catálogo. Las citas ya cobradas conservan su registro.');
    }
}
