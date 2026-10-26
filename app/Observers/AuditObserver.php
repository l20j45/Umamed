<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Guarda una entrada en la bitácora cada vez que se crea, modifica o elimina
 * alguno de los modelos clínicos.
 */
class AuditObserver
{
    /**
     * Campos que nunca se guardan (contraseñas, tokens y sellos de tiempo).
     *
     * @var list<string>
     */
    private const IGNORADOS = ['password', 'remember_token', 'created_at', 'updated_at'];

    public function created(Model $modelo): void
    {
        $this->registrar('created', $modelo, null);
    }

    public function updated(Model $modelo): void
    {
        $cambios = [];

        foreach ($modelo->getChanges() as $campo => $valor) {
            if (in_array($campo, self::IGNORADOS, true)) {
                continue;
            }

            $cambios[$campo] = [$modelo->getOriginal($campo), $valor];
        }

        if ($cambios === []) {
            return;
        }

        $this->registrar('updated', $modelo, $cambios);
    }

    public function deleted(Model $modelo): void
    {
        $this->registrar('deleted', $modelo, null);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>|null  $cambios
     */
    private function registrar(string $accion, Model $modelo, ?array $cambios): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'modelo' => $modelo::class,
            'modelo_id' => $modelo->getKey(),
            'cambios' => $cambios,
            'ip' => request()->ip(),
        ]);
    }
}
