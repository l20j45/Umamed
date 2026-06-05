<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProgressPhotoController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Cambiamos el comportamiento del dashboard para que liste los pacientes
    Route::get('/dashboard', [PatientController::class, 'index'])->name('dashboard');

    // Rutas para ver expediente y guardar mediciones
    Route::get('/pacientes/{id}', [PatientController::class, 'show'])->name('patients.show');
    Route::post('/pacientes/{id}/medidas', [PatientController::class, 'storeMeasure'])->name('patients.measures.store');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');

});

Route::middleware(['role:admin'])->group(function () {
    Route::get('/admin/users/create', [PatientController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [PatientController::class, 'store'])->name('admin.users.store');
});

Route::middleware(['role:paciente'])->group(function () {
Route::get('/mi-progreso', [ProgressPhotoController::class, 'index'])->name('patient.portal');
    Route::post('/mi-progreso/fotos', [ProgressPhotoController::class, 'store'])->name('patient.photos.store');

    // 🔒 El parámetro debe ser exactamente {id}
    Route::get('/mi-progreso/fotos/{id}', [ProgressPhotoController::class, 'show'])->name('patient.photos.show');
    Route::delete('/patient/photos/{photo}', [ProgressPhotoController::class, 'destroy'])
        ->name('patient.photos.destroy');
});


Route::get('/', function () {
    return Inertia::render('Welcome');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/testJob', [PatientController::class, 'testJob'])->name('testJob');

require __DIR__.'/auth.php';
