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
    categoria: string;
    categoria_legible?: string;
}

interface ObservacionDoctor {
    id: number;
    titulo: string;
    observaciones: string;
    dieta?: string | null;
    recomendaciones?: string | null;
    formato?: string;
    created_at?: string;
    creador?: string | null;
}

interface DocumentoSeguimiento {
    id: number;
    titulo: string;
    categoria_legible: string;
    notas: string | null;
    tiene_pdf: boolean;
    fecha: string;
    descarga_url: string;
}

interface PortalProps {
    auth: { user: { id: number; name: string } };
    has_data: boolean;
    data: {
        ultima: MeasureItem & { notes: string | null };
        mejoras: Omit<MeasureItem, 'fecha'>;
        historial: MeasureItem[];
    } | null;
    photos: PhotoItem[];
    observaciones?: ObservacionDoctor[];
    documentos?: DocumentoSeguimiento[];
}

function BodyViewExample({ label, view }: { label: string; view: 'front' | 'back' | 'side' }) {
    const common = {
        fill: 'none',
        stroke: '#111827',
        strokeWidth: 2.2,
        strokeLinecap: 'round' as const,
        strokeLinejoin: 'round' as const,
    };

    const profile = {
        front: (
            <svg viewBox="0 0 120 180" className="h-16 w-16">
                <path {...common} d="M60 20c-10 0-18 8-18 18v8c0 11 6 19 18 19s18-8 18-19v-8c0-10-8-18-18-18Z" />
                <path {...common} d="M46 62h28l7 46-7 44H53l-7-44 7-46Z" />
                <path {...common} d="M46 72l-15 25M74 72l15 25M48 118l-16 36M72 118l16 36" />
                <path {...common} d="M58 146l-4 22M62 146l4 22" />
            </svg>
        ),
        back: (
            <svg viewBox="0 0 120 180" className="h-16 w-16">
                <path {...common} d="M60 20c-10 0-18 8-18 18v8c0 11 6 19 18 19s18-8 18-19v-8c0-10-8-18-18-18Z" />
                <path {...common} d="M46 62h28l7 46-7 44H53l-7-44 7-46Z" />
                <path {...common} d="M46 72l-16 23M74 72l16 23M49 118l-17 36M71 118l17 36" />
                <path {...common} d="M58 146l-4 22M62 146l4 22" />
            </svg>
        ),
        side: (
            <svg viewBox="0 0 120 180" className="h-16 w-16">
                <path {...common} d="M62 18c-9 0-16 8-16 18v11c0 8 4 15 11 19l9 6 9-6c7-4 11-11 11-19V36c0-10-7-18-16-18h-8Z" />
                <path {...common} d="M52 62h20l10 41v41H42V103l10-41Z" />
                <path {...common} d="M44 74L26 98M73 74l19 20M52 118l-14 35M62 118l5 35" />
                <path {...common} d="M50 146l-5 23M65 146l5 23" />
            </svg>
        )
    };

    return (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-gray-200 bg-white p-2 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
            {profile[view]}
            <span className="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
                {label}
            </span>
        </div>
    );
}

