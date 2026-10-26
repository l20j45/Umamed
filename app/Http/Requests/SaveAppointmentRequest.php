<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Appointment::class) ?? false;
    }

    /**
     * Las horas llegan del formulario en la zona de la clínica; aquí sólo se
     * normaliza el formato y se fuerza el doctor cuando es el propio bariatra.
     */
    protected function prepareForValidation(): void
    {
        $usuario = $this->user();

        if ($usuario?->hasRole('bariatra') && ! $usuario->hasAnyRole(['admin', 'recepcionista'])) {
            $this->merge(['bariatra_id' => $usuario->getKey()]);
        }

        foreach (['inicia_en', 'termina_en'] as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor) && $valor !== '') {
                // datetime-local envía "2026-10-25T10:30".
                $this->merge([$campo => str_replace('T', ' ', $valor)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'bariatra_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'tipo' => ['required', Rule::in(Appointment::TIPOS)],
            'inicia_en' => ['required', 'date'],
            'termina_en' => ['required', 'date'],
            'estado' => ['required', Rule::in(Appointment::ESTADOS)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'paciente',
            'bariatra_id' => 'doctor',
            'inicia_en' => 'hora de inicio',
            'termina_en' => 'hora de fin',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $zona = Appointment::zonaHoraria();
            $inicio = Carbon::parse((string) $this->input('inicia_en'), $zona)->utc();
            $fin = Carbon::parse((string) $this->input('termina_en'), $zona)->utc();

            if ($fin->lessThanOrEqualTo($inicio)) {
                $validator->errors()->add('termina_en', 'La hora de fin debe ser posterior a la de inicio.');

                return;
            }

            $bariatra = User::query()->find($this->integer('bariatra_id'));

            if (! $bariatra?->hasRole('bariatra')) {
                $validator->errors()->add('bariatra_id', 'Selecciona un doctor válido.');

                return;
            }

            if ($this->filled('user_id')) {
                $paciente = User::query()->find($this->integer('user_id'));

                if (! $paciente?->hasRole('paciente')) {
                    $validator->errors()->add('user_id', 'Selecciona un paciente válido.');

                    return;
                }
            }

            /** @var Appointment|null $citaActual */
            $citaActual = $this->route('cita');

            $seCruza = Appointment::hayChoque(
                $this->integer('bariatra_id'),
                $inicio,
                $fin,
                $citaActual?->getKey(),
            );

            if ($seCruza) {
                $validator->errors()->add('inicia_en', 'Ese horario se cruza con otra cita del doctor.');
            }
        });
    }
}
