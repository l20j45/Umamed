<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('progress_photos', function (Blueprint $table) {
            // Agrega la columna 'deleted_at' (permitiendo valores nulos por defecto)
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress_photos', function (Blueprint $table) {
            // Elimina la columna si se hace un rollback
            $table->dropSoftDeletes();
        });
    }
};
