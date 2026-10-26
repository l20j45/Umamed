<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        // 1. Validamos la integridad del archivo subido
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'avatar.required' => 'Es obligatorio seleccionar un archivo de imagen.',
            'avatar.image' => 'El archivo proporcionado no corresponde a una imagen válida.',
            'avatar.mimes' => 'La extensión del archivo debe ser jpeg, png, jpg o webp.',
            'avatar.max' => 'La imagen es demasiado pesada. El límite admitido es de 2MB.',
        ]);

        $user = $request->user();

        // 2. Si el usuario ya cuenta con un avatar previo, lo purgamos del disco exclusivo
        if ($user->avatar && Storage::disk('avatars')->exists($user->avatar)) {
            Storage::disk('avatars')->delete($user->avatar);
        }

        // 3. Almacenamos el archivo directamente en la raíz de storage/app/avatars/
        // El método retorna únicamente el hash del nombre generado (ej: "xYz987...png")
        $fileName = $request->file('avatar')->store('', 'avatars');

        // 4. Persistimos el nombre limpio del archivo en la base de datos
        $user->update([
            'avatar' => $fileName,
        ]);

        // Retornamos hacia atrás manteniendo el estado de scroll intacto
        return redirect()->back()->with('success', 'Tu foto de perfil ha sido actualizada exitosamente en el servidor.');
    }
}
