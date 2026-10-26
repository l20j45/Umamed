<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gasto = $this->route('gasto');

        return $gasto instanceof Expense
            ? $this->user()?->can('update', $gasto) ?? false
            : $this->user()?->can('create', Expense::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'concepto' => ['required', 'string', 'max:120'],
            'categoria' => ['required', Rule::in(Expense::CATEGORIAS)],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'doctor',
            'concepto' => 'concepto del gasto',
            'monto' => 'monto',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDelGasto(): array
    {
        $datos = $this->validated();
        $usuario = $this->user();

        if ($usuario?->hasRole('bariatra') && ! $usuario->hasRole('admin')) {
            $datos['user_id'] = $usuario->getKey();
        } elseif (blank($datos['user_id'] ?? null)) {
            throw ValidationException::withMessages([
                'user_id' => 'Selecciona el doctor al que corresponde el gasto.',
            ]);
        }

        return $datos;
    }
}
