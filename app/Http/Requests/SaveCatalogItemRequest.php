<?php

namespace App\Http\Requests;

use App\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof CatalogItem
            ? $this->user()?->can('update', $item) ?? false
            : $this->user()?->can('create', CatalogItem::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', Rule::in(CatalogItem::TIPOS)],
            'precio' => ['required', 'numeric', 'min:0', 'max:999999'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'doctor',
            'nombre' => 'nombre del concepto',
            'precio' => 'costo',
        ];
    }

    /**
     * El dueño del concepto: el propio doctor o el que elija administración.
     *
     * @return array<string, mixed>
     */
    public function datosDelConcepto(): array
    {
        $datos = $this->validated();
        $usuario = $this->user();

        if ($usuario?->hasRole('bariatra')) {
            $datos['user_id'] = $usuario->getKey();
        } elseif (blank($datos['user_id'] ?? null)) {
            throw ValidationException::withMessages([
                'user_id' => 'Selecciona el doctor al que pertenece el concepto.',
            ]);
        }

        $datos['activo'] = (bool) ($datos['activo'] ?? true);

        return $datos;
    }
}
