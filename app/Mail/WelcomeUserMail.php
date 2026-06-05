<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    // Las propiedades públicas se exponen automáticamente, 
    // pero las forzaremos en el método content para evitar fallos de deserialización de la cola.
    public $user;
    public $password;

    /**
     * Create a new message instance.
     */
    public function __construct($user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a ' . config('app.name') . ' - Tus credenciales',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome',
            // 🔥 Al pasarlo explícitamente aquí, obligamos a Blade a recibir las variables 
            // directo de la memoria RAM del Worker, saltándonos cualquier bug del ecosistema de colas.
            with: [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'password' => $this->password,
            ],
        );
    }
}