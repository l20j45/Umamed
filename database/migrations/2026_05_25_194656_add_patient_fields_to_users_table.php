<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            // Separamos apellidos, añadimos teléfono, foto y fecha de tratamiento
            // Los ponemos como nullable() porque el Admin o el Bariatra no necesitan estos datos obligatoriamente
            $blueprint->string('last_name')->nullable()->after('name');
            $blueprint->string('phone')->nullable()->after('email');
            $blueprint->string('avatar')->nullable()->after('phone');
            $blueprint->date('treatment_start_date')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['last_name', 'phone', 'avatar', 'treatment_start_date']);
        });
    }
};
