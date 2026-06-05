import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import React from 'react';

interface CreateUserProps {
    auth: { user: { name: string } };
}

export default function Create({ auth }: CreateUserProps) {
    // Configuramos el formulario conuseForm de Inertia
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        auto_password: true, // Por defecto la contraseña se genera sola
        password: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.users.store'), {
            onSuccess: () => reset('password'),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Alta de Nuevo Usuario / Paciente
                </h2>
            }
        >
            <Head title="Alta de Usuario" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl border border-gray-100 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
                        <h3 className="mb-1 text-sm font-bold text-gray-900 dark:text-white">
                            Información General
                        </h3>
                        <p className="mb-6 text-xs text-gray-400">
                            Registra un nuevo paciente o administrador en el sistema.
                        </p>

                        <form onSubmit={handleSubmit} className="space-y-5">
                            {/* Nombre */}
                            <div className="grid grid-cols-1 gap-1.5">
                                <label className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Nombre Completo
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full rounded-md border p-2.5 text-xs focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="Ej. Juan Pérez"
                                    required
                                />
                                {errors.name && <span className="text-xs text-red-500">{errors.name}</span>}
                            </div>

                            {/* Email */}
                            <div className="grid grid-cols-1 gap-1.5">
                                <label className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Correo Electrónico
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full rounded-md border p-2.5 text-xs focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="juan.perez@example.com"
                                    required
                                />
                                {errors.email && <span className="text-xs text-red-500">{errors.email}</span>}
                            </div>

                            {/* Selector de Contraseña (Checkbox / Toggle) */}
                            <div className="relative flex items-start rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                <div className="flex h-5 items-center">
                                    <input
                                        id="auto_password"
                                        type="checkbox"
                                        checked={data.auto_password}
                                        onChange={(e) => {
                                            setData((prev) => ({
                                                ...prev,
                                                auto_password: e.target.checked,
                                                // Si se activa el autogenerado, limpiamos la contraseña escrita
                                                password: e.target.checked ? '' : prev.password
                                            }));
                                        }}
                                        className="h-4 w-4 rounded-sm border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                <div className="ml-3 text-xs">
                                    <label htmlFor="auto_password" className="font-bold text-gray-700 dark:text-gray-300 cursor-pointer">
                                        Generar contraseña automáticamente
                                    </label>
                                    <p className="text-gray-400">
                                        Se creará una contraseña segura de forma aleatoria en el servidor.
                                    </p>
                                </div>
                            </div>

                            {/* Campo Contraseña Manual */}
                            {!data.auto_password && (
                                <div className="grid grid-cols-1 gap-1.5 transition-all duration-200">
                                    <label className="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Contraseña Manual
                                    </label>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="w-full rounded-md border p-2.5 text-xs focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Mínimo 8 caracteres"
                                        required={!data.auto_password}
                                    />
                                    {errors.password && <span className="text-xs text-red-500">{errors.password}</span>}
                                </div>
                            )}

                            {/* Botón de envío */}
                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-md bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {processing ? 'Registrando...' : 'Dar de Alta Usuario'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
