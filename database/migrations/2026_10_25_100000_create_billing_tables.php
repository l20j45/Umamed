<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();

            // Cada doctor mantiene su propio catálogo.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('tipo', 20)->default('servicio');
            $table->decimal('precio', 10, 2);
            // Los productos que ya no se venden se desactivan, no se borran.
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'activo']);
        });

        Schema::create('appointment_charges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();

            // Referencia al catálogo; si se borra, el cargo conserva su copia.
            $table->foreignId('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();

            // Copia del nombre, tipo y precio al momento de cobrar.
            $table->string('nombre');
            $table->string('tipo', 20)->default('servicio');
            $table->decimal('precio_unitario', 10, 2);
            $table->unsignedSmallInteger('cantidad')->default(1);

            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('pagado')->default(false)->after('estado');
            $table->date('pagado_en')->nullable()->after('pagado');
            $table->string('metodo_pago', 20)->nullable()->after('pagado_en');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['pagado', 'pagado_en', 'metodo_pago']);
        });

        Schema::dropIfExists('appointment_charges');
        Schema::dropIfExists('catalog_items');
    }
};