export default function Portal({
    auth,
    has_data,
    data,
    photos = [],
    observaciones = [],
    documentos = [],
}: PortalProps) {

    const [removingId, setRemovingId] = useState<number | null>(null);

    // 2. Estados para controlar el índice de la foto activa en el Spotlight (Lightbox)
    const [lightboxIndex, setLightboxIndex] = useState<number>(-1);

    const CATEGORIAS = [
        { value: 'frente', label: 'Frontal' },
        { value: 'espalda', label: 'Espalda' },
        { value: 'lateral_derecha', label: 'Lateral derecha' },
        { value: 'lateral_izquierda', label: 'Lateral izquierda' },
    ];

    const [categoriaActiva, setCategoriaActiva] = useState<string>('frente');
    const [indiceCarrusel, setIndiceCarrusel] = useState<number>(0);
    const [touchStartX, setTouchStartX] = useState<number | null>(null);

    const fotosPorCategoria = CATEGORIAS.reduce<Record<string, PhotoItem[]>>((acc, categoria) => {
        acc[categoria.value] = photos.filter((foto) => (foto.categoria ?? 'frente') === categoria.value);
        return acc;
    }, {});

    const fotosCategoriaActual = fotosPorCategoria[categoriaActiva] ?? [];
    const fotoActual = fotosCategoriaActual[indiceCarrusel] ?? null;

    const irACategoria = (categoria: string) => {
        setCategoriaActiva(categoria);
        setIndiceCarrusel(0);
    };

    const mostrarSiguiente = () => {
        if (fotosCategoriaActual.length === 0) {
            return;
        }

        setIndiceCarrusel((valor) => (valor + 1) % fotosCategoriaActual.length);
    };

    const mostrarAnterior = () => {
        if (fotosCategoriaActual.length === 0) {
            return;
        }

        setIndiceCarrusel((valor) => (
            (valor - 1 + fotosCategoriaActual.length) % fotosCategoriaActual.length
        ));
    };

    const manejarInicioToque = (evento: React.TouchEvent<HTMLDivElement>) => {
        setTouchStartX(evento.touches[0]?.clientX ?? null);
    };

    const manejarFinToque = (evento: React.TouchEvent<HTMLDivElement>) => {
        if (touchStartX === null) {
            return;
        }

        const deltaX = (evento.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;

        if (Math.abs(deltaX) > 50) {
            if (deltaX < 0) {
                mostrarSiguiente();
            } else {
                mostrarAnterior();
            }
        }

        setTouchStartX(null);
    };

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
        categoria: 'frente',
    });

    const handlePhotoSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postPhoto(route('patient.photos.store'), {
            onSuccess: () => {
                resetPhoto('photo', 'notes', 'categoria');
                setCategoriaActiva('frente');
                setIndiceCarrusel(0);
            },
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

    const slides = (fotosCategoriaActual.length > 0 ? fotosCategoriaActual : photos).map((pic) => ({
        src: pic.photo_url,
        alt: pic.notes || `${pic.categoria_legible ?? 'Progreso'} clínico`,
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
                            {observaciones.length > 0 && (
                                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-6 shadow dark:border-emerald-900/50 dark:bg-emerald-900/10">
                                    <div className="mb-4 flex items-center justify-between gap-3">
                                        <div>
                                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-400">
                                                Plan de seguimiento
                                            </p>
                                            <h3 className="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                                {observaciones[0].titulo}
                                            </h3>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase text-emerald-700 dark:border-emerald-800 dark:bg-gray-900 dark:text-emerald-300">
                                                {observaciones[0].creador ?? 'Doctor'}
                                            </span>
                                            <a
                                                href={route('patients.observations.pdf', {
                                                    id: auth.user.id,
                                                    observacion: observaciones[0].id,
                                                })}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="rounded-full border border-emerald-300 bg-white px-3 py-1 text-[10px] font-bold uppercase text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-700 dark:bg-gray-900 dark:text-emerald-300"
                                            >
                                                Ver PDF
                                            </a>
                                        </div>
                                    </div>

                                    <div className="grid gap-4 lg:grid-cols-3">
                                        <div className="rounded-lg border border-white/70 bg-white/80 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="mb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                                Observaciones
                                            </p>
                                            <p className="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">
                                                {observaciones[0].observaciones}
                                            </p>
                                        </div>

                                        <div className="rounded-lg border border-white/70 bg-white/80 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="mb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                                Dieta
                                            </p>
                                            <p className="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">
                                                {observaciones[0].dieta || 'Sin dieta especificada en este seguimiento.'}
                                            </p>
                                        </div>

                                        <div className="rounded-lg border border-white/70 bg-white/80 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="mb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                                Recomendaciones
                                            </p>
                                            <p className="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">
                                                {observaciones[0].recomendaciones || 'Sin recomendaciones adicionales.'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Historial completo: cada seguimiento se puede
                                        abrir o descargar en PDF. */}
                                    {observaciones.length > 1 && (
                                        <div className="mt-4 rounded-lg border border-white/70 bg-white/80 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                                Historial de seguimientos ({observaciones.length})
                                            </p>
                                            <ul className="space-y-2">
                                                {observaciones.map((obs) => (
                                                    <li
                                                        key={obs.id}
                                                        className="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                                                    >
                                                        <div>
                                                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                                {obs.titulo}
                                                            </p>
                                                            {obs.created_at && (
                                                                <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                                                    {new Date(obs.created_at).toLocaleDateString(
                                                                        'es-MX',
                                                                        {
                                                                            day: 'numeric',
                                                                            month: 'short',
                                                                            year: 'numeric',
                                                                        },
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <a
                                                            href={route('patients.observations.pdf', {
                                                                id: auth.user.id,
                                                                observacion: obs.id,
                                                            })}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300"
                                                        >
                                                            Ver PDF
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            )}
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
                                    <label className="mb-1 block text-[11px] font-bold uppercase text-gray-400">
                                        Categoría
                                    </label>
                                    <select
                                        value={photoData.categoria}
                                        onChange={(e) => setPhotoData('categoria', e.target.value)}
                                        className="w-full rounded-md border p-2 text-xs focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        {CATEGORIAS.map((categoria) => (
                                            <option key={categoria.value} value={categoria.value}>
                                                {categoria.label}
                                            </option>
                                        ))}
                                    </select>
                                    {photoErrors.categoria && (
                                        <span className="mt-1 block text-xs text-red-500">
                                            {photoErrors.categoria}
                                        </span>
                                    )}
                                </div>

                                <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50">
                                    <p className="mb-2 text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        Ejemplo de orientación
                                    </p>
                                    <div className="grid grid-cols-3 gap-2">
                                        <BodyViewExample label="Frontal" view="front" />
                                        <BodyViewExample label="Espalda" view="back" />
                                        <BodyViewExample label="Lateral" view="side" />
                                    </div>
                                </div>

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

                                <div className="mb-4 flex flex-wrap gap-2">
                                    {CATEGORIAS.map((categoria) => (
                                        <button
                                            key={categoria.value}
                                            type="button"
                                            onClick={() => irACategoria(categoria.value)}
                                            className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                                categoriaActiva === categoria.value
                                                    ? 'border-blue-600 bg-blue-600 text-white'
                                                    : 'border-gray-200 bg-white text-gray-600 hover:border-blue-200 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'
                                            }`}
                                        >
                                            {categoria.label}
                                        </button>
                                    ))}
                                </div>

                                {fotosCategoriaActual.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                        No hay fotos registradas en esta categoría todavía.
                                    </div>
                                ) : (
                                    <>
                                        <div
                                            className="relative mx-auto max-w-[420px] overflow-hidden rounded-xl border border-gray-200 bg-gray-100 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                            onTouchStart={manejarInicioToque}
                                            onTouchEnd={manejarFinToque}
                                            onTouchCancel={() => setTouchStartX(null)}
                                        >
                                            <div className="aspect-[4/5] w-full overflow-hidden bg-white dark:bg-gray-950">
                                                <img
                                                    key={fotoActual?.id ?? `${categoriaActiva}-${indiceCarrusel}`}
                                                    src={fotoActual?.photo_url}
                                                    alt={fotoActual?.notes || 'Progreso'}
                                                    className="h-full w-full cursor-zoom-in object-cover object-center transition-all duration-500 ease-out hover:scale-110 animate-[fadeIn_0.45s_ease]"
                                                    style={{ opacity: 1, transform: 'scale(1.01)', touchAction: 'pan-y' }}
                                                    onClick={() => setLightboxIndex(fotosCategoriaActual.indexOf(fotoActual!))}
                                                />
                                            </div>

                                            <div className="absolute inset-x-0 top-0 flex items-center justify-between p-2">
                                                <button
                                                    type="button"
                                                    onClick={mostrarAnterior}
                                                    className="rounded-full bg-black/40 p-2 text-white transition hover:bg-black/60"
                                                    aria-label="Foto anterior"
                                                >
                                                    ‹
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={mostrarSiguiente}
                                                    className="rounded-full bg-black/40 p-2 text-white transition hover:bg-black/60"
                                                    aria-label="Siguiente foto"
                                                >
                                                    ›
                                                </button>
                                            </div>

                                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-3 text-white">
                                                <div className="flex items-center justify-between gap-2 text-[11px]">
                                                    <span>{fotoActual?.categoria_legible ?? 'Foto'}</span>
                                                    <span>{fotoActual?.taken_at ?? ''}</span>
                                                </div>
                                                {fotoActual?.notes && (
                                                    <p className="mt-1 text-xs text-white/90">{fotoActual.notes}</p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
                                            {fotosCategoriaActual.map((pic, index) => (
                                                <button
                                                    key={pic.id}
                                                    type="button"
                                                    onClick={() => {
                                                        setIndiceCarrusel(index);
                                                        setLightboxIndex(index);
                                                    }}
                                                    className={`group h-16 w-16 overflow-hidden rounded-lg border-2 bg-gray-100 transition duration-300 ${
                                                        indiceCarrusel === index
                                                            ? 'border-blue-500'
                                                            : 'border-transparent hover:border-blue-200'
                                                    }`}
                                                >
                                                    <img
                                                        src={pic.photo_url}
                                                        alt={pic.notes || 'Progreso'}
                                                        className="h-full w-full object-cover transition duration-300 group-hover:scale-110"
                                                    />
                                                </button>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                    </div>

                    <style>{`
                        @keyframes fadeIn {
                            from { opacity: 0; transform: scale(1.04); }
                            to { opacity: 1; transform: scale(1.01); }
                        }
                    `}</style>

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

                    {documentos.length > 0 && (
                        <div className="bg-white dark:bg-gray-800 shadow rounded-xl p-6 border border-gray-100 dark:border-gray-700">
                            <h3 className="text-sm font-bold text-gray-900 dark:text-gray-100 mb-4">
                                Seguimiento de tu doctora
                            </h3>
                            <ul className="space-y-3">
                                {documentos.map((documento) => (
                                    <li
                                        key={documento.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700/20"
                                    >
                                        <div>
                                            <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                {documento.categoria_legible}
                                            </span>
                                            <span className="ml-2 text-sm font-bold text-gray-900 dark:text-gray-100">
                                                {documento.titulo}
                                            </span>
                                            <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                                {documento.fecha}
                                            </span>
                                            {documento.notas && (
                                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {documento.notas}
                                                </p>
                                            )}
                                        </div>
                                        {documento.tiene_pdf ? (
                                            <a
                                                href={documento.descarga_url}
                                                className="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                            >
                                                Descargar PDF
                                            </a>
                                        ) : (
                                            <span className="rounded-full border border-gray-200 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                                Solo comentario
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
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
