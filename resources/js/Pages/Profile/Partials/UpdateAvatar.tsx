import { useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';

export default function UpdateAvatarForm() {
    // Obtenemos los datos del usuario actual desde las propiedades globales compartidas de Inertia
    const { auth } = usePage().props as any;
    
    // El avatar_url resuelto en el backend o un placeholder con la inicial si es null
    const defaultAvatar = auth.user.avatar_url 
        ? auth.user.avatar_url 
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(auth.user.name)}&background=3b82f6&color=fff`;

    const [preview, setPreview] = useState<string>(defaultAvatar);

    const { data, setData, post, processing, errors } = useForm({
        avatar: null as File | null,
        _method: 'POST', // Usamos POST nativo de formularios para la carga de binarios (archivos)
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('avatar', file);
            // Creamos una URL temporal local para el preview visual inmediato
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Mandamos la petición al endpoint del backend
        post(route('profile.avatar.update'), {
            preserveScroll: true,
            onSuccess: () => {
                // Limpieza opcional tras el éxito
            }
        });
    };

    return (
        <section className="max-w-xl rounded-xl border border-gray-100 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
            <header>
                <h2 className="text-sm font-bold text-gray-900 dark:text-gray-100">
                    Foto de Perfil
                </h2>
                <p className="mt-1 text-xs text-gray-400">
                    Actualiza tu imagen de avatar para personalizar tu portal clínico.
                </p>
            </header>

            <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                <div className="flex items-center space-x-6">
                    {/* Contenedor del Preview Circular */}
                    <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-50 shadow-inner dark:border-gray-700 dark:bg-gray-900">
                        <img
                            src={preview}
                            alt="Preview de avatar"
                            className="h-full w-full object-cover object-center"
                        />
                    </div>

                    {/* Input de Selección de Archivo */}
                    <div className="grid grid-cols-1 gap-1.5">
                        <label className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Seleccionar nueva imagen
                        </label>
                        <input
                            type="file"
                            accept="image/*"
                            onChange={handleFileChange}
                            className="w-full cursor-pointer text-xs text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 dark:file:bg-gray-700 dark:file:text-gray-300"
                        />
                        <p className="text-[11px] text-gray-400">
                            Formatos soportados: JPG, PNG, WEBP. Máximo 2MB.
                        </p>
                        {errors.avatar && (
                            <span className="text-xs text-red-500 font-semibold mt-1">
                                {errors.avatar}
                            </span>
                        )}
                    </div>
                </div>

                {/* Botón de Guardado (Solo se habilita si hay un archivo seleccionado) */}
                <div className="flex items-center gap-4">
                    <button
                        type="submit"
                        disabled={processing || !data.avatar}
                        className="rounded-md bg-blue-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-40"
                    >
                        {processing ? 'Subiendo...' : 'Actualizar Foto'}
                    </button>
                </div>
            </form>
        </section>
    );
}