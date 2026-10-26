<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // El paciente puede pedir que no se le manden recordatorios por WhatsApp.
            $table->boolean('recibir_recordatorios')->default(true)->after('estatura');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('recibir_recordatorios');
        });
    }
};
