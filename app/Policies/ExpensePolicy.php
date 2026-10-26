<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Los gastos los ve administración y cada doctor.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'bariatra']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'bariatra']);
    }

    public function update(User $user, Expense $gasto): bool
    {
        return $this->administra($user, $gasto);
    }

    public function delete(User $user, Expense $gasto): bool
    {
        return $this->administra($user, $gasto);
    }

    private function administra(User $user, Expense $gasto): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('bariatra')
            && (int) $gasto->user_id === (int) $user->getKey();
    }
}
