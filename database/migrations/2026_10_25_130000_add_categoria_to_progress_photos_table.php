<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('progress_photos', function (Blueprint $table) {
            $table->string('categoria', 30)->default('frente')->after('image_path');
        });

        DB::table('progress_photos')
            ->whereNull('categoria')
            ->update(['categoria' => 'frente']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress_photos', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
