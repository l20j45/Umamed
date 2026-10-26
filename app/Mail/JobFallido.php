<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobFallido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $trabajo,
        public string $cola,
        public string $mensaje,
        public string $traza,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Falló un proceso en '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.job-fallido',
            with: [
                'trabajo' => $this->trabajo,
                'cola' => $this->cola,
                'mensaje' => $this->mensaje,
                'traza' => $this->traza,
            ],
        );
    }
}
