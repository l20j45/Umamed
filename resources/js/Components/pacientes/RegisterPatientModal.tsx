import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface RegisterPatientModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export default function RegisterPatientModal({ isOpen, onClose }: RegisterPatientModalProps) {
    // Inicializamos el formulario de Inertia incluyendo las variables de contraseña
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        last_name: '',
        email: '',
        phone: '',
        estatura: '', // Estatura en metros: se usa para calcular el IMC
        auto_password: true, // Por defecto se genera de forma automática
        password: '',
    });

    if (!isOpen) return null;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Enviamos la petición POST a la ruta de Laravel
        post(route('admin.users.store'), {
            onSuccess: () => {
                reset(); // Limpia todos los campos si sale bien
                onClose(); // Cierra el modal
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none">
            {/* Fondo oscuro */}
            <div className="fixed inset-0 bg-black opacity-50" onClick={onClose}></div>

            {/* Contenedor del Modal */}
            <div className="relative w-full max-w-md mx-auto z-50 p-4">
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden border border-gray-200 dark:border-gray-700">

                    {/* Encabezado */}
                    <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Registrar Nuevo Paciente
                        </h3>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            ✕
                        </button>
                    </div>

                    {/* Formulario */}
                    <form onSubmit={handleSubmit} className="p-6 space-y-4">
                        {/* Campo Nombre */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                            <input
                                type="text"
                                required
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="w-full p-2 text-sm border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                            />
                            {errors.name && <span className="text-xs text-red-500 mt-1 block">{errors.name}</span>}
                        </div>

                        {/* Campo Apellido */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Apellidos</label>
                            <input
                                type="text"
                                value={data.last_name}
                                onChange={e => setData('last_name', e.target.value)}
                                className="w-full p-2 text-sm border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                            />
                            {errors.last_name && <span className="text-xs text-red-500 mt-1 block">{errors.last_name}</span>}
                        </div>

                        {/* Campo Correo */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo Electrónico *</label>
                            <input
                                type="email"
                                required
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                className="w-full p-2 text-sm border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                            />
                            {errors.email && <span className="text-xs text-red-500 mt-1 block">{errors.email}</span>}
                        </div>

                        {/* Campo Teléfono */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                            <input
                                type="tel"
                                value={data.phone}
                                onChange={e => setData('phone', e.target.value)}
                                className="w-full p-2 text-sm border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                            />
                            {errors.phone && <span className="text-xs text-red-500 mt-1 block">{errors.phone}</span>}
                        </div>

                        {/* Campo Estatura */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estatura (m)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.5"
                                max="2.5"
                                placeholder="1.70"
                                value={data.estatura}
                                onChange={e => setData('estatura', e.target.value)}
                                className="w-full p-2 text-sm border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <p className="mt-1 text-[11px] text-gray-400">
                                Se usa para calcular el IMC de cada medición. Si se deja vacía se
                                asume 1.70 m.
                            </p>
                            {errors.estatura && <span className="text-xs text-red-500 mt-1 block">{errors.estatura}</span>}
                        </div>

                        {/* Selector de Contraseña Automática / Manual */}
                        <div className="flex items-start rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50">
                            <div className="flex h-5 items-center">
                                <input
                                    id="auto_password"
                                    type="checkbox"
                                    checked={data.auto_password}
                                    onChange={e => {
                                        setData(prev => ({
                                            ...prev,
                                            auto_password: e.target.checked,
                                            // Limpiamos la contraseña escrita si decide regresar a autogenerada
                                            password: e.target.checked ? '' : prev.password
                                        }));
                                    }}
                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                            </div>
                            <div className="ml-3 text-xs">
                                <label htmlFor="auto_password" className="font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                                    Generar contraseña automáticamente
                                </label>
                                <p className="text-gray-400 dark:text-gray-500">
                                    Se enviará una clave segura por correo al paciente.
                                </p>
                            </div>
                        </div>

                        {/* Campo Contraseña Manual (Condicional) */}
                        {!data.auto_password && (
                            <div className="transition-all duration-200">
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña Manual *</label>
                                <input
                                    type="password"
                                    required={!data.auto_password}
                                    value={data.password}
                                    onChange={e => setData('password', e.target.value)}
                                    placeholder="Mínimo 8 caracteres"
                                    className="w-full p-2 text-sm border rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                                />
                                {errors.password && <span className="text-xs text-red-500 mt-1 block">{errors.password}</span>}
                            </div>
                        )}

                        {/* Botones de Acción */}
                        <div className="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 rounded-lg transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50"
                            >
                                {processing ? 'Guardando...' : 'Guardar Paciente'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}