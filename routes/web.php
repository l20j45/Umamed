<?php

use App\Http\Controllers\AppointmentChargeController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IngresosController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressPhotoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    // El dashboard lista los pacientes; a un paciente se le redirige al portal.
    Route::get('/dashboard', [PatientController::class, 'index'])->name('dashboard');

    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');

    // El expediente sólo lo abre el personal de la clínica.
    Route::middleware('role:admin|bariatra|recepcionista')->group(function () {
        Route::get('/pacientes/{id}', [PatientController::class, 'show'])->name('patients.show');
    });

    // Registrar mediciones es exclusivo de quien da atención clínica.
    Route::middleware('role:admin|bariatra')->group(function () {
        Route::get('/pacientes/{id}/expediente', [PatientController::class, 'exportarExpediente'])->name('patients.pdf');
        Route::patch('/pacientes/{id}', [PatientController::class, 'update'])->name('patients.update');
        Route::post('/pacientes/{id}/medidas', [PatientController::class, 'storeMeasure'])->name('patients.measures.store');
        Route::patch('/pacientes/{id}/medidas/{medida}', [PatientController::class, 'updateMeasure'])->name('patients.measures.update');
        Route::delete('/pacientes/{id}/medidas/{medida}', [PatientController::class, 'destroyMeasure'])->name('patients.measures.destroy');
    });
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/users/create', [PatientController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [PatientController::class, 'store'])->name('admin.users.store');
    Route::get('/actividad', [AuditLogController::class, 'index'])->name('auditoria.index');
});

Route::middleware(['auth', 'role:admin|bariatra|recepcionista'])->group(function () {
    Route::get('/agenda', [AppointmentController::class, 'index'])->name('agenda.index');
    Route::post('/agenda', [AppointmentController::class, 'store'])->name('agenda.store');
    Route::patch('/agenda/{cita}', [AppointmentController::class, 'update'])->name('agenda.update');
    Route::post('/agenda/{cita}/cancelar', [AppointmentController::class, 'cancelar'])->name('agenda.cancelar');
    Route::delete('/agenda/{cita}', [AppointmentController::class, 'destroy'])->name('agenda.destroy');
    Route::post('/agenda/{cita}/recordatorio', [AppointmentController::class, 'enviarRecordatorio'])->name('agenda.recordatorio');

    // Cobro de la cita: conceptos del catálogo, abonos, saldo y recibo.
    Route::get('/agenda/{cita}/cobro', [AppointmentChargeController::class, 'show'])->name('agenda.cobro');
    Route::post('/agenda/{cita}/cobro', [AppointmentChargeController::class, 'store'])->name('agenda.cobro.store');
    Route::patch('/agenda/{cita}/cobro/{cargo}', [AppointmentChargeController::class, 'update'])->name('agenda.cobro.update');
    Route::delete('/agenda/{cita}/cobro/{cargo}', [AppointmentChargeController::class, 'destroy'])->name('agenda.cobro.destroy');
    Route::post('/agenda/{cita}/pagos', [AppointmentChargeController::class, 'registrarPago'])->name('agenda.pagos.store');
    Route::post('/agenda/{cita}/pago', [AppointmentChargeController::class, 'registrarPago'])->name('agenda.pago');
    Route::delete('/agenda/{cita}/pagos/{pago}', [AppointmentChargeController::class, 'eliminarPago'])->name('agenda.pagos.destroy');
    Route::get('/agenda/{cita}/recibo', [AppointmentChargeController::class, 'recibo'])->name('agenda.recibo');

    // Catálogo de conceptos.
    Route::get('/catalogo', [CatalogItemController::class, 'index'])->name('catalogo.index');

    Route::middleware('role:admin|bariatra')->group(function () {
        Route::post('/catalogo', [CatalogItemController::class, 'store'])->name('catalogo.store');
        Route::patch('/catalogo/{item}', [CatalogItemController::class, 'update'])->name('catalogo.update');
        Route::delete('/catalogo/{item}', [CatalogItemController::class, 'destroy'])->name('catalogo.destroy');

        // Ingresos y gastos son información financiera del consultorio.
        Route::get('/ingresos', [IngresosController::class, 'index'])->name('ingresos.index');
        Route::get('/ingresos/exportar', [IngresosController::class, 'exportar'])->name('ingresos.exportar');
        Route::get('/gastos', [ExpenseController::class, 'index'])->name('gastos.index');
        Route::post('/gastos', [ExpenseController::class, 'store'])->name('gastos.store');
        Route::patch('/gastos/{gasto}', [ExpenseController::class, 'update'])->name('gastos.update');
        Route::delete('/gastos/{gasto}', [ExpenseController::class, 'destroy'])->name('gastos.destroy');
    });
});

Route::middleware(['auth', 'role:paciente'])->group(function () {
    Route::get('/mi-progreso', [ProgressPhotoController::class, 'index'])->name('patient.portal');
    Route::post('/mi-progreso/fotos', [ProgressPhotoController::class, 'store'])->name('patient.photos.store');
    Route::get('/mi-progreso/fotos/{id}', [ProgressPhotoController::class, 'show'])->name('patient.photos.show');
    Route::delete('/mi-progreso/fotos/{photo}', [ProgressPhotoController::class, 'destroy'])->name('patient.photos.destroy');

    Route::get('/mis-citas', [AppointmentController::class, 'misCitas'])->name('patient.citas');
    Route::patch('/mis-citas/recordatorios', [AppointmentController::class, 'actualizarRecordatorios'])->name('patient.recordatorios');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
