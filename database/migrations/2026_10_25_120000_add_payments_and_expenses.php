<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Abonos: una cita puede pagarse en varias exhibiciones.
        Schema::create('appointment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->date('pagado_en');
            $table->string('nota', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['appointment_id', 'pagado_en']);
        });

        // Los pagos que ya existían como "pagado = true" pasan a ser un abono.
        DB::table('appointments')
            ->where('pagado', true)
            ->orderBy('id')
            ->get(['id', 'pagado_en', 'metodo_pago'])
            ->each(function (object $cita): void {
                $total = (float) DB::table('appointment_charges')
                    ->where('appointment_id', $cita->id)
                    ->selectRaw('coalesce(sum(precio_unitario * cantidad), 0) as total')
                    ->value('total');

                if ($total <= 0) {
                    return;
                }

                DB::table('appointment_payments')->insert([
                    'appointment_id' => $cita->id,
                    'monto' => $total,
                    'metodo_pago' => $cita->metodo_pago ?? 'efectivo',
                    'pagado_en' => $cita->pagado_en ?? now()->toDateString(),
                    'nota' => 'Pago migrado del registro anterior',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // El estado del pago ahora se deriva de los abonos.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('pagado');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('pagado_en');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('metodo_pago');
        });

        // Gastos del consultorio.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('concepto');
            $table->string('categoria', 30)->default('otro');
            $table->decimal('monto', 10, 2);
            $table->date('fecha');
            $table->string('notas', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('pagado')->default(false)->after('estado');
            $table->date('pagado_en')->nullable()->after('pagado');
            $table->string('metodo_pago', 20)->nullable()->after('pagado_en');
        });

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('appointment_payments');
    }
};
