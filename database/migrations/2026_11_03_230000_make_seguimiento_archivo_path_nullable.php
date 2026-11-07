<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los documentos que no son menú (pedimentos, notas, otros) pueden
     * existir sin archivo: el aviso es el comentario en sí.
     */
    public function up(): void
    {
        Schema::table('seguimiento', function (Blueprint $table) {
            $table->string('archivo_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento', function (Blueprint $table) {
            $table->string('archivo_path')->nullable(false)->change();
        });
    }
};
