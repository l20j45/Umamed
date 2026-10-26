<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Contraseña en texto plano que se envía por correo al paciente.
     */
    public string $password;

    public function __construct(public User $user, string $password)
    {
        $this->password = $password;
    }
}
