<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumenDiarioDeCitas extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{hora: string, paciente: string, tipo: string, estado: string}>  $citas
     */
    public function __construct(
        public User $bariatra,
        public array $citas,
        public string $fecha,
    ) {}

    public function envelope(): Envelope
    {
        $cuantas = count($this->citas);

        return new Envelope(
            subject: $cuantas === 0
                ? 'Hoy no tienes citas agendadas'
                : "Tu agenda de hoy: {$cuantas} ".($cuantas === 1 ? 'cita' : 'citas'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.resumen-citas',
            with: [
                'doctor' => $this->bariatra->name,
                'fecha' => $this->fecha,
                'citas' => $this->citas,
            ],
        );
    }
}
