<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Quién puede ver la agenda.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'bariatra', 'recepcionista']);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Sólo el dueño de la agenda (o administración) puede modificarla.
     */
    public function update(User $user, Appointment $cita): bool
    {
        return $this->administra($user, $cita);
    }

    /**
     * Cancelar conserva la cita en el historial: lo puede hacer quien la gestiona.
     */
    public function cancel(User $user, Appointment $cita): bool
    {
        return $this->administra($user, $cita);
    }

    /**
     * Borrar de verdad queda reservado a administración.
     */
    public function delete(User $user, Appointment $cita): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Cobrar la cita: recepción y administración también registran pagos.
     */
    public function cobrar(User $user, Appointment $cita): bool
    {
        return $this->administra($user, $cita);
    }

    private function administra(User $user, Appointment $cita): bool
    {
        if ($user->hasAnyRole(['admin', 'recepcionista'])) {
            return true;
        }

        return $user->hasRole('bariatra')
            && (int) $cita->bariatra_id === (int) $user->getKey();
    }
}
