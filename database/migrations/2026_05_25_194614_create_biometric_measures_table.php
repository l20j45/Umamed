<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medidas', function (Blueprint $table) {
            $table->id();

            // Relación con la tabla users (el paciente)
            // if contractual: si se borra el usuario, se borran sus medidas (onDelete cascade)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Métricas de composición corporal (Usamos float o decimal para precisión)
            $table->decimal('peso', 5, 2);          // Peso en kg (ej. 120.50)
            $table->decimal('imc', 4, 2);             // IMC (Índice de masa corporal)
            $table->decimal('tSuperior', 4, 1)->nullable();   // % Grasa
            $table->decimal('abdomenSuperior', 4, 1)->nullable(); // % Músculo
            $table->decimal('abdomenMedio', 4, 1)->nullable(); // % Músculo
            $table->decimal('abdomenInferior', 4, 1)->nullable(); // % Músculo
            $table->decimal('exSuperior', 4, 1)->nullable(); // % Músculo
            $table->decimal('exInferior', 4, 1)->nullable(); // % Músculo
            $table->text('notes')->nullable();         // Notas breves de esa medición
            $table->timestamps();                      // Aquí se guarda la fecha de la toma (created_at)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_measures');
    }
};
