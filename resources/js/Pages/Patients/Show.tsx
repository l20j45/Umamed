import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Avatar from '@/Components/Avatar';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Medida {
    id: number;
    peso: string;
    imc?: string;
    tSuperior?: string;
    abdomenSuperior?: string;
    abdomenMedio?: string;
    abdomenInferior?: string;
    exSuperior?: string;
    exInferior?: string;
    notes?: string;
    created_at: string;
}

interface ProgressPhoto {
    id: number;
    path: string;
    description?: string;
    created_at: string;
}

interface PacienteObservacion {
    id: number;
    titulo: string;
    observaciones: string;
    dieta?: string | null;
    recomendaciones?: string | null;
    formato?: string;
    created_at: string;
    creador?: {
        name: string;
    } | null;
}

interface DocumentoSeguimiento {
    id: number;
    titulo: string;
    categoria: string;
    notas?: string | null;
    tiene_pdf?: boolean;
    created_at: string;
    subido_por?: { name: string } | null;
}

const CATEGORIAS_SEGUIMIENTO: Record<string, string> = {
    menu: 'Menú / plan alimenticio',
    pedimento: 'Pedimento de estudios',
    nota: 'Nota o instrucciones',
    otro: 'Otro documento',
};


interface ShowProps {
    auth?: {
        user: any;
    };
    patient: {
        id: number;
        name: string;
        last_name?: string;
        avatar?: string; // Nombre del archivo guardado en el disco de avatares
        medidas: Medida[];
        progress_photos?: ProgressPhoto[]; // Relación con sus fotos de evolución
        observaciones?: PacienteObservacion[];
        documentosSeguimiento?: DocumentoSeguimiento[];
        email?: string;
        phone?: string;
        estatura?: string | number | null;
        treatment_start_date?: string;
    };
}

