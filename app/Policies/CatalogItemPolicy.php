<?php

namespace App\Policies;

use App\Models\CatalogItem;
use App\Models\User;

class CatalogItemPolicy
{
    /**
     * Quien puede ver catálogos (recepción los necesita para cobrar).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'bariatra', 'recepcionista']);
    }

    /**
     * Sólo el doctor dueño del catálogo (o administración) lo edita.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'bariatra']);
    }

    public function update(User $user, CatalogItem $item): bool
    {
        return $this->administra($user, $item);
    }

    public function delete(User $user, CatalogItem $item): bool
    {
        return $this->administra($user, $item);
    }

    private function administra(User $user, CatalogItem $item): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('bariatra')
            && (int) $item->user_id === (int) $user->getKey();
    }
}
