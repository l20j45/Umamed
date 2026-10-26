<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // El profesional que atiende la cita.
            $table->foreignId('bariatra_id')->constrained('users')->cascadeOnDelete();

            // El paciente puede ser nulo para bloquear un horario (comida, junta, etc.).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('tipo', 40)->default('seguimiento');
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en');
            $table->string('estado', 20)->default('pendiente');
            $table->text('notas')->nullable();

            // Se marca cuando ya se envió el recordatorio por WhatsApp.
            $table->dateTime('recordatorio_enviado_en')->nullable();

            $table->timestamps();

            $table->index(['bariatra_id', 'inicia_en']);
            $table->index(['user_id', 'inicia_en']);
            $table->index(['estado', 'inicia_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