export default function Show({ patient }: ShowProps) {
    // Inicializamos el formulario con los campos antropométricos
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        peso: '',
        tSuperior: '',
        abdomenSuperior: '',
        abdomenMedio: '',
        abdomenInferior: '',
        exSuperior: '',
        exInferior: '',
        notes: '',
    });

    // Id de la medición que se está corrigiendo (null = nueva captura).
    const [medidaEditando, setMedidaEditando] = useState<number | null>(null);

    const estaturaForm = useForm({
        estatura: patient.estatura ? String(patient.estatura) : '',
    });

    const observacionForm = useForm({
        titulo: '',
        observaciones: '',
        dieta: '',
        recomendaciones: '',
        formato: 'estandar',
    });

    // --- SEGUIMIENTO: documentos PDF que el staff sube entre consultas ---
    const rolesActuales = (
        ((usePage().props as unknown as { auth?: { user?: { roles?: string[] } } })
            .auth?.user?.roles) ?? []
    ) as string[];
    const puedeGestionarSeguimiento = rolesActuales.includes('admin')
        || rolesActuales.includes('bariatra');

    const documentoForm = useForm({
        titulo: '',
        categoria: 'menu',
        notas: '',
        pdf: null as File | null,
    });

    // Sólo el menú alimenticio exige PDF; los demás tipos son comentario.
    const exigePdfs = documentoForm.data.categoria === 'menu';

    const guardarDocumento = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        documentoForm.post(route('patients.seguimiento.store', patient.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () =>
                documentoForm.reset('titulo', 'categoria', 'notas', 'pdf'),
        });
    };

    const eliminarDocumento = (documento: DocumentoSeguimiento) => {
        if (
            !window.confirm(
                '¿Eliminar este documento del expediente? No se puede deshacer.',
            )
        ) {
            return;
        }

        router.delete(
            route('patients.seguimiento.destroy', {
                id: patient.id,
                documento: documento.id,
            }),
            { preserveScroll: true },
        );
    };


    const guardarEstatura = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        estaturaForm.patch(route('patients.update', patient.id), {
            preserveScroll: true,
        });
    };

    const limpiarFormulario = () => {
        reset(
            'peso',
            'tSuperior',
            'abdomenSuperior',
            'abdomenMedio',
            'abdomenInferior',
            'exSuperior',
            'exInferior',
            'notes',
        );
        setMedidaEditando(null);
    };

    const guardarObservacion = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        observacionForm.post(route('patients.observations.store', patient.id), {
            preserveScroll: true,
            onSuccess: () => {
                observacionForm.reset('titulo', 'observaciones', 'dieta', 'recomendaciones', 'formato');
            },
        });
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        const opciones = { onSuccess: () => limpiarFormulario() };

        if (medidaEditando) {
            patch(
                route('patients.measures.update', {
                    id: patient.id,
                    medida: medidaEditando,
                }),
                opciones,
            );

            return;
        }

        post(route('patients.measures.store', { id: patient.id }), opciones);
    };

    const editarMedida = (medida: Medida) => {
        setMedidaEditando(medida.id);
        setData({
            peso: String(medida.peso ?? ''),
            tSuperior: String(medida.tSuperior ?? ''),
            abdomenSuperior: String(medida.abdomenSuperior ?? ''),
            abdomenMedio: String(medida.abdomenMedio ?? ''),
            abdomenInferior: String(medida.abdomenInferior ?? ''),
            exSuperior: String(medida.exSuperior ?? ''),
            exInferior: String(medida.exInferior ?? ''),
            notes: medida.notes ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const eliminarMedida = (medida: Medida) => {
        if (
            !window.confirm(
                '¿Eliminar esta medición del expediente? No se puede deshacer.',
            )
        ) {
            return;
        }

        router.delete(
            route('patients.measures.destroy', {
                id: patient.id,
                medida: medida.id,
            }),
            { preserveScroll: true },
        );

        if (medidaEditando === medida.id) {
            limpiarFormulario();
        }
    };

    // --- CÁLCULOS CLÍNICOS EN CALIENTE ---
    const totalConsultas = patient.medidas.length;
    const primeraMedicion = totalConsultas > 0 ? patient.medidas[0] : null;
    const ultimaMedicion =
        totalConsultas > 0 ? patient.medidas[totalConsultas - 1] : null;

    const pesoPerdidoTotal =
        primeraMedicion && ultimaMedicion
            ? parseFloat(primeraMedicion.peso) - parseFloat(ultimaMedicion.peso)
            : 0;

    const diferenciaUltimaConsulta =
        totalConsultas > 1 && ultimaMedicion
            ? parseFloat(patient.medidas[totalConsultas - 2].peso) -
              parseFloat(ultimaMedicion.peso)
            : 0;

    // La foto del paciente; si no tiene, el avatar muestra sus iniciales.
    const patientAvatarUrl = patient.avatar
        ? `/user-avatars/${patient.avatar}`
        : null;

    return (
        <AuthenticatedLayout
            header = {
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Expediente:{' '}
                        <span className="text-emerald-600 dark:text-emerald-400">
                            {patient.name} {patient.last_name || ''}
                        </span>
                    </h2>
                    <span className="w-fit rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                        ID Paciente: #{patient.id}
                    </span>
                    <a
                        href={route('patients.pdf', patient.id)}
                        className="w-fit rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    >
                        Descargar expediente (PDF)
                    </a>
                </div>
            }
        >
            <Head title={`Expediente - ${patient.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    
                    {/* TARJETA 1: DATOS PERSONALES DEL PACIENTE + AVATAR */}
                    <div className="overflow-hidden border border-gray-200 bg-white p-6 shadow-sm sm:rounded-lg dark:border-gray-700 dark:bg-gray-800">
                        <div className="flex flex-col gap-6 md:flex-row md:items-center">
                            {/* Avatar del paciente */}
                            <div className="h-20 w-20 shrink-0 overflow-hidden rounded-full border border-sand bg-gray-100">
                                <Avatar
                                    nombre={patient.name}
                                    url={patientAvatarUrl}
                                />
                            </div>

                            <div className="w-full">
                                <h3 className="mb-4 text-xs font-bold uppercase tracking-wider text-gray-400">
                                    Información de Contacto
                                </h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-4">
                                    <div>
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            Correo Electrónico
                                        </span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {patient.email}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            Teléfono Móvil
                                        </span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {patient.phone || 'No registrado'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            Inicio de Tratamiento
                                        </span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {patient.treatment_start_date
                                                ? new Date(
                                                    patient.treatment_start_date,
                                                ).toLocaleDateString()
                                                : 'No asignado'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-xs text-gray-500 dark:text-gray-400">
                                            Total de Mediciones
                                        </span>
                                        <span className="text-sm font-bold text-blue-600 dark:text-blue-400">
                                            {totalConsultas} visitas
                                        </span>
                                    </div>
                                </div>

                                <form
                                    onSubmit={guardarEstatura}
                                    className="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-3 dark:border-gray-700"
                                >
                                    <div>
                                        <label className="block text-xs text-gray-500 dark:text-gray-400">
                                            Estatura (m)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.5"
                                            max="2.5"
                                            placeholder="1.70"
                                            value={estaturaForm.data.estatura}
                                            onChange={(e) =>
                                                estaturaForm.setData(
                                                    'estatura',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-28 rounded border-gray-300 bg-gray-50 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={estaturaForm.processing}
                                        className="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {estaturaForm.processing
                                            ? 'Guardando…'
                                            : 'Guardar estatura'}
                                    </button>
                                    {estaturaForm.errors.estatura && (
                                        <span className="text-xs text-red-500">
                                            {estaturaForm.errors.estatura}
                                        </span>
                                    )}
                                    <span className="text-[11px] text-gray-400">
                                        Se usa para calcular el IMC de cada
                                        medición.
                                    </span>
                                </form>
                            </div>
                        </div>
                    </div>

                    {/* TARJETA 2: OBSERVACIONES Y RECOMENDACIONES DEL BARIATRA */}
                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div className="mb-4 flex items-center justify-between gap-3 border-b border-gray-200 pb-3 dark:border-gray-700">
                            <h3 className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                Seguimiento del bariatra
                            </h3>
                            <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                Formato estándar
                            </span>
                        </div>

                        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                            <form onSubmit={guardarObservacion} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Título del seguimiento
                                    </label>
                                    <input
                                        type="text"
                                        value={observacionForm.data.titulo}
                                        onChange={(e) => observacionForm.setData('titulo', e.target.value)}
                                        className="w-full rounded border-gray-300 bg-gray-50 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="Ej. Seguimiento 04"
                                    />
                                    {observacionForm.errors.titulo && (
                                        <span className="mt-1 block text-xs text-red-500">{observacionForm.errors.titulo}</span>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Observaciones del paciente
                                    </label>
                                    <textarea
                                        rows={5}
                                        value={observacionForm.data.observaciones}
                                        onChange={(e) => observacionForm.setData('observaciones', e.target.value)}
                                        className="w-full rounded border-gray-300 bg-gray-50 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="Describe el avance, síntomas, adherencia, evolución y cambios relevantes..."
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Dieta / Plan nutricional
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={observacionForm.data.dieta}
                                        onChange={(e) => observacionForm.setData('dieta', e.target.value)}
                                        className="w-full rounded border-gray-300 bg-gray-50 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="Incluye calorías, macros, horarios, alimentos o restricciones..."
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Recomendaciones
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={observacionForm.data.recomendaciones}
                                        onChange={(e) => observacionForm.setData('recomendaciones', e.target.value)}
                                        className="w-full rounded border-gray-300 bg-gray-50 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="Incluye rutina, hidratación, actividad física, seguimiento y metas..."
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={observacionForm.processing}
                                    className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                                >
                                    {observacionForm.processing ? 'Guardando...' : 'Guardar seguimiento'}
                                </button>
                            </form>

                            <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <h4 className="mb-3 text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Historial de seguimientos
                                </h4>

                                {(patient.observaciones ?? []).length === 0 ? (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Aún no hay observaciones registradas.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {(patient.observaciones ?? []).map((obs) => (
                                            <div key={obs.id} className="rounded border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                                                <div className="mb-2 flex items-start justify-between gap-2">
                                                    <div>
                                                        <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{obs.titulo}</p>
                                                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                                            {new Date(obs.created_at).toLocaleDateString('es-MX', {
                                                                day: 'numeric',
                                                                month: 'short',
                                                                year: 'numeric',
                                                            })}
                                                        </p>
                                                    </div>
                                                    <a
                                                        href={route('patients.observations.pdf', {
                                                            id: patient.id,
                                                            observacion: obs.id,
                                                        })}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300"
                                                    >
                                                        PDF
                                                    </a>
                                                </div>
                                                <p className="text-xs text-gray-600 dark:text-gray-300">
                                                    <span className="font-semibold">Observaciones:</span> {obs.observaciones.slice(0, 120)}{obs.observaciones.length > 120 ? '…' : ''}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* TARJETA 2: INDICADORES RÁPIDOS */}
                    {ultimaMedicion && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="rounded-lg border border-l-4 border-emerald-500 border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span className="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Peso Actual
                                </span>
                                <span className="text-2xl font-black text-gray-900 dark:text-gray-100">
                                    {ultimaMedicion.peso} kg
                                </span>
                                <span className="mt-1 block text-xs text-gray-400">
                                    Último registro médico
                                </span>
                            </div>
                            <div className="rounded-lg border border-l-4 border-blue-500 border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span className="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Pérdida Total
                                </span>
                                <span
                                    className={`text-2xl font-black ${pesoPerdidoTotal >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                                >
                                    {pesoPerdidoTotal >= 0
                                        ? `-${pesoPerdidoTotal.toFixed(1)}`
                                        : `+${Math.abs(pesoPerdidoTotal).toFixed(1)}`}{' '}
                                    kg
                                </span>
                                <span className="mt-1 block text-xs text-gray-400">
                                    Desde la primera consulta
                                </span>
                            </div>
                            <div className="rounded-lg border border-l-4 border-gray-200 border-pine-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span className="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Evolución Interconsulta
                                </span>
                                <span
                                    className={`text-2xl font-black ${diferenciaUltimaConsulta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                                >
                                    {diferenciaUltimaConsulta >= 0
                                        ? `-${diferenciaUltimaConsulta.toFixed(1)}`
                                        : `+${Math.abs(diferenciaUltimaConsulta).toFixed(1)}`}{' '}
                                    kg
                                </span>
                                <span className="mt-1 block text-xs text-gray-400">
                                    Comparado con la cita anterior
                                </span>
                            </div>
                        </div>
                    )}

                    {/* INTERFAZ PRINCIPAL */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* FORMULARIO DE CAPTURA */}
                        <div className="h-fit rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <h3 className="mb-4 border-b border-gray-200 pb-2 text-lg font-bold text-emerald-600 dark:border-gray-700 dark:text-emerald-400">
                                {medidaEditando
                                    ? 'Editar medición'
                                    : 'Nueva Captura Antropométrica'}
                            </h3>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Peso (kg) *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        className={`w-full rounded border-gray-300 bg-gray-50 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 ${errors.peso ? 'border-red-500' : ''}`}
                                        value={data.peso}
                                        onChange={(e) =>
                                            setData('peso', e.target.value)
                                        }
                                        required
                                    />
                                    {errors.peso && (
                                        <span className="mt-1 block text-xs text-red-500">
                                            {errors.peso}
                                        </span>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        T. Superior / Tórax (cm)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.1"
                                        className="w-full rounded border-gray-300 bg-gray-50 text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        value={data.tSuperior}
                                        onChange={(e) =>
                                            setData('tSuperior', e.target.value)
                                        }
                                    />
                                </div>

                                <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                    <span className="block text-xs font-bold uppercase tracking-wider text-gray-400">
                                        Perímetros Abdominales
                                    </span>
                                    <div className="grid grid-cols-3 gap-2">
                                        <div>
                                            <label className="mb-1 block text-xs text-gray-500">
                                                Sup. (cm)
                                            </label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                className="w-full rounded border-gray-300 bg-white text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                value={data.abdomenSuperior}
                                                onChange={(e) =>
                                                    setData(
                                                        'abdomenSuperior',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs text-gray-500">
                                                Med. (cm)
                                            </label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                className="w-full rounded border-gray-300 bg-white text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                value={data.abdomenMedio}
                                                onChange={(e) =>
                                                    setData(
                                                        'abdomenMedio',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs text-gray-500">
                                                Inf. (cm)
                                            </label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                className="w-full rounded border-gray-300 bg-white text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                value={data.abdomenInferior}
                                                onChange={(e) =>
                                                    setData(
                                                        'abdomenInferior',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Ex. Superior (cm)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.1"
                                            className="w-full rounded border-gray-300 bg-gray-50 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            value={data.exSuperior}
                                            onChange={(e) =>
                                                setData(
                                                    'exSuperior',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Ex. Inferior (cm)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.1"
                                            className="w-full rounded border-gray-300 bg-gray-50 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                            value={data.exInferior}
                                            onChange={(e) =>
                                                setData(
                                                    'exInferior',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Notas Clínicas
                                    </label>
                                    <textarea
                                        className="w-full rounded border-gray-300 bg-gray-50 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        rows={3}
                                        placeholder="Observaciones de la consulta..."
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData('notes', e.target.value)
                                        }
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Guardando...'
                                        : medidaEditando
                                          ? 'Guardar cambios'
                                          : 'Guardar Consulta'}
                                </button>

                                {medidaEditando && (
                                    <button
                                        type="button"
                                        onClick={limpiarFormulario}
                                        className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Cancelar edición
                                    </button>
                                )}
                            </form>
                        </div>

                        {/* SECCIÓN DERECHA: HISTORIAL CLÍNICO Y GALERÍA DE PROGRESO */}
                        <div className="space-y-6 lg:col-span-2">
                            
                            {/* GALERÍA DE FOTOS DE PROGRESO */}
                            <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h3 className="mb-4 border-b border-gray-200 pb-2 text-lg font-bold text-emerald-600 dark:border-gray-700 dark:text-emerald-400">
                                    Galería de Evolución Fotográfica
                                </h3>

                                {!patient.progress_photos || patient.progress_photos.length === 0 ? (
                                    <div className="py-6 text-center border border-dashed rounded-lg border-gray-200 dark:border-gray-700">
                                        <p className="text-sm text-gray-400">
                                            No se han cargado fotos de progreso para este paciente todavía.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 max-h-[300px] overflow-y-auto pr-1">
                                        {patient.progress_photos.map((photo) => (
                                            <div 
                                                key={photo.id} 
                                                className="group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                                            >
                                                <div className="aspect-square w-full overflow-hidden">
                                                    <img 
                                                        src={`/storage/${photo.path}`} // Suponiendo almacenamiento en el disco público general
                                                        alt={photo.description || "Foto de progreso"} 
                                                        className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                    />
                                                </div>
                                                <div className="p-2 text-[10px] text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border-t dark:border-gray-700">
                                                    <span className="block font-semibold text-gray-700 dark:text-gray-200">
                                                        {new Date(photo.created_at).toLocaleDateString('es-MX')}
                                                    </span>
                                                    {photo.description && (
                                                        <span className="block truncate text-gray-400" title={photo.description}>
                                                            {photo.description}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* HISTORIAL CLÍNICO (LÍNEA DE TIEMPO) */}
                            <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h3 className="mb-4 border-b border-gray-200 pb-2 text-lg font-bold text-blue-600 dark:border-gray-700 dark:text-blue-400">
                                    Historial de Medidas (Línea de Tiempo)
                                </h3>

                                {patient.medidas.length === 0 ? (
                                    <div className="py-12 text-center">
                                        <p className="text-sm text-gray-400">
                                            Este expediente no contiene registros todavía.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="max-h-[400px] space-y-4 overflow-y-auto pr-2">
                                        {[...patient.medidas]
                                            .reverse()
                                            .map((measure, index, array) => {
                                                const totalElementos = array.length;
                                                const posicionOriginal =
                                                    totalElementos - 1 - index;
                                                const citaAnterior =
                                                    posicionOriginal > 0
                                                        ? patient.medidas[
                                                        posicionOriginal - 1
                                                        ]
                                                        : null;
                                                const diferenciaPeso = citaAnterior
                                                    ? parseFloat(citaAnterior.peso) - parseFloat(measure.peso)
                                                    : null;

                                                return (
                                                    <div
                                                        key={measure.id}
                                                        className="rounded-lg border border-gray-200 bg-gray-50 p-4 transition hover:shadow-sm dark:border-gray-700 dark:bg-gray-700/20"
                                                    >
                                                        <div className="mb-3 flex items-center justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                                                            <span className="text-sm font-bold text-gray-700 dark:text-gray-300">
                                                                Consulta:{' '}
                                                                {new Date(
                                                                    measure.created_at,
                                                                ).toLocaleDateString(
                                                                    'es-MX',
                                                                    {
                                                                        day: 'numeric',
                                                                        month: 'short',
                                                                        year: 'numeric',
                                                                    },
                                                                )}
                                                            </span>
                                                            <div className="flex space-x-2">
                                                                {diferenciaPeso !==
                                                                    null && (
                                                                        <span
                                                                            className={`rounded px-2 py-0.5 text-xs font-bold ${diferenciaPeso >= 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'}`}
                                                                        >
                                                                            {diferenciaPeso >= 0
                                                                                ? `⬇ ${diferenciaPeso.toFixed(1)} kg`
                                                                                : `⬆ ${Math.abs(diferenciaPeso).toFixed(1)} kg`}
                                                                        </span>
                                                                    )}
                                                                <span className="rounded bg-gray-200 px-2.5 py-0.5 text-xs font-black text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                                    IMC:{' '}
                                                                    {measure.imc
                                                                        ? parseFloat(
                                                                            measure.imc,
                                                                        ).toFixed(
                                                                            1,
                                                                        )
                                                                        : 'N/A'}
                                                                </span>

                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        editarMedida(
                                                                            measure,
                                                                        )
                                                                    }
                                                                    className="rounded border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                                >
                                                                    Editar
                                                                </button>

                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        eliminarMedida(
                                                                            measure,
                                                                        )
                                                                    }
                                                                    className="rounded border border-red-200 px-2 py-0.5 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400"
                                                                >
                                                                    Eliminar
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div className="grid grid-cols-2 gap-x-2 gap-y-3 text-xs text-gray-600 sm:grid-cols-4 dark:text-gray-300">
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Peso
                                                                </span>
                                                                <span className="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                                    {measure.peso}{' '}
                                                                    kg
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Tórax Sup.
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {measure.tSuperior
                                                                        ? `${measure.tSuperior} cm`
                                                                        : '-'}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Abd. Sup.
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {measure.abdomenSuperior
                                                                        ? `${measure.abdomenSuperior} cm`
                                                                        : '-'}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Abd. Med.
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {measure.abdomenMedio
                                                                        ? `${measure.abdomenMedio} cm`
                                                                        : '-'}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Abd. Inf.
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {measure.abdomenInferior
                                                                        ? `${measure.abdomenInferior} cm`
                                                                        : '-'}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Ex. Sup.
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {measure.exSuperior
                                                                        ? `${measure.exSuperior} cm`
                                                                        : '-'}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span className="block text-gray-400">
                                                                    Ex. Inf.
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {measure.exInferior
                                                                        ? `${measure.exInferior} cm`
                                                                        : '-'}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        {measure.notes && (
                                                            <div className="mt-3 rounded border border-gray-200 bg-white p-2.5 text-xs italic text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                                                {measure.notes}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                    </div>
                                )}
                            </div>

                            {/* SEGUIMIENTO: MENÚS, PEDIMENTOS Y NOTAS EN PDF */}
                            <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h3 className="mb-4 border-b border-gray-200 pb-2 text-lg font-bold text-emerald-600 dark:border-gray-700 dark:text-emerald-400">
                                    Seguimiento (documentos del consultorio)
                                </h3>

                                {puedeGestionarSeguimiento && (
                                    <form
                                        onSubmit={guardarDocumento}
                                        className="mb-5 rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700/20"
                                    >
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Título
                                                </label>
                                                <input
                                                    type="text"
                                                    required
                                                    maxLength={120}
                                                    placeholder="Menú de septiembre"
                                                    value={documentoForm.data.titulo}
                                                    onChange={(e) =>
                                                        documentoForm.setData(
                                                            'titulo',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded border-gray-300 bg-white text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                />
                                                {documentoForm.errors.titulo && (
                                                    <span className="mt-1 block text-xs text-red-500">
                                                        {documentoForm.errors.titulo}
                                                    </span>
                                                )}
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Tipo de documento
                                                </label>
                                                <select
                                                    value={documentoForm.data.categoria}
                                                    onChange={(e) =>
                                                        documentoForm.setData(
                                                            'categoria',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded border-gray-300 bg-white text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                >
                                                    {Object.entries(CATEGORIAS_SEGUIMIENTO).map(
                                                        ([valor, texto]) => (
                                                            <option key={valor} value={valor}>
                                                                {texto}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>
                                            {exigePdfs ? (
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Archivo PDF
                                                    </label>
                                                    <input
                                                        type="file"
                                                        accept="application/pdf"
                                                        required
                                                        onChange={(e) =>
                                                            documentoForm.setData(
                                                                'pdf',
                                                                e.target.files?.[0] ?? null,
                                                            )
                                                        }
                                                        className="w-full rounded border-gray-300 bg-white text-xs text-gray-900 file:mr-2 file:rounded file:border-0 file:bg-emerald-600 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-white dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                    />
                                                    {documentoForm.errors.pdf && (
                                                        <span className="mt-1 block text-xs text-red-500">
                                                            {documentoForm.errors.pdf}
                                                        </span>
                                                    )}
                                                </div>
                                            ) : (
                                                <div className="flex items-end">
                                                    <span className="w-full rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                                        Sin archivo: la nota se
                                                        entrega como comentario.
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div className="sm:col-span-2">
                                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {exigePdfs
                                                        ? 'Notas para el paciente (opcional)'
                                                        : 'Comentario para el paciente'}
                                                </label>
                                                <input
                                                    type="text"
                                                    required={!exigePdfs}
                                                    maxLength={500}
                                                    placeholder={
                                                        exigePdfs
                                                            ? 'Instrucciones breves del documento…'
                                                            : 'Ej.: continuar menú 3, repetir biometría en 15 días…'
                                                    }
                                                    value={documentoForm.data.notas}
                                                    onChange={(e) =>
                                                        documentoForm.setData(
                                                            'notas',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded border-gray-300 bg-white text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                />
                                                {documentoForm.errors.notas && (
                                                    <span className="mt-1 block text-xs text-red-500">
                                                        {documentoForm.errors.notas}
                                                    </span>
                                                )}
                                            </div>
                                            {exigePdfs ? (
                                                <div className="flex items-end">
                                                    <button
                                                        type="submit"
                                                        disabled={documentoForm.processing}
                                                        className="w-full rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                                    >
                                                        {documentoForm.processing
                                                            ? 'Subiendo…'
                                                            : 'Subir documento'}
                                                    </button>
                                                </div>
                                            ) : (
                                                <button
                                                    type="submit"
                                                    disabled={documentoForm.processing}
                                                    className="mt-5 w-full rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50 sm:mt-0"
                                                >
                                                    {documentoForm.processing
                                                        ? 'Guardando…'
                                                        : 'Enviar nota'}
                                                </button>
                                            )}
                                        </div>
                                    </form>
                                )}

                                {(patient.documentosSeguimiento ?? []).length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-gray-200 py-6 text-center dark:border-gray-700">
                                        <p className="text-sm text-gray-400">
                                            Sin documentos de seguimiento todavía. Aquí
                                            van los menús, pedimentos de estudios y notas
                                            que se comparten entre consultas.
                                        </p>
                                    </div>
                                ) : (
                                    <ul className="space-y-3">
                                        {(patient.documentosSeguimiento ?? []).map(
                                            (documento) => (
                                                <li
                                                    key={documento.id}
                                                    className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700/20"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                                        <div>
                                                            <span className="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                                {
                                                                    CATEGORIAS_SEGUIMIENTO[
                                                                        documento.categoria
                                                                    ] ?? documento.categoria
                                                                }
                                                            </span>
                                                            <span className="ml-2 text-sm font-bold text-gray-800 dark:text-gray-100">
                                                                {documento.titulo}
                                                            </span>
                                                            <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                                                {new Date(
                                                                    documento.created_at,
                                                                ).toLocaleDateString('es-MX')}
                                                            </span>
                                                            {documento.subido_por && (
                                                                <span className="ml-2 text-xs text-gray-400">
                                                                    por{' '}
                                                                    {documento.subido_por.name}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="flex space-x-2">
                                                            {documento.tiene_pdf !== false && (
                                                                <a
                                                                    href={route(
                                                                        'seguimiento.descargar',
                                                                        documento.id,
                                                                    )}
                                                                    className="rounded border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                                >
                                                                    Descargar PDF
                                                                </a>
                                                            )}
                                                            {puedeGestionarSeguimiento && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        eliminarDocumento(
                                                                            documento,
                                                                        )
                                                                    }
                                                                    className="rounded border border-red-200 px-2 py-0.5 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400"
                                                                >
                                                                    Eliminar
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {documento.notas && (
                                                        <div className="mt-2 rounded border border-gray-200 bg-white p-2.5 text-xs italic text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                                            {documento.notas}
                                                        </div>
                                                    )}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}