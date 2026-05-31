import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    TooltipProps,
    XAxis,
    YAxis,
} from 'recharts';

// 1. Importamos la librería del Lightbox y sus estilos CSS esenciales
import Lightbox from "yet-another-react-lightbox";
import "yet-another-react-lightbox/styles.css";

interface MeasureItem {
    fecha: string;
    peso: number;
    imc: number;
    tSuperior: number;
    abdomenSuperior: number;
    abdomenMedio: number;
    abdomenInferior: number;
    exSuperior: number;
    exInferior: number;
}

interface PhotoItem {
    id: number;
    photo_url: string;
    taken_at: string;
    notes: string | null;
}

interface PortalProps {
    auth: { user: { name: string } };
    has_data: boolean;
    data: {
        ultima: MeasureItem & { notes: string | null };
        mejoras: Omit<MeasureItem, 'fecha'>;
        historial: MeasureItem[];
    } | null;
    photos: PhotoItem[];
}

export default function Portal({
    auth,
    has_data,
    data,
    photos = [],
}: PortalProps) {

    const [removingId, setRemovingId] = useState<number | null>(null);

    // 2. Estados para controlar el índice de la foto activa en el Spotlight (Lightbox)
    const [lightboxIndex, setLightboxIndex] = useState<number>(-1);

    const {
        data: photoData,
        setData: setPhotoData,
        post: postPhoto,
        processing: uploading,
        reset: resetPhoto,
        errors: photoErrors,
    } = useForm({
        photo: null as File | null,
        taken_at: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handlePhotoSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postPhoto(route('patient.photos.store'), {
            onSuccess: () => resetPhoto('photo', 'notes'),
        });
    };

    const handleSoftDelete = (id: number, e: React.MouseEvent) => {
        // Evitamos que al presionar el botón de borrar se dispare el click de apertura del Lightbox
        e.stopPropagation();

        if (confirm('¿Estás seguro de que deseas eliminar esta foto de tu registro?')) {
            setRemovingId(id);
            router.delete(route('patient.photos.destroy', id), {
                preserveScroll: true,
                onFinish: () => setRemovingId(null)
            });
        }
    };

    const renderMejora = (valor: number, unidad: string = 'cm') => {
        if (valor === 0) {
            return (
                <span className="text-xs font-medium text-gray-400">
                    Sin cambios
                </span>
            );
        }
        const esReduccion = valor > 0;
        return (
            <span
                className={`text-xs font-bold ${esReduccion ? 'text-green-600 dark:text-green-400' : 'text-red-500'}`}
            >
                {esReduccion ? `-${valor.toFixed(1)}` : `+${Math.abs(valor).toFixed(1)}`}{' '}
                {unidad}
            </span>
        );
    };

    type CustomTooltipProps = TooltipProps<number, string> & {
        payload?: Array<{
            name: string;
            value: string | number | null;
            color?: string;
        }>;
        label?: string | number;
    };

    const CustomTooltip = ({ active, payload, label }: CustomTooltipProps) => {
        if (active && payload && payload.length) {
            return (
                <div className="rounded-lg border border-gray-200 bg-white p-3 text-xs shadow-md dark:border-gray-700 dark:bg-gray-900">
                    <p className="mb-1 font-bold text-gray-700 dark:text-gray-300">
                        Consulta: {label}
                    </p>
                    {payload.map((pld) => (
                        <p
                            key={pld.name}
                            style={{ color: pld.color }}
                            className="font-semibold"
                        >
                            {pld.name}: {pld.value}
                        </p>
                    ))}
                </div>
            );
        }
        return null;
    };

    // 3. Mapeamos el array de fotos al formato estructurado que requiere la librería ({ src, alt })
    const slides = photos.map(pic => ({
        src: pic.photo_url,
        alt: pic.notes || "Progreso clínico",
    }));

    return (
        <AuthenticatedLayout

            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Mi Progreso Clínico
                </h2>
            }
        >
            <Head title="Mi Progreso" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                    {!has_data || !data ? (
                        <div className="rounded-xl border border-gray-100 bg-white p-8 text-center shadow dark:border-gray-700 dark:bg-gray-800">
                            <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                ¡Hola, {auth.user.name}!
                            </h3>
                            <p className="mx-auto mt-2 max-w-md text-gray-500 dark:text-gray-400">
                                Tu bariatra está configurando tus datos iniciales. Muy pronto verás aquí tus gráficos y comparativas corporales.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* SECCIÓN 1: ESTADO ACTUAL */}
                            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
                                <div className="mb-6 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                            Últimas Mediciones Registradas
                                        </h3>
                                        <p className="text-xs text-gray-400">
                                            Control de consulta del {data.ultima.fecha}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
                                    <div className="border-r pr-2 dark:border-gray-700">
                                        <p className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                            Peso Corporal
                                        </p>
                                        <p className="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                                            {data.ultima.peso}{' '}
                                            <span className="text-xs font-normal">kg</span>
                                        </p>
                                        <div className="mt-0.5">
                                            {renderMejora(data.mejoras.peso, 'kg')}
                                        </div>
                                    </div>

                                    <div className="border-r pr-2 dark:border-gray-700">
                                        <p className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                            Masa Corporal (IMC)
                                        </p>
                                        <p className="mt-1 text-2xl font-black text-blue-600 dark:text-blue-400">
                                            {data.ultima.imc.toFixed(1)}
                                        </p>
                                        <div className="mt-0.5">
                                            {renderMejora(data.mejoras.imc, 'pts')}
                                        </div>
                                    </div>

                                    <div className="border-r pr-2 dark:border-gray-700">
                                        <p className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                            Torso Superior
                                        </p>
                                        <p className="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                                            {data.ultima.tSuperior}{' '}
                                            <span className="text-xs font-normal">cm</span>
                                        </p>
                                        <div className="mt-0.5">
                                            {renderMejora(data.mejoras.tSuperior)}
                                        </div>
                                    </div>

                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                            Abdomen Medio
                                        </p>
                                        <p className="mt-1 text-2xl font-black text-gray-900 dark:text-white">
                                            {data.ultima.abdomenMedio}{' '}
                                            <span className="text-xs font-normal">cm</span>
                                        </p>
                                        <div className="mt-0.5">
                                            {renderMejora(data.mejoras.abdomenMedio)}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* SECCIÓN 2: GRÁFICOS INTERACTIVOS */}
                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
                                    <h4 className="mb-1 text-sm font-bold text-gray-800 dark:text-gray-200">
                                        Tendencia de Peso e IMC
                                    </h4>
                                    <div className="mt-4 h-64 w-full">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <AreaChart
                                                data={data.historial}
                                                margin={{ top: 5, right: 5, left: -15, bottom: 0 }}
                                            >
                                                <defs>
                                                    <linearGradient id="colorPeso" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.15} />
                                                        <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                                                    </linearGradient>
                                                </defs>
                                                <XAxis dataKey="fecha" stroke="#9ca3af" fontSize={10} />
                                                <YAxis domain={['dataMin - 3', 'dataMax + 3']} stroke="#9ca3af" fontSize={10} />
                                                <Tooltip content={<CustomTooltip />} />
                                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" className="dark:stroke-gray-700" />
                                                <Area type="monotone" name="Peso (kg)" dataKey="peso" stroke="#3b82f6" strokeWidth={2.5} fill="url(#colorPeso)" />
                                                <Line type="monotone" name="IMC" dataKey="imc" stroke="#10b981" strokeWidth={2} dot={{ r: 3 }} />
                                                <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '8px' }} />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                </div>

                                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
                                    <h4 className="mb-1 text-sm font-bold text-gray-800 dark:text-gray-200">
                                        Evolución de Reducción Abdominal
                                    </h4>
                                    <div className="mt-4 h-64 w-full">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <LineChart
                                                data={data.historial}
                                                margin={{ top: 5, right: 5, left: -15, bottom: 0 }}
                                            >
                                                <XAxis dataKey="fecha" stroke="#9ca3af" fontSize={10} />
                                                <YAxis domain={['dataMin - 4', 'dataMax + 4']} stroke="#9ca3af" fontSize={10} />
                                                <Tooltip content={<CustomTooltip />} />
                                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" className="dark:stroke-gray-700" />
                                                <Line type="monotone" name="Sup. (cm)" dataKey="abdomenSuperior" stroke="#f59e0b" strokeWidth={2} dot={false} />
                                                <Line type="monotone" name="Med. (cm)" dataKey="abdomenMedio" stroke="#ef4444" strokeWidth={2} dot={false} />
                                                <Line type="monotone" name="Inf. (cm)" dataKey="abdomenInferior" stroke="#8b5cf6" strokeWidth={2} dot={false} />
                                                <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '8px' }} />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}

                    {/* SECCIÓN 3: SUBIDA DE IMÁGENES Y GALERÍA */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* FORMULARIO DE CARGA */}
                        <div className="h-fit rounded-xl border border-gray-100 bg-white p-6 shadow dark:border-gray-700 dark:bg-gray-800">
                            <h3 className="mb-1 text-sm font-bold text-gray-900 dark:text-white">
                                Añadir Registro Visual
                            </h3>
                            <p className="mb-4 text-xs text-gray-400">
                                Protegido en el storage local privado de la aplicación.
                            </p>

                            <form onSubmit={handlePhotoSubmit} className="space-y-4">
                                <div>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) =>
                                            setPhotoData(
                                                'photo',
                                                e.target.files ? e.target.files[0] : null
                                            )
                                        }
                                        className="w-full cursor-pointer text-xs text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 dark:file:bg-gray-700 dark:file:text-gray-300"
                                    />
                                    {photoErrors.photo && (
                                        <span className="mt-1 block text-xs text-red-500">
                                            {photoErrors.photo}
                                        </span>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-2">
                                    <label className="text-[11px] font-bold uppercase text-gray-400">
                                        Fecha de captura
                                    </label>
                                    <input
                                        type="date"
                                        value={photoData.taken_at}
                                        onChange={(e) => setPhotoData('taken_at', e.target.value)}
                                        className="w-full rounded-md border p-2 text-xs focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-2">
                                    <label className="text-[11px] font-bold uppercase text-gray-400">
                                        Nota descriptiva
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="Ej: Semana 3 - Ayunas"
                                        value={photoData.notes || ''}
                                        onChange={(e) => setPhotoData('notes', e.target.value)}
                                        className="w-full rounded-md border p-2 text-xs focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    {photoErrors.notes && (
                                        <span className="block text-xs text-red-500">
                                            {photoErrors.notes}
                                        </span>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={uploading || !photoData.photo}
                                    className="w-full rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-40"
                                >
                                    {uploading ? 'Cifrando y guardando...' : 'Subir Foto Privada'}
                                </button>
                            </form>
                        </div>

                        {/* CUADRÍCULA DE LA GALERÍA */}
                        {photos.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white p-6 shadow lg:col-span-2 dark:border-gray-700 dark:bg-gray-800">
                                <p className="text-xs text-gray-400">
                                    Aún no hay imágenes privadas cargadas en tu bitácora.
                                </p>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow lg:col-span-2 dark:border-gray-700 dark:bg-gray-800">
                                <h3 className="mb-1 text-sm font-bold text-gray-900 dark:text-white">
                                    Evolución Visual en Fotos
                                </h3>
                                <p className="mb-4 text-xs text-gray-400">
                                    Solo visibles para ti. Las imágenes pasan por validación de token de sesión.
                                </p>

                                <div className="grid max-h-[340px] grid-cols-2 gap-3 overflow-y-auto pr-1 sm:grid-cols-3">
                                    {photos.map((pic, index) => (
                                        <div
                                            key={pic.id}
                                            // Al hacer click en la tarjeta, asignamos el index actual para disparar el Spotlight/Lightbox
                                            onClick={() => setLightboxIndex(index)}
                                            className={`group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 shadow-sm transition-all duration-300 cursor-zoom-in hover:scale-[1.02] dark:border-gray-700 dark:bg-gray-900
                                                ${removingId === pic.id ? 'opacity-0 scale-90 pointer-events-none duration-200' : ''}`}
                                        >
                                            <div className="aspect-[3/4] w-full overflow-hidden bg-gray-100">
                                                <img
                                                    src={pic.photo_url}
                                                    alt={pic.notes || "Progreso"}
                                                    loading="lazy"
                                                    className="h-full w-full object-cover object-center"
                                                />
                                            </div>

                                            {/* Capa superpuesta constante en el hover */}
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex flex-col justify-between p-2">

                                                {/* Botón de Borrado Lógico */}
                                                <div className="flex justify-end">
                                                    <button
                                                        type="button"
                                                        onClick={(e) => handleSoftDelete(pic.id, e)}
                                                        className="rounded-md bg-red-600/90 p-1 text-white hover:bg-red-600 transition shadow backdrop-blur-xs"
                                                        title="Mover a papelera (Borrado lógico)"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                {/* Nota */}
                                                {pic.notes && (
                                                    <p className="truncate text-[10px] text-white font-semibold text-center w-full bg-black/40 py-0.5 rounded-xs">
                                                        {pic.notes}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                    </div>
                    {has_data && data && (
                        <div className="bg-white dark:bg-gray-800 shadow rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                            <h3 className="text-sm font-bold text-gray-900 dark:text-gray-100 mb-4">Bitácora General Antropométrica</h3>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                                    <thead className="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-300 font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th className="px-3 py-3 text-left">Fecha</th>
                                            <th className="px-3 py-3 text-left">Peso</th>
                                            <th className="px-3 py-3 text-left">IMC</th>
                                            <th className="px-3 py-3 text-left">Torso S.</th>
                                            <th className="px-3 py-3 text-left">Abd. Sup.</th>
                                            <th className="px-3 py-3 text-left">Abd. Med.</th>
                                            <th className="px-3 py-3 text-left">Abd. Inf.</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                        {[...data.historial].reverse().map((item, idx) => (
                                            <tr key={idx} className="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition-colors">
                                                <td className="px-3 py-3 whitespace-nowrap font-bold text-gray-900 dark:text-white">{item.fecha}</td>
                                                <td className="px-3 py-3 whitespace-nowrap font-bold text-blue-600 dark:text-blue-400">{item.peso} kg</td>
                                                <td className="px-3 py-3 whitespace-nowrap text-gray-500">{item.imc.toFixed(1)}</td>
                                                <td className="px-3 py-3 whitespace-nowrap text-gray-500">{item.tSuperior} cm</td>
                                                <td className="px-3 py-3 whitespace-nowrap text-gray-500">{item.abdomenSuperior} cm</td>
                                                <td className="px-3 py-3 whitespace-nowrap text-gray-500">{item.abdomenMedio} cm</td>
                                                <td className="px-3 py-3 whitespace-nowrap text-gray-500">{item.abdomenInferior} cm</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* 4. Componente inyectado del Lightbox para el Spotlight al hacer Click */}
            <Lightbox
                index={lightboxIndex}
                open={lightboxIndex >= 0}
                close={() => setLightboxIndex(-1)}
                slides={slides}
                styles={{ container: { backgroundColor: "rgba(0, 0, 0, 0.9)" } }}
            />
        </AuthenticatedLayout>
    );
}
