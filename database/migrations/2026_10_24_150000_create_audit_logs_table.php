<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Quién hizo el cambio (nulo si fue un proceso automático).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // created | updated | deleted
            $table->string('accion', 20);

            // Modelo afectado (App\Models\Appointment, etc.) y su id.
            $table->string('modelo');
            $table->unsignedBigInteger('modelo_id')->nullable();

            // Cambios guardados como JSON: {campo: [antes, después]}.
            $table->json('cambios')->nullable();

            $table->string('ip', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['modelo', 'modelo_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
